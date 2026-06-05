<?php

/**
 * Vercel PHP Entry Point for Laravel
 * Routes all Vercel serverless requests into Laravel's public/index.php
 */

// Fix: Vercel's writable tmp directory for Laravel storage
$_ENV['VERCEL'] = '1';

// Rewrite the request URI (Vercel passes full path)
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Serve static files from /public if they exist
$publicPath = __DIR__ . '/../public';
if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false; // Let Vercel serve static files directly
}

// Override storage paths to use /tmp (only writable dir on Vercel)
putenv('APP_STORAGE_PATH=/tmp/storage');

// Bootstrap Laravel application from public/index.php
define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

// Redirect storage paths to /tmp for serverless compatibility
$app->useStoragePath('/tmp/storage');

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
