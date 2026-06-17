<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    /**
     * Handle ABA PayWay Webhook callback
     */
    public function paywayCallback(Request $request)
    {
        Log::info("PayWay Callback received: " . json_encode($request->all()));

        $payload = $request->all();
        $apiKey = config('services.payway.api_key');

        // Check if hash exists in the payload or in headers
        $receivedHash = $payload['hash'] ?? $request->header('X-PAYWAY-HMAC-SHA512') ?? $request->header('X-PayWay-Signature');

        if (!$receivedHash) {
            Log::warning("PayWay Callback missing signature hash.");
            return response()->json(['error' => 'Missing signature'], 400);
        }

        // Remove the hash from params to calculate hash of other parameters
        $paramsToHash = $payload;
        unset($paramsToHash['hash']);

        // 1. Sort fields by key ascending
        ksort($paramsToHash);

        // 2. Concatenate all values
        $b4hash = '';
        foreach ($paramsToHash as $value) {
            if (is_array($value)) {
                $b4hash .= json_encode($value);
            } else {
                $b4hash .= strval($value);
            }
        }

        // 3. Calculate hash
        $calculatedHash = base64_encode(hash_hmac('sha512', $b4hash, $apiKey, true));

        // 4. Verify signature
        if (trim($receivedHash) !== trim($calculatedHash)) {
            Log::warning("PayWay Callback invalid signature. Received: {$receivedHash}, Calculated: {$calculatedHash}");
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Process transaction
        $tranId = $payload['tran_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$tranId) {
            return response()->json(['error' => 'Missing transaction ID'], 400);
        }

        $order = Order::where('order_number', $tranId)->first();

        if (!$order) {
            Log::warning("PayWay Callback order not found: {$tranId}");
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Update order based on status (0 = success, others = failed/cancelled)
        if ($status == '0' || $status === 0) {
            if ($order->payment_status !== 'completed') {
                $order->update([
                    'payment_status' => 'completed',
                    'status' => 'processing',
                ]);
                Log::info("Order {$order->order_number} marked as COMPLETED via PayWay Callback.");
            }
        } else {
            $order->update([
                'payment_status' => 'failed',
            ]);
            Log::warning("Order {$order->order_number} marked as FAILED via PayWay Callback status: {$status}");
        }

        return response()->json(['status' => 'ok', 'message' => 'Callback processed successfully']);
    }
}
