<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AdminOtp;
use App\Models\Notification;
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
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:100',
        ]);

        $email = strtolower(trim($validated['email']));

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            return response()->json([
                'message' => 'An account with this email already exists. Please sign in instead.',
                'errors' => ['email' => ['This email is already registered.']],
            ], 422);
        }

        $country = $validated['country'] ?? 'Nigeria';
        $state = $validated['state'] ?? 'Lagos';
        $city = $validated['city'] ?? 'Ikeja';
        $location = $validated['location'] ?? "{$city}, {$state}, {$country}";

        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'country' => $country,
            'state' => $state,
            'city' => $city,
            'location' => $location,
            'industry' => $validated['industry'] ?? 'Electronics',
            'latitude' => 6.5966,
            'longitude' => 3.3421,
            'kyc_status' => 'verified',
            'rating' => 0.0,
            'review_count' => 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $otp = $this->issueAndSendVerificationOtp($user);

        return response()->json([
            'message' => "Account created! A 6-digit verification code has been sent to {$email}.",
            'user' => $user,
            'token' => $token,
            'requires_verification' => true,
            'otp_preview' => $otp,
        ], 201);
    }

    protected function issueAndSendVerificationOtp(User $user): string
    {
        $email = strtolower(trim($user->email));
        $otp = (string) random_int(100000, 999999);

        AdminOtp::where('email', $email)
            ->where('purpose', 'email_verify')
            ->whereNull('used_at')
            ->update(['used_at' => Carbon::now()]);

        AdminOtp::create([
            'email' => $email,
            'otp' => $otp,
            'purpose' => 'email_verify',
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);
        Cache::forget("kiloneed_verify_attempts_{$email}");

        try {
            Mail::raw(
                "Hello {$user->name},\n\n" .
                "Your Kiloneed email verification code is: {$otp}\n\n" .
                "Enter this 6-digit code in the app to verify your email address. This code expires in 15 minutes.\n\n" .
                "Thank you,\nThe Kiloneed Team",
                function ($message) use ($email) {
                    $message->to($email)->subject('Verify Your Kiloneed Email Address');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Email verification OTP send failed: ' . $e->getMessage());
        }

        return $otp;
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|min:4|max:8',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if (!$user) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $record = AdminOtp::where('email', $email)
            ->where('purpose', 'email_verify')
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest('id')
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired verification code. Please request a new one.'], 422);
        }

        $attemptsKey = "kiloneed_verify_attempts_{$email}";
        $attempts = (int) Cache::get($attemptsKey, 0);
        if (!hash_equals((string) $record->otp, trim((string) $validated['otp']))) {
            $attempts++;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(15));
            if ($attempts >= 5) {
                $record->update(['used_at' => Carbon::now()]);
                return response()->json(['message' => 'Too many incorrect attempts. Please request a new code.'], 422);
            }
            return response()->json(['message' => 'Incorrect verification code.'], 422);
        }

        $record->update(['used_at' => Carbon::now()]);
        Cache::forget($attemptsKey);

        if (!$user->email_verified_at) {
            $user->forceFill(['email_verified_at' => Carbon::now()])->save();

            // Send Welcome Email
            try {
                Mail::raw(
                    "Welcome to Kiloneed, {$user->name}!\n\n" .
                    "Welcome to Kiloneed — where every need meets its solution right around you.\n\n" .
                    "Here is what you can do right away:\n" .
                    "• Post a Need: Broadcast what you are looking for and get real-time responses from nearby merchants.\n" .
                    "• Explore the Marketplace: Discover verified sellers, deals, and featured items around you.\n" .
                    "• Join Trade Groups: Collaborate with businesses and buyers in your industry.\n" .
                    "• Real-Time Chat: Message buyers and sellers directly with instant notifications.\n\n" .
                    "We are thrilled to have you on board!\n\n" .
                    "Best regards,\nThe Kiloneed Team",
                    function ($message) use ($email) {
                        $message->to($email)->subject('Welcome to Kiloneed — Every Need, Every Solution!');
                    }
                );
            } catch (\Throwable $e) {
                Log::error('Welcome email send failed: ' . $e->getMessage());
            }

            // Create welcome in-app notification
            try {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'system',
                    'title' => "Welcome to Kiloneed, {$user->name}!",
                    'description' => 'Your email is verified. Welcome to Kiloneed — where every need meets its solution right around you.',
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return response()->json([
            'message' => 'Email verified successfully! Welcome to Kiloneed.',
            'verified' => true,
            'user' => $user->fresh(),
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($validated['email']));

        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if (!$user) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified.', 'verified' => true]);
        }

        $otp = $this->issueAndSendVerificationOtp($user);

        return response()->json([
            'message' => "A new 6-digit verification code has been sent to {$email}.",
            'otp_preview' => $otp,
        ]);
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

        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if (!$user) {
            return response()->json([
                'message' => "No account found registered with '{$email}'. Please check your spelling or sign up first.",
            ], 404);
        }

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
                "Your Kiloneed verification code is: {$otp}\n\nThis code expires in 15 minutes. If you did not request it, you can ignore this email.",
                function ($message) use ($email) {
                    $message->to($email)->subject('Your Kiloneed Verification Code');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Password reset email failed: ' . $e->getMessage());
        }

        Log::info("Kiloneed password reset OTP for {$email}: {$otp}");

        return response()->json([
            'message' => "A 6-digit verification code has been sent for {$email}.",
            'expires_in_minutes' => 15,
            'otp_preview' => $otp,
        ]);
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

        $user = User::whereRaw('lower(email) = ?', [$email])->first();

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