<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== All Users in Database ===\n\n";

$users = User::all();

if ($users->count() === 0) {
    echo "❌ No users found in database!\n\n";
    echo "Creating admin user...\n";
    
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin'
    ]);
    
    echo "✅ Admin created successfully!\n";
    echo "Email: admin@example.com\n";
    echo "Password: password\n";
    exit(0);
}

echo "Found {$users->count()} user(s):\n\n";

foreach ($users as $user) {
    echo "ID: {$user->id}\n";
    echo "Name: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Role: {$user->role}\n";
    echo "Is Admin: " . ($user->role === 'admin' ? '✅ YES' : '❌ NO') . "\n";
    echo "---\n";
}

$adminCount = User::where('role', 'admin')->count();
echo "\nTotal Admins: {$adminCount}\n";

if ($adminCount === 0) {
    echo "\n⚠️  WARNING: No admin users found!\n";
    echo "You need to create an admin user to access protected routes.\n\n";
    echo "Run this command:\n";
    echo "php artisan tinker\n";
    echo "User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('12345'), 'role' => 'admin']);\n";
}
