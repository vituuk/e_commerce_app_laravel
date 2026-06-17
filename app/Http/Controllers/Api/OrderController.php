<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $orders = Order::with('orderItems.product')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:credit_card,paypal,google_pay,khqr',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.size' => 'nullable|string',
            'items.*.color' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Calculate totals
            $subtotal = collect($request->items)->sum(function ($item) {
                return $item['price'] * $item['quantity'];
            });
            $tax = $subtotal * 0.10; // 10% tax
            $total = $subtotal + $tax;

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'status' => 'pending',
            ]);

            // Create order items
            foreach ($request->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'size' => $item['size'] ?? null,
                    'color' => $item['color'] ?? null,
                ]);
            }

            // Clear cart items
            CartItem::where('user_id', $request->user()->id)->delete();

            DB::commit();

            // Handle KHQR generation
            $qrData = null;
            if ($request->payment_method === 'khqr') {
                $qrResult = $this->generatePayWayQR($order, $request->user());
                $qrData = [
                    'qr_string' => $qrResult['qr_string'],
                    'abapay_deeplink' => $qrResult['abapay_deeplink'],
                    'is_mock' => $qrResult['is_mock'] ?? false,
                ];
            }

            return response()->json([
                'order' => $order->load('orderItems.product'),
                'qr_data' => $qrData,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create order',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $order = Order::with('orderItems.product.category')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($order);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'sometimes|in:pending,completed,failed',
        ]);

        $order->update($request->only(['status', 'payment_status']));

        return response()->json($order);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Only allow deletion if order is pending
        if ($order->status !== 'pending') {
            return response()->json([
                'error' => 'Cannot delete order that is not pending'
            ], 400);
        }

        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }

    /**
     * Generate KHQR from ABA PayWay
     */
    private function generatePayWayQR($order, $user)
    {
        $merchantId = config('services.payway.merchant_id');
        $apiKey = config('services.payway.api_key');
        $baseUrl = config('services.payway.base_url');

        $reqTime = now()->format('YmdHis');
        $tranId = $order->order_number;
        $amount = number_format($order->total, 2, '.', '');
        
        // Split name into first and last
        $nameParts = explode(' ', trim($user->name), 2);
        $firstName = $nameParts[0] ?? 'Customer';
        $lastName = $nameParts[1] ?? 'User';

        $customer = Customer::where('user_id', $user->id)->first();
        $phone = $customer?->phone ?? '012345678';

        // Prepare request parameters for PayWay
        $params = [
            'req_time' => $reqTime,
            'merchant_id' => $merchantId,
            'tran_id' => $tranId,
            'amount' => $amount,
            'payment_option' => 'abapay_khqr',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
            'phone' => $phone,
        ];

        // 1. Sort fields by key ascending
        ksort($params);

        // 2. Concatenate all values
        $b4hash = '';
        foreach ($params as $value) {
            $b4hash .= strval($value);
        }

        // 3. Generate signature
        $hash = base64_encode(hash_hmac('sha512', $b4hash, $apiKey, true));
        $params['hash'] = $hash;

        try {
            // 4. Send request using HTTP facade
            $response = Http::post("{$baseUrl}/api/payment-gateway/v1/payments/generate-qr", $params);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] == 0) {
                    return [
                        'success' => true,
                        'qr_string' => $data['qr_string'] ?? '',
                        'abapay_deeplink' => $data['abapay_deeplink'] ?? '',
                        'is_mock' => false,
                    ];
                } else {
                    Log::warning("PayWay error status: " . json_encode($data));
                    $message = $data['description'] ?? 'ABA PayWay returned an error.';
                }
            } else {
                $message = 'HTTP error: ' . $response->status();
            }
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
        }

        // Fallback to mock QR code in case PayWay whitelisting or credentials fail (local testing)
        Log::warning("PayWay QR Generation Failed: " . $message . ". Falling back to mock QR for testing.");
        
        return [
            'success' => true,
            'qr_string' => "00020101021230540010ec4747290110" . $order->order_number . "5204599953038405406" . $amount . "5802KH5915E-Commerce Shop6010Phnom Penh6304" . strtoupper(substr(md5($order->order_number), 0, 4)),
            'abapay_deeplink' => "aba://payway?type=payway&qrcode=" . urlencode($order->order_number),
            'is_mock' => true,
        ];
    }
}
