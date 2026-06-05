<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Laravel\Sanctum\PersonalAccessToken;
use App\Models\User;

echo "=== Token Checker ===\n\n";

// Get token from command line argument
if ($argc < 2) {
    echo "Usage: php check_token.php YOUR_TOKEN_HERE\n";
    echo "Example: php check_token.php 1|abcdef123456...\n";
    exit(1);
}

$tokenString = $argv[1];

// Parse token (format: ID|TOKEN)
$parts = explode('|', $tokenString, 2);
if (count($parts) !== 2) {
    echo "❌ Invalid token format. Expected: ID|TOKEN\n";
    exit(1);
}

$tokenId = $parts[0];
$plainToken = $parts[1];

echo "Token ID: {$tokenId}\n";
echo "Token String: {$plainToken}\n\n";

// Find token in database
$accessToken = PersonalAccessToken::find($tokenId);

if (!$accessToken) {
    echo "❌ Token not found in database!\n";
    echo "This token may have been deleted or never existed.\n";
    exit(1);
}

echo "✅ Token found in database\n";
echo "Token Name: {$accessToken->name}\n";
echo "Created: {$accessToken->created_at}\n";
echo "Last Used: " . ($accessToken->last_used_at ?? 'Never') . "\n\n";

// Get the user
$user = $accessToken->tokenable;

if (!$user) {
    echo "❌ User not found for this token!\n";
    exit(1);
}

echo "=== User Information ===\n";
echo "User ID: {$user->id}\n";
echo "Name: {$user->name}\n";
echo "Email: {$user->email}\n";
echo "Role: {$user->role}\n";
echo "Is Admin: " . ($user->role === 'admin' ? '✅ YES' : '❌ NO') . "\n\n";

if ($user->role !== 'admin') {
    echo "⚠️  THIS USER IS NOT AN ADMIN!\n";
    echo "This is why you're getting 403 Forbidden.\n\n";
    echo "To fix this, run:\n";
    echo "php artisan tinker\n";
    echo "User::find({$user->id})->update(['role' => 'admin']);\n";
} else {
    echo "✅ This user IS an admin. Token should work!\n";
    echo "If you're still getting 403, try:\n";
    echo "1. Clear cache: php artisan optimize:clear\n";
    echo "2. Make sure you're using Bearer token in header\n";
    echo "3. Check Authorization header format: Bearer {$tokenString}\n";
}
