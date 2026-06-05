<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Users and Roles ===\n\n";

$users = DB::table('users')->select('id', 'name', 'email', 'role')->get();

foreach ($users as $user) {
    echo "ID: {$user->id}\n";
    echo "Name: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Role: {$user->role}\n";
    echo "---\n";
}

echo "\nTo promote a user to admin, run:\n";
echo "php artisan tinker\n";
echo "User::where('email', 'YOUR_EMAIL')->update(['role' => 'admin']);\n";
