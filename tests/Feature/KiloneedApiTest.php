<?php

namespace Tests\Feature;

use App\Models\Need;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KiloneedApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret' => 'sk_test_unit_secret']);
        $this->seed();

        $this->user = User::where('email', 'user@kiloneed.com')->firstOrFail();

        $this->admin = new User([
            'name' => 'Test Admin',
            'email' => 'root@kiloneed.test',
            'password' => 'Str0ng-Admin-Pass',
        ]);
        $this->admin->is_admin = true;
        $this->admin->save();
    }

    protected function balance(): float
    {
        return (float) $this->getJson('/api/transactions')->json('wallet_balance');
    }

    // ---------------------------------------------------------------- auth

    public function test_login_requires_the_correct_password(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'user@kiloneed.com', 'password' => 'wrong'])
            ->assertStatus(401);

        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever1'])
            ->assertStatus(401);
        $this->assertDatabaseMissing('users', ['email' => 'nobody@example.com']);

        $res = $this->postJson('/api/auth/login', ['email' => 'user@kiloneed.com', 'password' => 'password123'])
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token']);

        $this->withHeader('Authorization', 'Bearer ' . $res->json('token'))
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'user@kiloneed.com');
    }

    public function test_register_cannot_overwrite_an_existing_account(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Attacker',
            'email' => 'user@kiloneed.com',
            'password' => 'attacker-pass-1',
        ])->assertStatus(422);

        $this->assertTrue(\Hash::check('password123', $this->user->fresh()->password));

        $this->postJson('/api/auth/register', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'a-good-password',
        ])->assertStatus(201)->assertJsonStructure(['token']);
    }

    public function test_protected_routes_reject_anonymous_requests(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
        $this->getJson('/api/transactions')->assertStatus(401);
        $this->postJson('/api/transactions/withdraw', ['amount' => 1000])->assertStatus(401);
        $this->postJson('/api/needs', ['title' => 'x', 'text' => 'y'])->assertStatus(401);
        $this->getJson('/api/conversations')->assertStatus(401);
    }

    // --------------------------------------------------------------- public

    public function test_public_browsing_works_without_a_token(): void
    {
        $this->getJson('/api/needs')->assertStatus(200)->assertJsonStructure(['needs', 'totalCount', 'hasMore']);
        $this->getJson('/api/products')->assertStatus(200);
        $this->getJson('/api/ads')->assertStatus(200);
        $this->getJson('/api/groups')->assertStatus(200);
    }

    // ---------------------------------------------------------------- needs

    public function test_needs_creation_and_interactions_work_when_signed_in(): void
    {
        Sanctum::actingAs($this->user);

        $createRes = $this->postJson('/api/needs', [
            'title' => 'Need 100 Bags of Dangote Cement in Lekki',
            'text' => 'Urgently need 100 bags of cement delivered to Lekki Phase 1 site.',
            'categories' => ['Construction', 'Services'],
            'privacy' => 'public',
            'expiresInHours' => 24,
        ])->assertStatus(201)->assertJsonPath('need.title', 'Need 100 Bags of Dangote Cement in Lekki');

        $needId = $createRes->json('need.id');

        $this->postJson("/api/needs/{$needId}/comments", ['text' => 'We can supply today.'])->assertStatus(201);
        $this->postJson("/api/needs/{$needId}/chats", ['text' => 'What is your price per bag?'])->assertStatus(201);
        $this->postJson("/api/needs/{$needId}/bookmark")->assertStatus(200);
        $this->postJson("/api/needs/{$needId}/repost")->assertStatus(200);
        $this->postJson("/api/needs/{$needId}/click")->assertStatus(200);
    }

    // ------------------------------------------------------------- products

    public function test_products_can_only_be_modified_by_their_owner(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/products', [
            'title' => 'Brand New Starlink Standard Kit v4',
            'price' => 590000,
            'description' => 'Sealed Starlink v4 kit ready for activation anywhere in Nigeria.',
            'category' => 'Electronics',
            'state' => 'Lagos',
            'city' => 'Ikeja',
        ])->assertStatus(201)->assertJsonPath('product.title', 'Brand New Starlink Standard Kit v4');

        $other = User::where('email', 'adaeze@kiloneed.com')->firstOrFail();
        $foreign = Product::where('user_id', '!=', $this->user->id)->firstOrFail();

        $this->putJson("/api/products/{$foreign->id}", ['title' => 'Hijacked'])->assertStatus(403);
        $this->deleteJson("/api/products/{$foreign->id}")->assertStatus(403);
    }

    // --------------------------------------------------------------- wallet

    public function test_deposit_stays_pending_until_paystack_confirms(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc',
                'access_code' => 'abc',
                'reference' => 'REF_DEPOSIT_1',
            ]]),
            'api.paystack.co/transaction/verify/REF_DEPOSIT_1' => Http::response(['status' => true, 'data' => [
                'status' => 'success', 'amount' => 5000000, 'currency' => 'NGN', 'gateway_response' => 'Approved',
            ]]),
        ]);

        Sanctum::actingAs($this->user);
        $base = (float) $this->getJson('/api/transactions')->json('wallet_balance');

        $this->postJson('/api/transactions/deposit', ['amount' => 50000])
            ->assertStatus(201)
            ->assertJsonPath('authorization_url', 'https://checkout.paystack.com/abc')
            ->assertJsonPath('transaction.status', 'pending');

        $this->assertEquals($base, $this->balance());

        $this->getJson('/api/paystack/verify/REF_DEPOSIT_1')
            ->assertStatus(200)
            ->assertJsonPath('paystack_status', 'success');

        $this->assertEquals($base + 50000, $this->balance());

        // Verifying again must not double-credit.
        $this->getJson('/api/paystack/verify/REF_DEPOSIT_1')->assertStatus(200);
        $this->assertEquals($base + 50000, $this->balance());
    }

    public function test_underpaid_transactions_are_rejected(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => [
                'authorization_url' => 'https://checkout.paystack.com/x', 'access_code' => 'x', 'reference' => 'REF_SHORT',
            ]]),
            'api.paystack.co/transaction/verify/REF_SHORT' => Http::response(['status' => true, 'data' => [
                'status' => 'success', 'amount' => 100, 'currency' => 'NGN',
            ]]),
        ]);

        Sanctum::actingAs($this->user);
        $base = (float) $this->getJson('/api/transactions')->json('wallet_balance');
        $this->postJson('/api/transactions/deposit', ['amount' => 50000])->assertStatus(201);
        $this->getJson('/api/paystack/verify/REF_SHORT')->assertJsonPath('paystack_status', 'failed');
        $this->assertEquals($base, $this->balance());
    }

    public function test_withdrawal_requires_sufficient_balance(): void
    {
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/transactions/withdraw', ['amount' => 1000])->assertStatus(422);

        Transaction::create([
            'user_id' => $this->user->id, 'type' => 'deposit', 'amount' => 10000, 'currency' => 'NGN',
            'reference' => 'SEEDED_DEP', 'status' => 'successful',
        ]);

        $this->postJson('/api/transactions/withdraw', ['amount' => 6000])
            ->assertStatus(201)
            ->assertJsonPath('transaction.status', 'pending');

        // The pending withdrawal reserves funds, so the same money cannot be withdrawn twice.
        $this->postJson('/api/transactions/withdraw', ['amount' => 6000])->assertStatus(422);
    }

    public function test_subscription_uses_server_side_prices_and_waits_for_payment(): void
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => [
            'authorization_url' => 'https://checkout.paystack.com/p', 'access_code' => 'p', 'reference' => 'REF_SUB',
        ]])]);

        Sanctum::actingAs($this->user);
        $before = $this->user->role;

        $this->postJson('/api/subscriptions/subscribe', ['plan_id' => 'premium', 'amount' => 1])
            ->assertStatus(201)
            ->assertJsonPath('transaction.amount', 12500)
            ->assertJsonPath('transaction.status', 'pending');

        $this->assertSame($before, $this->user->fresh()->role);
    }

    public function test_paystack_webhook_requires_a_valid_signature(): void
    {
        $this->postJson('/api/paystack/webhook', ['event' => 'charge.success'], ['x-paystack-signature' => 'bad'])
            ->assertStatus(401);

        Transaction::create([
            'user_id' => $this->user->id, 'type' => 'deposit', 'amount' => 2000, 'currency' => 'NGN',
            'reference' => 'WEBHOOK_REF', 'status' => 'pending',
        ]);

        $payload = json_encode(['event' => 'charge.success', 'data' => [
            'reference' => 'WEBHOOK_REF', 'status' => 'success', 'amount' => 200000, 'currency' => 'NGN',
        ]]);
        $signature = hash_hmac('sha512', $payload, 'sk_test_unit_secret');

        $this->call('POST', '/api/paystack/webhook', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);

        $this->assertDatabaseHas('transactions', ['reference' => 'WEBHOOK_REF', 'status' => 'successful']);
    }

    // ---------------------------------------------------------------- admin

    public function test_admin_api_is_locked_down(): void
    {
        $this->getJson('/api/admin/stats')->assertStatus(401);
        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->deleteJson('/api/admin/users/' . $this->user->id)->assertStatus(401);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/admin/stats')->assertStatus(403);
        $this->getJson('/api/admin/transactions')->assertStatus(403);
    }

    public function test_admin_login_requires_an_admin_account(): void
    {
        $this->postJson('/api/admin/auth/login', ['email' => 'user@kiloneed.com', 'password' => 'password123'])
            ->assertStatus(401);
        $this->postJson('/api/admin/auth/login', ['email' => 'root@kiloneed.test', 'password' => 'nope'])
            ->assertStatus(401);

        $token = $this->postJson('/api/admin/auth/login', ['email' => 'root@kiloneed.test', 'password' => 'Str0ng-Admin-Pass'])
            ->assertStatus(200)
            ->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/stats')
            ->assertStatus(200)
            ->assertJsonStructure(['kpis', 'recent_users', 'recent_needs', 'recent_reports']);
    }

    public function test_admin_portal_endpoints_work_for_admins(): void
    {
        Sanctum::actingAs($this->admin);

        foreach (['users', 'needs', 'products', 'reports', 'ads', 'transactions', 'groups', 'settings', 'notifications'] as $path) {
            $this->getJson("/api/admin/{$path}")->assertStatus(200);
        }

        $this->postJson('/api/admin/users', [
            'name' => 'Tunde Bakare', 'email' => 'tunde@kiloneed.com', 'company' => 'Bakare Motors Ltd',
            'location' => 'Lekki, Lagos', 'industry' => 'Vehicles', 'role' => 'Premium Merchant', 'kyc_status' => 'verified',
        ])->assertStatus(201)->assertJsonStructure(['temporary_password']);

        $first = Product::first();
        $this->putJson("/api/admin/products/{$first->id}", ['featured' => true])->assertStatus(200);

        $this->deleteJson('/api/admin/users/' . $this->admin->id)->assertStatus(403);
    }

    public function test_admin_otp_flow_is_enumeration_safe_and_single_use(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        // Unknown / non-admin emails get the same answer and no code.
        $this->postJson('/api/admin/auth/send-otp', ['email' => 'user@kiloneed.com'])
            ->assertStatus(200)->assertJsonMissing(['otp_preview']);
        $this->assertDatabaseCount('admin_otps', 0);

        $otp = $this->postJson('/api/admin/auth/send-otp', ['email' => 'root@kiloneed.test', 'purpose' => 'forgot_password'])
            ->assertStatus(200)->json('otp_preview');
        $this->assertNotEmpty($otp);

        // Wrong code is rejected and a password cannot be reset without a valid OTP.
        $this->postJson('/api/admin/auth/reset-password-otp', [
            'email' => 'root@kiloneed.test', 'otp' => '000000', 'password' => 'Brand-New-Pass1',
        ])->assertStatus(422);
        $this->postJson('/api/admin/auth/reset-password-otp', [
            'email' => 'user@kiloneed.com', 'otp' => '123456', 'password' => 'Brand-New-Pass1',
        ])->assertStatus(422);

        $this->postJson('/api/admin/auth/reset-password-otp', [
            'email' => 'root@kiloneed.test', 'otp' => $otp, 'password' => 'Brand-New-Pass1',
        ])->assertStatus(200);

        // The code cannot be reused.
        $this->postJson('/api/admin/auth/reset-password-otp', [
            'email' => 'root@kiloneed.test', 'otp' => $otp, 'password' => 'Another-Pass-22',
        ])->assertStatus(422);

        $this->postJson('/api/admin/auth/login', ['email' => 'root@kiloneed.test', 'password' => 'Brand-New-Pass1'])
            ->assertStatus(200);
    }

    public function test_admin_can_change_password_with_current_password(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/auth/change-password', [
            'current_password' => 'wrong', 'password' => 'Another-Pass-22', 'password_confirmation' => 'Another-Pass-22',
        ])->assertStatus(422);
    }

    public function test_user_password_reset_by_email_code(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        // Same answer for unknown emails, and no code is issued.
        $this->postJson('/api/auth/forgot-password', ['email' => 'ghost@example.com'])
            ->assertStatus(200)->assertJsonMissing(['otp_preview']);

        // Admins cannot use the public reset flow.
        $this->postJson('/api/auth/forgot-password', ['email' => 'root@kiloneed.test'])
            ->assertStatus(200)->assertJsonMissing(['otp_preview']);

        $otp = $this->postJson('/api/auth/forgot-password', ['email' => 'user@kiloneed.com'])
            ->assertStatus(200)->json('otp_preview');
        $this->assertNotEmpty($otp);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'user@kiloneed.com', 'otp' => '000000', 'password' => 'Brand-New-Pass1',
        ])->assertStatus(422);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'user@kiloneed.com', 'otp' => $otp, 'password' => 'Brand-New-Pass1',
        ])->assertStatus(200);

        $this->postJson('/api/auth/login', ['email' => 'user@kiloneed.com', 'password' => 'Brand-New-Pass1'])
            ->assertStatus(200);
    }
}