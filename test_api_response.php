<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Category;

echo "=== Testing API Response Format ===\n\n";

// Test 1: Get first product
echo "1. First Product:\n";
$product = Product::with('category')->first();
if ($product) {
    echo json_encode($product, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "No products found!\n\n";
}

// Test 2: Get all categories
echo "2. All Categories:\n";
$categories = Category::all();
echo json_encode($categories, JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Paginated products (like API)
echo "3. Paginated Products (like API returns):\n";
$paginated = Product::with('category')->paginate(15);
echo json_encode($paginated, JSON_PRETTY_PRINT) . "\n\n";
