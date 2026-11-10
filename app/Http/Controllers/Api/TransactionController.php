<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Subscription plans. Prices are defined server-side so a client can never
     * choose its own price. Amounts are in NGN.
     */
    protected const PLANS = [
        'free' => ['name' => 'Free Starter', 'monthly' => 0, 'annual' => 0],
        'pro' => ['name' => 'Pro Merchant', 'monthly' => 5000, 'annual' => 48000],
        'premium' => ['name' => 'Premium Merchant', 'monthly' => 12500, 'annual' => 120000],
    ];

    protected function paystackSecret(): ?string
    {
        return config('services.paystack.secret') ?: null;
    }

    protected function paystackBaseUrl(): string
    {
        return rtrim(config('services.paystack.payment_url', 'https://api.paystack.co'), '/');
    }

    /**
     * Current spendable wallet balance. Pending withdrawals are reserved so a user
     * cannot withdraw the same funds twice. Card payments (Paystack checkout) never
     * touch the wallet.
     */
    protected function walletBalance(User $user): float
    {
        $transactions = Transaction::where('user_id', $user->id)->get();

        $deposits = $transactions->where('type', 'deposit')->where('status', 'successful')->sum('amount');
        $withdrawals = $transactions->where('type', 'withdrawal')->whereIn('status', ['pending', 'successful'])->sum('amount');
        $walletPayments = $transactions
            ->where('type', 'payment')
            ->where('status', 'successful')
            ->filter(fn ($t) => ($t->metadata['method'] ?? 'wallet') !== 'card')
            ->sum('amount');

        return max(0, (float) ($deposits - $withdrawals - $walletPayments));
    }

    /**
     * Call Paystack "initialize". Returns null when Paystack is unreachable/misconfigured.
     */
    protected function callPaystackInitialize(string $email, float $amountNgn, string $reference, array $metadata = []): ?array
    {
        $secret = $this->paystackSecret();

        if (!$secret) {
            return null;
        }

        try {
            $response = Http::withToken($secret)
                ->timeout(15)
                ->post($this->paystackBaseUrl() . '/transaction/initialize', [
                    'email' => $email,
                    'amount' => (int) round($amountNgn * 100), // kobo
                    'currency' => 'NGN',
                    'reference' => $reference,
                    ...(env('FRONTEND_URL') ? ['callback_url' => rtrim((string) env('FRONTEND_URL'), '/') . '/subscription'] : []),
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

            Log::warning('Paystack initialize rejected: ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning('Paystack initialize error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Create a PENDING transaction and a Paystack checkout session for it.
     * Nothing is credited until Paystack confirms payment (webhook or verify).
     */
    protected function startCheckout(User $user, float $amount, string $type, string $description, array $meta, string $prefix): JsonResponse
    {
        if (!$this->paystackSecret()) {
            return response()->json(['message' => 'Payments are not configured on this server.'], 503);
        }

        $reference = $prefix . strtoupper(Str::random(12));

        $paystack = $this->callPaystackInitialize($user->email, $amount, $reference, array_merge($meta, [
            'user_id' => $user->id,
            'type' => $type,
        ]));

        if (!$paystack || empty($paystack['authorization_url'])) {
            return response()->json(['message' => 'Unable to start the payment right now. Please try again.'], 502);
        }

        $trx = Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => 'NGN',
            'reference' => $paystack['reference'],
            'status' => 'pending',
            'description' => $description,
            'metadata' => array_merge($meta, $paystack, ['method' => 'card']),
        ]);

        AdminNotification::create([
            'title' => 'Paystack Payment Initialized',
            'message' => "{$user->name} started a payment of NGN " . number_format($amount) . " ({$trx->reference}).",
            'type' => 'transaction',
            'target_tab' => 'transactions',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Paystack checkout initialized',
            'authorization_url' => $paystack['authorization_url'],
            'access_code' => $paystack['access_code'],
            'reference' => $trx->reference,
            'transaction' => $trx,
        ], 201);
    }

    /**
     * Apply a Paystack result to a pending transaction exactly once (idempotent).
     * The paid amount and currency must match what we expected.
     */
    protected function settleTransaction(Transaction $trx, array $paystackData): Transaction
    {
        return DB::transaction(function () use ($trx, $paystackData) {
            $locked = Transaction::lockForUpdate()->find($trx->id);

            if (!$locked || $locked->status !== 'pending') {
                return $locked ?? $trx;
            }

            $status = $paystackData['status'] ?? null;

            if ($status === 'success') {
                $paidKobo = (int) ($paystackData['amount'] ?? 0);
                $expectedKobo = (int) round($locked->amount * 100);
                $currency = $paystackData['currency'] ?? $locked->currency;

                if ($paidKobo !== $expectedKobo || $currency !== $locked->currency) {
                    Log::error("Paystack amount mismatch for {$locked->reference}: paid {$paidKobo}, expected {$expectedKobo}");
                    $locked->update(['status' => 'failed']);

                    return $locked;
                }

                $locked->update(['status' => 'successful']);
                $this->applySuccessEffects($locked);
            } elseif (in_array($status, ['failed', 'abandoned', 'reversed'], true)) {
                $locked->update(['status' => 'failed']);
            }

            return $locked;
        });
    }

    protected function applySuccessEffects(Transaction $trx): void
    {
        $user = User::find($trx->user_id);
        $planName = $trx->metadata['plan_name'] ?? null;

        if ($user && $planName) {
            $user->update(['role' => $planName]);
        }

        if ($user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'payment',
                'title' => 'Payment Successful',
                'message' => "Your payment of NGN " . number_format($trx->amount) . " ({$trx->reference}) was received.",
                'description' => $trx->description,
                'detail' => $trx->reference,
                'is_read' => false,
            ]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $transactions = Transaction::where('user_id', $user->id)->latest()->get();

        return response()->json([
            'wallet_balance' => $this->walletBalance($user),
            'currency' => 'NGN',
            'transactions' => $transactions,
        ]);
    }

    /**
     * Initialize a Paystack checkout session (wallet deposit or merchant payment).
     */
    public function initializePaystack(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:10000000',
            'type' => 'nullable|in:deposit,payment',
            'description' => 'nullable|string|max:255',
        ]);

        $type = $validated['type'] ?? 'deposit';
        $description = $validated['description'] ?? ($type === 'deposit' ? 'Paystack Wallet Funding' : 'Paystack Merchant Payment');

        return $this->startCheckout($user, (float) $validated['amount'], $type, $description, [], 'TXN_PSK_');
    }

    /**
     * Verify a Paystack transaction (called by the app after the checkout redirect).
     * The server asks Paystack directly; the client's word is never trusted.
     */
    public function verifyPaystack(Request $request, string $reference): JsonResponse
    {
        $user = $this->resolveUser($request);

        $trx = Transaction::where('reference', $reference)->where('user_id', $user->id)->first();

        if (!$trx) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        $secret = $this->paystackSecret();
        $gatewayResponse = null;

        if ($trx->status === 'pending' && $secret) {
            try {
                $response = Http::withToken($secret)
                    ->timeout(15)
                    ->get($this->paystackBaseUrl() . '/transaction/verify/' . urlencode($reference));

                if ($response->successful() && $response->json('status') === true) {
                    $data = $response->json('data', []);
                    $gatewayResponse = $data['gateway_response'] ?? null;
                    $trx = $this->settleTransaction($trx, $data);
                }
            } catch (\Throwable $e) {
                Log::warning('Paystack verify error: ' . $e->getMessage());
            }
        }

        $trx->refresh();

        return response()->json([
            'message' => 'Paystack transaction checked',
            'reference' => $reference,
            'paystack_status' => match ($trx->status) {
                'successful' => 'success',
                'failed' => 'failed',
                default => 'pending',
            },
            'gateway_response' => $gatewayResponse,
            'transaction' => $trx,
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Paystack webhook. Authenticated by the x-paystack-signature HMAC-SHA512 header.
     */
    public function paystackWebhook(Request $request): JsonResponse
    {
        $secret = $this->paystackSecret();
        $signature = (string) $request->header('x-paystack-signature', '');

        if (!$secret || !hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = $request->input('event');
        $data = (array) $request->input('data', []);

        if (in_array($event, ['charge.success', 'charge.failed'], true) && !empty($data['reference'])) {
            $trx = Transaction::where('reference', $data['reference'])->first();

            if ($trx) {
                if ($event === 'charge.failed') {
                    $data['status'] = 'failed';
                }
                $this->settleTransaction($trx, $data);
            }
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Fund the wallet through Paystack checkout.
     */
    public function deposit(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:10000000',
            'description' => 'nullable|string|max:255',
        ]);

        return $this->startCheckout(
            $user,
            (float) $validated['amount'],
            'deposit',
            $validated['description'] ?? 'Paystack Wallet Funding',
            [],
            'TXN_'
        );
    }

    /**
     * Request a withdrawal. Funds are reserved immediately; the payout stays
     * "pending" until an administrator processes it from the Revenue & Ledger tab.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:10000000',
            'description' => 'nullable|string|max:255',
        ]);

        $amount = (float) $validated['amount'];

        $result = DB::transaction(function () use ($user, $amount, $validated) {
            if ($amount > $this->walletBalance($user)) {
                return null;
            }

            return Transaction::create([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => 'TXN_WDR_' . strtoupper(Str::random(10)),
                'status' => 'pending',
                'description' => $validated['description'] ?? 'Bank Account Withdrawal',
            ]);
        });

        if (!$result) {
            return response()->json(['message' => 'Insufficient wallet balance.'], 422);
        }

        AdminNotification::create([
            'title' => 'Withdrawal Requested',
            'message' => "{$user->name} requested a withdrawal of NGN " . number_format($amount) . " ({$result->reference}).",
            'type' => 'transaction',
            'target_tab' => 'transactions',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Withdrawal request submitted and awaiting processing',
            'transaction' => $result,
        ], 201);
    }

    /**
     * Subscribe to a plan. Paid plans are only activated after Paystack confirms payment.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'plan_id' => 'required|string|in:' . implode(',', array_keys(self::PLANS)),
            'billing_cycle' => 'nullable|string|in:monthly,annual',
        ]);

        $plan = self::PLANS[$validated['plan_id']];
        $cycle = $validated['billing_cycle'] ?? 'monthly';
        $amount = (float) $plan[$cycle];

        if ($amount <= 0) {
            $user->update(['role' => $plan['name']]);

            return response()->json([
                'message' => "Switched to {$plan['name']}",
                'authorization_url' => null,
                'access_code' => null,
                'user' => $user->fresh(),
                'transaction' => null,
            ]);
        }

        return $this->startCheckout(
            $user,
            $amount,
            'payment',
            "{$plan['name']} (" . ucfirst($cycle) . ' Plan)',
            ['plan_id' => $validated['plan_id'], 'plan_name' => $plan['name'], 'billing_cycle' => $cycle],
            'TXN_SUB_'
        );
    }
}
