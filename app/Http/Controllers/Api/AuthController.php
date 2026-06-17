<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:5|confirmed',
            'role' => 'sometimes|in:user,admin', // Allow role specification
        ]);

        // Create user with specified role (defaults to 'user' if not provided)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user', // Use provided role or default to 'user'
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Create admin user (Admin only)
     */
    public function createAdmin(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:5',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
        ]);

        return response()->json([
            'message' => 'Admin user created successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * Redirect to Google
     */
    public function redirectToGoogle()
    {
        return response()->json([
            'url' => \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle Google Callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->user();
            
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'name' => $user->name ?? $googleUser->getName() ?? 'Google User',
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName() ?? 'Google User',
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                    'role' => 'user',
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed', 'message' => $e->getMessage()], 401);
        }
    }

    /**
     * Verify Google Token from Mobile App
     */
    public function verifyGoogleToken(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $idToken = $request->id_token;
            
            // Validate the ID token using Google TokenInfo API
            $response = \Illuminate\Support\Facades\Http::get("https://oauth2.googleapis.com/tokeninfo", [
                'id_token' => $idToken,
            ]);

            if ($response->failed()) {
                // Fallback to checking as an access token via Socialite if HTTP request fails
                try {
                    $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->userFromToken($idToken);
                    $email = $googleUser->getEmail();
                    $name = $googleUser->getName() ?? 'Google User';
                    $googleId = $googleUser->getId();
                } catch (\Exception $socialiteEx) {
                    return response()->json([
                        'error' => 'Token verification failed',
                        'message' => 'Invalid ID token or Access token.'
                    ], 401);
                }
            } else {
                $payload = $response->json();
                
                // Verify the issuer is google
                if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'])) {
                    return response()->json(['error' => 'Token verification failed', 'message' => 'Invalid issuer'], 401);
                }
                
                $email = $payload['email'] ?? null;
                $name = $payload['name'] ?? 'Google User';
                $googleId = $payload['sub'] ?? null;
                
                if (!$email || !$googleId) {
                    return response()->json(['error' => 'Token verification failed', 'message' => 'Missing email or sub in token'], 401);
                }
            }

            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleId,
                    'name' => $user->name ?? $name,
                ]);
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                    'role' => 'user',
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token verification failed', 'message' => $e->getMessage()], 401);
        }
    }

    /**
     * Send password reset code
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'No user found with this email address.'
        ]);

        try {
            $email = $request->email;
            $code = strval(mt_rand(100000, 999999));

            // Store token in database
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => $code,
                    'created_at' => now(),
                ]
            );

            // Send raw email (writes to laravel.log under MAIL_MAILER=log)
            \Illuminate\Support\Facades\Mail::raw("Your password reset code is: {$code}. It will expire in 15 minutes.", function ($message) use ($email) {
                $message->to($email)
                        ->subject("Password Reset Verification Code");
            });

            return response()->json([
                'message' => 'A password reset code has been sent to your email.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send reset code. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password using code
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:5|confirmed',
        ], [
            'email.exists' => 'No user found with this email address.'
        ]);

        try {
            $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$record || $record->token !== $request->code) {
                return response()->json([
                    'message' => 'The verification code is invalid.'
                ], 422);
            }

            // Check if code is expired (e.g. 15 minutes)
            $createdAt = \Carbon\Carbon::parse($record->created_at);
            if ($createdAt->addMinutes(15)->isPast()) {
                // Delete expired record
                \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();

                return response()->json([
                    'message' => 'The verification code has expired. Please request a new one.'
                ], 422);
            }

            // Update user password
            $user = User::where('email', $request->email)->first();
            $user->update([
                'password' => Hash::make($request->password)
            ]);

            // Delete token record
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'message' => 'Your password has been reset successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset password. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
