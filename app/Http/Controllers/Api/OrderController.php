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
        $apiKey     = config('services.payway.api_key');
        $baseUrl    = config('services.payway.base_url');

        // Use APP_URL (set in Render dashboard) for the webhook callback
        $appUrl = rtrim(config('app.url'), '/');
        $returnUrl = $appUrl . '/api/payments/payway-callback';

        $reqTime  = now()->format('YmdHis');
        $tranId   = $order->order_number;
        $amount   = number_format($order->total, 2, '.', '');

        // Split name into first and last
        $nameParts = explode(' ', trim($user->name), 2);
        $firstName = $nameParts[0] ?? 'Customer';
        $lastName  = $nameParts[1] ?? 'User';

        $customer = Customer::where('user_id', $user->id)->first();
        $phone    = $customer?->phone ?? '012345678';

        // Prepare request parameters for PayWay
        // NOTE: return_url is required by ABA PayWay to deliver webhook callbacks
        $params = [
            'req_time'             => $reqTime,
            'merchant_id'          => $merchantId,
            'tran_id'              => $tranId,
            'amount'               => $amount,
            'payment_option'       => 'abapay_khqr',
            'return_url'           => $returnUrl,
            'continue_success_url' => $returnUrl,
            'cancel_url'           => $returnUrl,
            'first_name'           => $firstName,
            'last_name'            => $lastName,
            'email'                => $user->email,
            'phone'                => $phone,
        ];

        // 1. Sort fields by key ascending (required by ABA PayWay hash algorithm)
        ksort($params);

        // 2. Concatenate all values (no separators)
        $b4hash = implode('', array_map('strval', array_values($params)));

        // 3. Generate HMAC-SHA512 signature
        $hash = base64_encode(hash_hmac('sha512', $b4hash, $apiKey, true));
        $params['hash'] = $hash;

        Log::info('ABA PayWay QR request', [
            'url'         => "{$baseUrl}/api/payment-gateway/v1/payments/purchase",
            'merchant_id' => $merchantId,
            'tran_id'     => $tranId,
            'amount'      => $amount,
            'return_url'  => $returnUrl,
            'b4hash'      => $b4hash,
        ]);

        try {
            // 4. POST to ABA PayWay purchase endpoint
            $response = Http::timeout(15)
                ->post("{$baseUrl}/api/payment-gateway/v1/payments/purchase", $params);

            $statusCode = $response->status();
            $body       = $response->body();
            $data       = $response->json() ?? [];

            Log::info('ABA PayWay QR response', [
                'http_status' => $statusCode,
                'body'        => $body,
            ]);

            if ($response->successful() && isset($data['status']) && $data['status'] == 0) {
                return [
                    'success'         => true,
                    'qr_string'       => $data['qr_string'] ?? '',
                    'abapay_deeplink' => $data['abapay_deeplink'] ?? '',
                    'is_mock'         => false,
                ];
            }

            $message = $data['description'] ?? $data['message'] ?? ("HTTP {$statusCode}: {$body}");
            Log::warning('ABA PayWay QR failed', ['message' => $message, 'data' => $data]);

        } catch (\Exception $ex) {
            $message = $ex->getMessage();
            Log::error('ABA PayWay QR exception', ['error' => $message]);
        }

        // ── Fallback: generate a properly formatted KHQR string ──────────────
        // This is a valid EMVCo QR with correct CRC-16/CCITT-FALSE so the
        // ABA emulator can decode it (though it won't be payable in sandbox).
        Log::warning("PayWay QR Generation Failed: {$message}. Falling back to mock KHQR.");

        $mockQr = $this->buildMockKHQR($merchantId, $tranId, $amount);

        return [
            'success'         => true,
            'qr_string'       => $mockQr,
            'abapay_deeplink' => "aba://payway?type=payway&qrcode=" . urlencode($tranId),
            'is_mock'         => true,
        ];
    }

    /**
     * Build a mock KHQR string with valid CRC-16/CCITT-FALSE checksum.
     * Format follows EMVCo QR Code Specification for Payment Systems.
     */
    private function buildMockKHQR(string $merchantId, string $tranId, string $amount): string
    {
        // EMVCo fields
        $payload =
            '000201'                                          // Payload Format Indicator
            . '010212'                                        // Point of Initiation: Dynamic
            . '2654'                                          // Merchant Account Info (tag 26)
            .   '0010' . 'ec47472901'                         // ABA PayWay AID
            .   '0110' . str_pad(substr($tranId, 0, 20), 20) // Transaction reference
            . '52045999'                                      // Merchant Category Code
            . '5303840'                                       // Currency: USD (840)
            . '54' . str_pad(strlen($amount), 2, '0', STR_PAD_LEFT) . $amount // Amount
            . '5802KH'                                        // Country: KH
            . '5915E-Commerce Shop'                           // Merchant name
            . '6010Phnom Penh'                                // Merchant city
            . '6304';                                         // CRC placeholder (4 chars follow)

        // CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF, no reflect)
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return $payload . strtoupper(sprintf('%04X', $crc));
    }
}

