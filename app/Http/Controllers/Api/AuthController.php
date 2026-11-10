<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AdminOtp;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|max:128',
            'phone' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
        ]);

        $email = strtolower(trim($validated['email']));

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            return response()->json([
                'message' => 'An account with this email already exists. Please sign in instead.',
                'errors' => ['email' => ['This email is already registered.']],
            ], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => $validated['password'], // hashed by the model cast
            'phone' => $validated['phone'] ?? null,
            'location' => $validated['location'] ?? 'Ikeja, Lagos',
            'industry' => $validated['industry'] ?? 'Electronics',
            'latitude' => 6.5966,
            'longitude' => 3.3421,
            'kyc_status' => 'verified',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::whereRaw('lower(email) = ?', [strtolower(trim($validated['email']))])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
                'errors' => ['email' => ['The provided credentials are incorrect.']],
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->resolveUser($request),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string',
            'avatar' => 'nullable|string',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'industry' => 'nullable|string',
            'bio' => 'nullable|string',
            'company' => 'nullable|string',
            'settings' => 'nullable|array',
            'handle' => 'nullable|string',
        ]);

        if (isset($validated['handle'])) {
            $currentSettings = $user->settings ?? [];
            $currentSettings['handle'] = $validated['handle'];
            $validated['settings'] = $currentSettings;
            unset($validated['handle']);
        }

        // Track 30-day reviewable identity fields in profile_change_requests table
        $trackedFields = [
            'name' => 'Profile Name',
            'location' => 'Location',
            'industry' => 'Category',
        ];

        foreach ($trackedFields as $col => $label) {
            if (!empty($validated[$col]) && $validated[$col] !== $user->{$col}) {
                ProfileChangeRequest::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'field' => $label,
                    'old_value' => (string) $user->{$col},
                    'new_value' => (string) $validated[$col],
                    'days_remaining' => 30,
                    'status' => 'pending',
                    'requested_at' => now(),
                ]);

                AdminNotification::create([
                    'title' => "30-Day {$label} Change Queued",
                    'message' => "{$user->name} requested {$label} change from '{$user->{$col}}' to '{$validated[$col]}'.",
                    'type' => 'kyc',
                    'target_tab' => 'users',
                    'is_read' => false,
                ]);
            }
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Send a 6-digit password-reset code by email. The response is identical whether or
     * not the account exists so the endpoint cannot be used to discover registered emails.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($validated['email']));
        $otp = null;

        $user = User::whereRaw('lower(email) = ?', [$email])->where('is_admin', false)->first();

        if ($user) {
            $otp = (string) random_int(100000, 999999);

            AdminOtp::where('email', $email)->where('purpose', 'user_reset')->whereNull('used_at')->update(['used_at' => Carbon::now()]);
            AdminOtp::create([
                'email' => $email,
                'otp' => $otp,
                'purpose' => 'user_reset',
                'expires_at' => Carbon::now()->addMinutes(15),
            ]);
            Cache::forget("kiloneed_user_otp_attempts_{$email}");

            try {
                Mail::raw(
                    "Your Kiloneed password reset code is: {$otp}\n\nThis code expires in 15 minutes. If you did not request it, you can ignore this email.",
                    function ($message) use ($email) {
                        $message->to($email)->subject('Your Kiloneed password reset code');
                    }
                );
            } catch (\Throwable $e) {
                Log::error('Password reset email failed: ' . $e->getMessage());
            }

            if (config('mail.default') === 'log') {
                Log::info("Kiloneed password reset OTP for {$email}: {$otp}");
            }
        }

        $response = [
            'message' => 'If an account exists for that email, a 6-digit reset code has been sent.',
            'expires_in_minutes' => 15,
        ];

        if ($otp && app()->environment('local', 'testing')) {
            $response['otp_preview'] = $otp;
        }

        return response()->json($response);
    }

    /**
     * Reset the password using the emailed 6-digit code (5 wrong guesses burn the code).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|min:4|max:8',
            'password' => 'required|string|min:8|max:128',
        ]);

        $email = strtolower(trim($validated['email']));
        $invalid = response()->json(['message' => 'Invalid or expired reset code.'], 422);

        $record = AdminOtp::where('email', $email)
            ->where('purpose', 'user_reset')
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest('id')
            ->first();

        if (!$record) {
            return $invalid;
        }

        $attemptsKey = "kiloneed_user_otp_attempts_{$email}";
        $attempts = (int) Cache::get($attemptsKey, 0);

        if ($attempts >= 5) {
            $record->update(['used_at' => Carbon::now()]);
            Cache::forget($attemptsKey);
            return $invalid;
        }

        if (!hash_equals((string) $record->otp, trim($validated['otp']))) {
            Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(15));
            return $invalid;
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->where('is_admin', false)->first();

        if (!$user) {
            return $invalid;
        }

        $record->update(['used_at' => Carbon::now()]);
        Cache::forget($attemptsKey);

        $user->forceFill(['password' => $validated['password']])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset successfully. Please sign in with your new password.']);
    }
}