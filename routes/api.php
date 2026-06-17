<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\UploadController;

Route::get('/diagnose', function () {
    $results = [];
    try {
        $results['config'] = [
            'DB_CONNECTION' => config('database.default'),
            'DB_HOST' => config('database.connections.pgsql.host'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'DB_USERNAME' => config('database.connections.pgsql.username'),
            'SESSION_DRIVER' => config('session.driver'),
        ];
        
        $pdo = DB::connection()->getPdo();
        $results['db_connection'] = 'SUCCESS';
        
        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tableList = array_map(function($t) { return $t->table_name; }, $tables);
        $results['tables'] = $tableList;
        
        if (in_array('products', $tableList)) {
            $results['products_count'] = DB::table('products')->count();
        } else {
            $results['products_count'] = 'N/A (table products does not exist)';
        }
    } catch (\Exception $e) {
        $results['db_connection'] = 'FAILED';
        $results['error'] = $e->getMessage();
    }
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/diagnose-payway', function () {
    $merchantId = config('services.payway.merchant_id');
    $apiKey     = config('services.payway.api_key');
    $baseUrl    = config('services.payway.base_url');
    $appUrl     = rtrim(config('app.url'), '/');

    $reqTime = now()->format('YmdHis');
    $tranId  = 'TEST' . strtoupper(substr(md5(time()), 0, 10));
    $amount  = '1.00';

    $hashParams = [
        'req_time'       => $reqTime,
        'merchant_id'    => $merchantId,
        'tran_id'        => $tranId,
        'amount'         => $amount,
        'payment_option' => 'abapay_khqr',
        'first_name'     => 'Test',
        'last_name'      => 'User',
        'email'          => 'test@test.com',
        'phone'          => '012345678',
    ];
    ksort($hashParams);
    $b4hash = implode('', array_map('strval', array_values($hashParams)));
    $hash   = base64_encode(hash_hmac('sha512', $b4hash, $apiKey, true));

    $params = array_merge($hashParams, [
        'return_url' => $appUrl . '/api/payments/payway-callback',
        'hash'       => $hash,
    ]);

    try {
        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->asForm()
            ->post("{$baseUrl}/api/payment-gateway/v1/payments/purchase", $params);

        return response()->json([
            'merchant_id'    => $merchantId,
            'api_key_prefix' => substr($apiKey, 0, 8) . '...',
            'base_url'       => $baseUrl,
            'app_url'        => $appUrl,
            'b4hash'         => $b4hash,
            'http_status'    => $response->status(),
            'aba_response'   => $response->json() ?? $response->body(),
        ], 200, [], JSON_PRETTY_PRINT);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/diagnose-cloudinary', function () {
    $results = [
        'cloudinary_cloud_name' => config('cloudinary.cloud_url') ? 'SET' : 'NOT SET',
        'env_CLOUDINARY_CLOUD_NAME' => env('CLOUDINARY_CLOUD_NAME') ?? 'NOT SET',
        'env_CLOUDINARY_API_KEY'    => env('CLOUDINARY_API_KEY') ? substr(env('CLOUDINARY_API_KEY'), 0, 6) . '...' : 'NOT SET',
        'env_CLOUDINARY_API_SECRET' => env('CLOUDINARY_API_SECRET') ? substr(env('CLOUDINARY_API_SECRET'), 0, 6) . '...' : 'NOT SET',
        'env_CLOUDINARY_URL'        => env('CLOUDINARY_URL') ? 'SET' : 'NOT SET',
    ];


    try {
        // Try to actually connect to Cloudinary with a small test upload
        $testImagePath = tempnam(sys_get_temp_dir(), 'test');
        // Create a 1x1 pixel PNG
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $testImagePath);
        imagedestroy($img);

        $cloudinary = new \Cloudinary\Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        $uploadApi = $cloudinary->uploadApi();
        $result = $uploadApi->upload($testImagePath, [
            'folder' => 'e-commerce-products-test',
            'public_id' => 'connection-test',
            'overwrite' => true,
        ]);

        unlink($testImagePath);
        $results['cloudinary_status'] = 'SUCCESS';
        $results['test_url'] = $result['secure_url'];

        // Clean up test image
        $cloudinary->adminApi()->deleteAssets(['e-commerce-products-test/connection-test']);

    } catch (\Exception $e) {
        $results['cloudinary_status'] = 'FAILED';
        $results['cloudinary_error'] = $e->getMessage();
    }

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

// ─── Public routes ───────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/payments/payway-callback', [\App\Http\Controllers\Api\PaymentCallbackController::class, 'paywayCallback']);

// ─── Google Auth ─────────────────────────────────────────
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google/verify', [AuthController::class, 'verifyGoogleToken']);

// ─── Public: Categories & Products ───────────────────────
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
 

// ─── Protected (any authenticated user) ──────────────────
Route::middleware(['auth:sanctum'])->group(function () {
    // User info & logout
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/upload', [UploadController::class, 'upload']);

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Orders (every logged-in user can manage their own orders)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // Customer profile (every logged-in user can manage their own profile)
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);
});

// ─── Admin-only routes ────────────────────────────────────
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/admin/users', [AuthController::class, 'createAdmin']);

    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::post('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/products/bulk', [ProductController::class, 'bulkStore']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::post('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});