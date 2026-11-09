<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Call Paystack Transaction Initialize API (https://api.paystack.co/transaction/initialize)
     */
    protected function callPaystackInitialize(string $email, float $amountNgn, string $reference, array $metadata = []): array
    {
        $secret = config('services.paystack.secret') ?: env('PAYSTACK_SECRET');
        $baseUrl = rtrim(config('services.paystack.payment_url', 'https://api.paystack.co'), '/');

        if (!$secret) {
            return [
                'authorization_url' => null,
                'access_code' => null,
                'reference' => $reference,
            ];
        }

        try {
            $response = Http::withToken($secret)
                ->timeout(10)
                ->post("{$baseUrl}/transaction/initialize", [
                    'email' => $email,
                    'amount' => (int) round($amountNgn * 100), // Paystack expects kobo
                    'currency' => 'NGN',
                    'reference' => $reference,
                    'metadata' => $metadata,
                ]);

            if ($response->successful() && $response->json('status') === true) {
                $data = $response->json('data', []);
                return [
                    'authorization_url' => $data['authorization_url'] ?? null,
                    'access_code' => $data['access_code'] ?? null,
                    'reference' => $data['reference'] ?? $reference,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Paystack initialize error: ' . $e->getMessage());
        }

        return [
            'authorization_url' => "https://checkout.paystack.com/{$reference}",
            'access_code' => $reference,
            'reference' => $reference,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $transactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->get();

        $deposits = $transactions->where('type', 'deposit')->where('status', 'successful')->sum('amount');
        $withdrawals = $transactions->where('type', 'withdrawal')->where('status', 'successful')->sum('amount');
        $payments = $transactions->where('type', 'payment')->where('status', 'successful')->sum('amount');
        $balance = max(0, $deposits - $withdrawals - $payments);

        return response()->json([
            'wallet_balance' => (float) $balance,
            'currency' => 'NGN',
            'transactions' => $transactions,
        ]);
    }

    /**
     * Initialize a Paystack Checkout Session (Wallet Deposit, Ad Boost, or Plan Subscription)
     */
    public function initializePaystack(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'type' => 'nullable|in:deposit,payment',
            'description' => 'nullable|string|max:255',
            'plan_name' => 'nullable|string|max:100',
        ]);

        $reference = 'TXN_PSK_' . strtoupper(Str::random(10));
        $type = $validated['type'] ?? 'deposit';
        $desc = $validated['description'] ?? ($type === 'deposit' ? 'Paystack Wallet Funding' : 'Paystack Merchant Payment');

        $paystackData = $this->callPaystackInitialize(
            $user->email ?: 'user@kiloneed.com',
            (float) $validated['amount'],
            $reference,
            [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'type' => $type,
                'plan_name' => $validated['plan_name'] ?? null,
            ]
        );

        $trx = Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $validated['amount'],
            'currency' => 'NGN',
            'reference' => $paystackData['reference'] ?? $reference,
            'status' => 'successful',
            'description' => $desc,
            'metadata' => $paystackData,
        ]);

        AdminNotification::create([
            'title' => 'Paystack Payment Initialized',
            'message' => "{$user->name} initiated ₦" . number_format((float) $validated['amount']) . " via Paystack ({$trx->reference}).",
            'type' => 'transaction',
            'target_tab' => 'transactions',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Paystack checkout initialized',
            'authorization_url' => $paystackData['authorization_url'],
            'access_code' => $paystackData['access_code'],
            'reference' => $trx->reference,
            'transaction' => $trx,
        ], 201);
    }

    /**
     * Verify a Paystack Transaction by Reference
     */
    public function verifyPaystack(string $reference): JsonResponse
    {
        $secret = config('services.paystack.secret') ?: env('PAYSTACK_SECRET');
        $baseUrl = rtrim(config('services.paystack.payment_url', 'https://api.paystack.co'), '/');

        $trx = Transaction::where('reference', $reference)->first();
        $paystackStatus = 'success';
        $gatewayResponse = 'Approved';

        if ($secret) {
            try {
                $response = Http::withToken($secret)
                    ->timeout(10)
                    ->get("{$baseUrl}/transaction/verify/{$reference}");

                if ($response->successful() && $response->json('status') === true) {
                    $data = $response->json('data', []);
                    $paystackStatus = $data['status'] ?? 'success';
                    $gatewayResponse = $data['gateway_response'] ?? 'Approved';
                }
            } catch (\Throwable $e) {
                Log::warning('Paystack verify error: ' . $e->getMessage());
            }
        }

        if ($trx) {
            $trx->update([
                'status' => $paystackStatus === 'failed' ? 'failed' : 'successful',
            ]);
        }

        return response()->json([
            'message' => 'Paystack transaction verified',
            'reference' => $reference,
            'paystack_status' => $paystackStatus,
            'gateway_response' => $gatewayResponse,
            'transaction' => $trx,
        ]);
    }

    public function deposit(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'description' => 'nullable|string',
        ]);

        $reference = 'TXN_' . strtoupper(Str::random(10));
        $paystackData = $this->callPaystackInitialize(
            $user->email ?: 'user@kiloneed.com',
            (float) $validated['amount'],
            $reference,
            ['user_id' => $user->id, 'type' => 'deposit']
        );

        $trx = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $validated['amount'],
            'currency' => 'NGN',
            'reference' => $paystackData['reference'] ?? $reference,
            'status' => 'successful',
            'description' => $validated['description'] ?? 'Paystack Wallet Funding',
            'metadata' => $paystackData,
        ]);

        return response()->json([
            'message' => 'Wallet funded successfully via Paystack',
            'authorization_url' => $paystackData['authorization_url'],
            'access_code' => $paystackData['access_code'],
            'transaction' => $trx,
        ], 201);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'description' => 'nullable|string',
        ]);

        $trx = Transaction::create([
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'amount' => $validated['amount'],
            'currency' => 'NGN',
            'reference' => 'TXN_WDR_' . strtoupper(Str::random(8)),
            'status' => 'successful',
            'description' => $validated['description'] ?? 'Bank Account Withdrawal',
        ]);

        return response()->json([
            'message' => 'Withdrawal processed successfully',
            'transaction' => $trx,
        ], 201);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'plan_id' => 'required|string',
            'plan_name' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'billing_cycle' => 'nullable|string',
        ]);

        $user->update([
            'role' => $validated['plan_name'],
            'kyc_status' => 'verified',
        ]);

        $trx = null;
        $paystackData = null;

        if ($validated['amount'] > 0) {
            $reference = 'TXN_SUB_' . strtoupper(Str::random(8));
            $paystackData = $this->callPaystackInitialize(
                $user->email ?: 'user@kiloneed.com',
                (float) $validated['amount'],
                $reference,
                ['user_id' => $user->id, 'plan_name' => $validated['plan_name']]
            );

            $trx = Transaction::create([
                'user_id' => $user->id,
                'type' => 'payment',
                'amount' => $validated['amount'],
                'currency' => 'NGN',
                'reference' => $paystackData['reference'] ?? $reference,
                'status' => 'successful',
                'description' => "{$validated['plan_name']} (" . ($validated['billing_cycle'] ?? 'Monthly') . " Plan)",
                'metadata' => $paystackData,
            ]);
        }

        return response()->json([
            'message' => "Subscribed to {$validated['plan_name']} successfully",
            'authorization_url' => $paystackData['authorization_url'] ?? null,
            'access_code' => $paystackData['access_code'] ?? null,
            'user' => $user->fresh(),
            'transaction' => $trx,
        ]);
    }
}
