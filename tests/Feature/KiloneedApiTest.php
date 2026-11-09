<?php

namespace Tests\Feature;

use App\Models\Need;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KiloneedApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_auth_login_and_profile_endpoints_work(): void
    {
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'user@kiloneed.com',
            'password' => 'password123',
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token']);

        $token = $loginRes->json('token');

        $meRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $meRes->assertStatus(200)
            ->assertJsonPath('user.email', 'user@kiloneed.com');
    }

    public function test_needs_feed_and_creation_and_interactions_work(): void
    {
        // 1. Fetch Needs Feed
        $feedRes = $this->getJson('/api/needs');
        $feedRes->assertStatus(200)
            ->assertJsonStructure(['needs', 'totalCount', 'hasMore']);

        // 2. Create a new Need
        $createRes = $this->postJson('/api/needs', [
            'title' => 'Need 100 Bags of Dangote Cement in Lekki',
            'text' => 'Urgently need 100 bags of cement delivered to Lekki Phase 1 site.',
            'categories' => ['Construction', 'Services'],
            'privacy' => 'public',
            'expiresInHours' => 24,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('need.title', 'Need 100 Bags of Dangote Cement in Lekki');

        $needId = $createRes->json('need.id');

        // 3. Add a Comment
        $commentRes = $this->postJson("/api/needs/{$needId}/comments", [
            'text' => 'We can supply today from Victoria Island depot.',
        ]);
        $commentRes->assertStatus(201);

        // 4. Add an In-Need Chat Response
        $chatRes = $this->postJson("/api/needs/{$needId}/chats", [
            'text' => 'Hello, what is your delivery price per bag?',
        ]);
        $chatRes->assertStatus(201);

        // 5. Toggle Bookmark & Repost
        $this->postJson("/api/needs/{$needId}/bookmark")->assertStatus(200);
        $this->postJson("/api/needs/{$needId}/repost")->assertStatus(200);
        $this->postJson("/api/needs/{$needId}/click")->assertStatus(200);
    }

    public function test_marketplace_products_endpoints_work(): void
    {
        $listRes = $this->getJson('/api/products');
        $listRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(4, count($listRes->json()));

        $createRes = $this->postJson('/api/products', [
            'title' => 'Brand New Starlink Standard Kit v4',
            'price' => 590000,
            'description' => 'Sealed Starlink v4 kit ready for activation anywhere in Nigeria.',
            'category' => 'Electronics',
            'state' => 'Lagos',
            'city' => 'Ikeja',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('product.title', 'Brand New Starlink Standard Kit v4');
    }

    public function test_wallet_ads_groups_and_reports_work(): void
    {
        $this->getJson('/api/transactions')
            ->assertStatus(200)
            ->assertJsonStructure(['wallet_balance', 'currency', 'transactions']);

        $this->postJson('/api/transactions/deposit', [
            'amount' => 50000,
            'description' => 'Test Deposit',
        ])->assertStatus(201);

        $this->postJson('/api/subscriptions/subscribe', [
            'plan_id' => 'pro',
            'plan_name' => 'Pro Merchant',
            'amount' => 5000,
            'billing_cycle' => 'monthly',
        ])->assertStatus(200);

        $this->getJson('/api/ads')->assertStatus(200);
        $this->getJson('/api/groups')->assertStatus(200);
        $this->getJson('/api/connections')->assertStatus(200);
        $this->getJson('/api/notifications')->assertStatus(200);

        $this->postJson('/api/reports', [
            'target_type' => 'need',
            'target_id' => 1,
            'reason' => 'Spam or duplicate need',
        ])->assertStatus(201);
    }

    public function test_admin_portal_endpoints_work(): void
    {
        $this->getJson('/api/admin/stats')
            ->assertStatus(200)
            ->assertJsonStructure(['kpis', 'recent_users', 'recent_needs', 'recent_reports']);

        $this->getJson('/api/admin/users')->assertStatus(200);
        $this->getJson('/api/admin/needs')->assertStatus(200);
        $this->getJson('/api/admin/products')->assertStatus(200);
        $this->getJson('/api/admin/reports')->assertStatus(200);
        $this->getJson('/api/admin/ads')->assertStatus(200);
        $this->getJson('/api/admin/transactions')->assertStatus(200);
        $this->getJson('/api/admin/groups')->assertStatus(200);
        $this->getJson('/api/admin/settings')->assertStatus(200);

        // Create Merchant from Admin
        $newMerchant = $this->postJson('/api/admin/users', [
            'name' => 'Tunde Bakare',
            'email' => 'tunde@kiloneed.com',
            'company' => 'Bakare Motors Ltd',
            'location' => 'Lekki, Lagos',
            'industry' => 'Vehicles',
            'role' => 'Premium Merchant',
            'kyc_status' => 'verified',
        ]);
        $newMerchant->assertStatus(201);

        // Update Product Featured status from Admin
        $firstProduct = Product::first();
        $this->putJson("/api/admin/products/{$firstProduct->id}", [
            'featured' => true,
        ])->assertStatus(200);
    }
}

