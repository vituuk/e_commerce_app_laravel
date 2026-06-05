<?php

// Test script to verify middleware protection

$baseUrl = 'http://localhost:8000/api';

echo "=== Testing Middleware Protection ===\n\n";

// Test 1: Public route (should work)
echo "Test 1: Public route (GET /products)\n";
$ch = curl_init("$baseUrl/products");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Status: $httpCode " . ($httpCode == 200 ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test 2: Protected route without token (should fail with 401)
echo "Test 2: Protected route without token (GET /cart)\n";
$ch = curl_init("$baseUrl/cart");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Status: $httpCode " . ($httpCode == 401 ? "✅ PASS" : "❌ FAIL - Should be 401") . "\n";
echo "Response: " . substr($response, 0, 100) . "\n\n";

// Test 3: Admin route without token (should fail with 401)
echo "Test 3: Admin route without token (POST /products)\n";
$ch = curl_init("$baseUrl/products");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'title' => 'Test',
    'price' => 99,
    'description' => 'Test',
    'category_id' => 1,
    'images' => ['test.jpg']
]));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Status: $httpCode " . ($httpCode == 401 ? "✅ PASS" : "❌ FAIL - Should be 401") . "\n";
echo "Response: " . substr($response, 0, 100) . "\n\n";

echo "=== Test Complete ===\n";
