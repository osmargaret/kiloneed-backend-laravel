<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdminNotification;
use App\Models\AdminOtp;
use App\Models\Group;
use App\Models\GroupDisbursement;
use App\Models\Need;
use App\Models\Notification;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProfileChangeRequest;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Ensure newly added administrative tables are seeded with initial records if empty
     */
    protected function ensureAdminTablesSeeded(): void
    {
        if (app()->environment('production') && !env('SEED_DEMO')) {
            return;
        }

        if (ProfileChangeRequest::count() === 0) {
            $u1 = User::first();
            $u2 = User::skip(1)->first() ?? $u1;
            ProfileChangeRequest::insert([
                [
                    'user_id' => $u1?->id ?? 1,
                    'user_name' => $u1?->name ?? 'John Doe',
                    'user_email' => $u1?->email ?? 'user@kiloneed.com',
                    'field' => 'Location',
                    'old_value' => 'Ikeja, Lagos',
                    'new_value' => 'Victoria Island, Lagos',
                    'days_remaining' => 15,
                    'status' => 'pending',
                    'requested_at' => Carbon::now()->subDays(15),
                    'created_at' => Carbon::now()->subDays(15),
                    'updated_at' => Carbon::now()->subDays(15),
                ],
                [
                    'user_id' => $u1?->id ?? 1,
                    'user_name' => $u1?->name ?? 'John Doe',
                    'user_email' => $u1?->email ?? 'user@kiloneed.com',
                    'field' => 'Category',
                    'old_value' => 'Electronics',
                    'new_value' => 'Enterprise IT & Telecoms',
                    'days_remaining' => 22,
                    'status' => 'pending',
                    'requested_at' => Carbon::now()->subDays(8),
                    'created_at' => Carbon::now()->subDays(8),
                    'updated_at' => Carbon::now()->subDays(8),
                ],
                [
                    'user_id' => $u2?->id ?? 1,
                    'user_name' => $u2?->name ?? 'David Okonkwo',
                    'user_email' => $u2?->email ?? 'david@kiloneed.com',
                    'field' => 'Profile Name',
                    'old_value' => $u2?->name ?? 'David Okonkwo',
                    'new_value' => 'Okonkwo Global Commodities Official',
                    'days_remaining' => 8,
                    'status' => 'pending',
                    'requested_at' => Carbon::now()->subDays(22),
                    'created_at' => Carbon::now()->subDays(22),
                    'updated_at' => Carbon::now()->subDays(22),
                ],
            ]);
        }

        if (GroupDisbursement::count() === 0) {
            $g1 = Group::first();
            $g2 = Group::skip(1)->first() ?? $g1;
            GroupDisbursement::create([
                'group_id' => $g1?->id,
                'group_name' => $g1?->name ?? 'PH Real Estate Circle',
                'requested_by' => 'Adebayo Ogunlesi (Group Admin)',
                'amount' => 500000,
                'purpose' => 'Joint Land Survey & Escrow Deposit at GRA Phase 3',
                'signatures_count' => 3,
                'required_signatures' => 3,
                'signers' => ['Adebayo Ogunlesi', 'Fatima Bello', 'Chinedu Okoro'],
                'status' => 'pending_release',
            ]);
            GroupDisbursement::create([
                'group_id' => $g2?->id,
                'group_name' => $g2?->name ?? 'Bulk Importers Network',
                'requested_by' => 'Emeka Nwosu (Group Admin)',
                'amount' => 1250000,
                'purpose' => 'Shared 40ft Container Apapa Port Clearing Levy',
                'signatures_count' => 3,
                'required_signatures' => 3,
                'signers' => ['Emeka Nwosu', 'Adaeze Okafor', 'Bola Tinubu Jr'],
                'status' => 'released',
            ]);
        }

        if (AdminNotification::count() === 0) {
            AdminNotification::insert([
                [
                    'title' => 'New KYC Verification Dossier Submitted',
                    'message' => 'Kemi Adeosun (Savory Affair Catering) uploaded CAC Certificate RC-1928374 & NIN for Pro Merchant review.',
                    'type' => 'kyc',
                    'target_tab' => 'users',
                    'is_read' => false,
                    'created_at' => Carbon::now()->subMinutes(10),
                    'updated_at' => Carbon::now()->subMinutes(10),
                ],
                [
                    'title' => 'Trust & Safety Report #2 Flagged',
                    'message' => 'Halima Sani reported User #7 for requesting advance deposit prior to CAC badge verification.',
                    'type' => 'report',
                    'target_tab' => 'reports',
                    'is_read' => false,
                    'created_at' => Carbon::now()->subMinutes(25),
                    'updated_at' => Carbon::now()->subMinutes(25),
                ],
                [
                    'title' => 'Special Group 3/3 Multi-Sig Disbursement Ready',
                    'message' => 'PH Real Estate Circle has collected 3/3 member signatures for a ₦500,000 treasury release.',
                    'type' => 'group',
                    'target_tab' => 'groups',
                    'is_read' => false,
                    'created_at' => Carbon::now()->subHour(),
                    'updated_at' => Carbon::now()->subHour(),
                ],
                [
                    'title' => 'Paystack Wallet Deposit Confirmed',
                    'message' => 'John Doe funded wallet with ₦150,000 (Ref: TXN_PAYSTACK_94821A).',
                    'type' => 'transaction',
                    'target_tab' => 'transactions',
                    'is_read' => true,
                    'created_at' => Carbon::now()->subHours(2),
                    'updated_at' => Carbon::now()->subHours(2),
                ],
            ]);
        }
    }

    /**
     * Get Executive Dashboard KPIs, Charts, and Recent Activity
     */
    public function stats(): JsonResponse
    {
        $this->ensureAdminTablesSeeded();

        $totalUsers = User::count();
        $verifiedUsers = User::where('kyc_status', 'verified')->count();
        $pendingKyc = User::where('kyc_status', 'pending')->count();

        $totalNeeds = Need::count();
        $activeNeeds = Need::where('status', 'active')
            ->where('expires_at', '>', Carbon::now())
            ->count();

        $totalProducts = Product::count();

        $pendingReports = Report::where('status', 'pending')->count();
        $totalReports = Report::count();

        $totalRevenue = Transaction::where('type', 'payment')
            ->where('status', 'successful')
            ->sum('amount');

        $totalDeposits = Transaction::where('type', 'deposit')
            ->where('status', 'successful')
            ->sum('amount');

        $activeAds = Ad::where('status', 'active')->count();
        $totalGroups = Group::count();

        $recentUsers = User::latest()->limit(5)->get();
        $recentNeeds = Need::with('user')->latest()->limit(5)->get();
        $recentReports = Report::with('reporter')->latest()->limit(5)->get();

        return response()->json([
            'kpis' => [
                'total_users' => $totalUsers,
                'verified_users' => $verifiedUsers,
                'pending_kyc' => $pendingKyc,
                'total_needs' => $totalNeeds,
                'active_needs' => $activeNeeds,
                'total_products' => $totalProducts,
                'pending_reports' => $pendingReports,
                'total_reports' => $totalReports,
                'total_revenue' => (float) $totalRevenue,
                'total_deposits' => (float) $totalDeposits,
                'active_ads' => $activeAds,
                'total_groups' => $totalGroups,
            ],
            'recent_users' => $recentUsers,
            'recent_needs' => $recentNeeds,
            'recent_reports' => $recentReports,
        ]);
    }

    /**
     * List and filter all platform users
     */
    public function users(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $kyc = $request->query('kyc_status');
        $role = $request->query('role');

        $query = User::withCount(['needs', 'comments', 'bookmarks'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($kyc && $kyc !== 'all') {
            $query->where('kyc_status', $kyc);
        }

        if ($role && $role !== 'all') {
            $query->where('role', $role);
        }

        return response()->json($query->get());
    }

    /**
     * Create a new verified merchant / user from Admin Portal
     */
    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'role' => 'nullable|string|max:100',
            'kyc_status' => 'nullable|string|in:verified,pending,unverified,suspended',
        ]);

        $temporaryPassword = Str::random(14);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? '+234 800 000 0000',
            'password' => $temporaryPassword,
            'company' => $validated['company'] ?? 'Verified Partner',
            'location' => $validated['location'] ?? 'Ikeja, Lagos',
            'industry' => $validated['industry'] ?? 'Electronics',
            'role' => $validated['role'] ?? 'Pro Merchant',
            'kyc_status' => $validated['kyc_status'] ?? 'verified',
            'rating' => 5.0,
            'review_count' => 1,
        ]);

        return response()->json([
            'message' => 'Merchant provisioned successfully',
            'temporary_password' => $temporaryPassword,
            'user' => $user,
        ], 201);
    }

    /**
     * Update a user's KYC status, profile, or role
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'kyc_status' => 'sometimes|string|in:verified,pending,unverified,suspended',
            'role' => 'sometimes|string|max:100',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'industry' => 'sometimes|string|max:100',
            'location' => 'sometimes|string|max:255',
            'company' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string',
            'avatar' => 'sometimes|nullable|string',
        ]);

        $previousKyc = $user->kyc_status;
        $user->update($validated);

        if (isset($validated['kyc_status']) && $validated['kyc_status'] !== $previousKyc) {
            if ($validated['kyc_status'] === 'verified') {
                $msg = 'Congratulations! Your merchant KYC dossier has been verified by Kiloneed Admin. Your Verified Badge is now active across Needs and Marketplace.';
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'kyc_verified',
                    'title' => 'KYC Identity & CAC Verification Approved',
                    'message' => $msg,
                    'description' => $msg,
                    'detail' => 'Verified Badge Activated',
                    'is_read' => false,
                ]);
            }
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Delete a user account
     */
    public function deleteUser(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($user->is_admin) {
            return response()->json(['message' => 'Administrator accounts cannot be deleted here.'], 403);
        }
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * List all needs for admin moderation
     */
    public function needs(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Need::with(['user', 'media', 'comments'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('text', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json($query->get());
    }

    /**
     * Create an official Need broadcast from Admin Portal
     */
    public function createNeed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'text' => 'required|string',
            'location' => 'nullable|string|max:255',
            'radius' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'categories' => 'nullable|array',
            'privacy' => 'nullable|in:public,connections,groups,only_me',
        ]);

        $owner = User::where('role', 'admin')->first() ?? User::first();
        $categories = $validated['categories'] ?? [$validated['category'] ?? 'Electronics'];

        $need = Need::create([
            'user_id' => $owner?->id ?? 1,
            'title' => $validated['title'],
            'text' => $validated['text'],
            'location' => $validated['location'] ?? 'Victoria Island, Lagos',
            'latitude' => 6.4281,
            'longitude' => 3.4219,
            'radius' => $validated['radius'] ?? '50km',
            'categories' => $categories,
            'privacy' => $validated['privacy'] ?? 'public',
            'expires_at' => Carbon::now()->addHours(24),
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Official Need broadcasted by Administrator',
            'need' => $need->load(['user', 'media']),
        ], 201);
    }

    /**
     * Update need status (active, fulfilled, expired, archived)
     */
    public function updateNeedStatus(Request $request, int $id): JsonResponse
    {
        $need = Need::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:active,fulfilled,expired,archived',
        ]);

        $need->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Need status updated',
            'need' => $need->fresh(['user', 'media']),
        ]);
    }

    /**
     * Force delete a need
     */
    public function deleteNeed(int $id): JsonResponse
    {
        $need = Need::findOrFail($id);
        $need->delete();

        return response()->json([
            'message' => 'Need removed by administrator',
        ]);
    }

    /**
     * List all marketplace products for admin moderation
     */
    public function products(): JsonResponse
    {
        $products = Product::with('user')->latest()->get();
        return response()->json($products);
    }

    /**
     * Create a Marketplace Product from Admin Portal
     */
    public function createProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'image' => 'nullable|string',
            'featured' => 'nullable|boolean',
        ]);

        $owner = User::first();

        $product = Product::create([
            'user_id' => $owner?->id ?? 1,
            'title' => $validated['title'],
            'price' => $validated['price'],
            'description' => $validated['description'],
            'seller_type' => 'Business',
            'state' => $validated['state'] ?? 'Lagos',
            'city' => $validated['city'] ?? 'Ikeja',
            'category' => $validated['category'] ?? 'Electronics',
            'subcategory' => 'General',
            'image' => $validated['image'] ?? 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500&h=500&fit=crop',
            'featured' => $validated['featured'] ?? true,
            'condition' => $validated['condition'] ?? 'Brand New',
            'availability' => 'Available',
            'listing_type' => 'For Sale',
            'negotiable' => true,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Marketplace product listed by administrator',
            'product' => $product->load('user'),
        ], 201);
    }

    /**
     * Update a marketplace product (feature/unfeature, status, etc.)
     */
    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $product->update($request->only([
            'title',
            'price',
            'featured',
            'status',
            'availability',
            'condition',
        ]));

        return response()->json([
            'message' => 'Product updated by administrator',
            'product' => $product->fresh('user'),
        ]);
    }

    /**
     * Delete a marketplace product
     */
    public function deleteProduct(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Product removed by administrator',
        ]);
    }

    /**
     * List all moderation reports
     */
    public function reports(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = Report::with('reporter')->latest();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $reports = $query->get()->map(function ($report) {
            if ($report->target_type === 'need') {
                $report->target_entity = Need::with('user')->find($report->target_id);
            } elseif ($report->target_type === 'user') {
                $report->target_entity = User::find($report->target_id);
            }
            return $report;
        });

        return response()->json($reports);
    }

    /**
     * Resolve or dismiss a report
     */
    public function updateReport(Request $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,reviewed,dismissed',
            'delete_target' => 'nullable|boolean',
        ]);

        $report->update(['status' => $validated['status']]);

        if (!empty($validated['delete_target']) && $report->target_type === 'need') {
            Need::where('id', $report->target_id)->delete();
        }

        return response()->json([
            'message' => 'Report updated successfully',
            'report' => $report->fresh('reporter'),
        ]);
    }

    /**
     * List all sponsored ad campaigns
     */
    public function ads(): JsonResponse
    {
        $ads = Ad::with('user')->latest()->get();
        return response()->json($ads);
    }

    /**
     * Create a sponsored ad campaign from Admin Portal
     */
    public function createAd(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'budget' => 'required|numeric|min:0',
            'ad_type' => 'nullable|string|max:100',
            'placement' => 'nullable|string|max:100',
            'media_url' => 'nullable|string',
            'target_url' => 'nullable|string',
        ]);

        $owner = User::first();

        $ad = Ad::create([
            'user_id' => $owner?->id ?? 1,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'ad_type' => $validated['ad_type'] ?? 'Image & Text',
            'placement' => $validated['placement'] ?? 'Marketplace Featured',
            'media_url' => $validated['media_url'] ?? 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&auto=format&fit=crop&q=80',
            'target_url' => $validated['target_url'] ?? 'https://kiloneed.com/marketplace',
            'budget' => $validated['budget'],
            'spent' => 0,
            'impressions' => 100,
            'clicks' => 5,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Sponsored ad campaign created',
            'ad' => $ad->load('user'),
        ], 201);
    }

    /**
     * Update ad status
     */
    public function updateAd(Request $request, int $id): JsonResponse
    {
        $ad = Ad::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:draft,active,paused,completed',
        ]);

        $ad->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Ad status updated',
            'ad' => $ad->fresh('user'),
        ]);
    }

    /**
     * Delete a sponsored ad campaign
     */
    public function deleteAd(int $id): JsonResponse
    {
        $ad = Ad::findOrFail($id);
        $ad->delete();

        return response()->json([
            'message' => 'Ad campaign deleted successfully',
        ]);
    }

    /**
     * List all platform transactions
     */
    public function transactions(): JsonResponse
    {
        $transactions = Transaction::with('user')->latest()->get();
        return response()->json($transactions);
    }

    /**
     * Create a transaction ledger record from Admin Portal
     */
    public function createTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:deposit,withdrawal,payment,reward',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'status' => 'nullable|in:pending,successful,failed',
            'user_id' => 'nullable|integer',
        ]);

        $user = isset($validated['user_id'])
            ? User::find($validated['user_id'])
            : User::first();

        $tx = Transaction::create([
            'user_id' => $user?->id ?? 1,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'currency' => 'NGN',
            'reference' => 'TXN_ADM_' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8)),
            'status' => $validated['status'] ?? 'successful',
            'description' => $validated['description'],
        ]);

        return response()->json([
            'message' => 'Ledger transaction recorded',
            'transaction' => $tx->load('user'),
        ], 201);
    }

    /**
     * Update a transaction's status (pending, successful, failed)
     */
    public function updateTransaction(Request $request, int $id): JsonResponse
    {
        $tx = Transaction::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,successful,failed',
        ]);

        $tx->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Transaction status updated',
            'transaction' => $tx->fresh('user'),
        ]);
    }

    /**
     * List all trade groups
     */
    public function groups(): JsonResponse
    {
        $groups = Group::with('owner')->withCount('members')->latest()->get();
        return response()->json($groups);
    }

    /**
     * Create a trade group from Admin Portal
     */
    public function createGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'is_private' => 'nullable|boolean',
            'group_type' => 'nullable|string|in:open,closed,special',
            'monthly_dues' => 'nullable|numeric|min:0',
        ]);

        $owner = User::first();
        $gType = $validated['group_type'] ?? (!empty($validated['is_private']) ? 'closed' : 'open');

        $group = Group::create([
            'owner_id' => $owner?->id ?? 1,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'category' => $validated['category'] ?? 'General',
            'is_private' => $gType !== 'open',
            'group_type' => $gType,
            'monthly_dues' => $gType === 'special' ? ($validated['monthly_dues'] ?? 5000) : 0,
            'treasury_balance' => 0,
            'max_members' => 1000,
        ]);

        $group->loadCount('members');

        return response()->json([
            'message' => 'Trade group created',
            'group' => $group,
        ], 201);
    }

    /**
     * Delete a group
     */
    public function deleteGroup(int $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully',
        ]);
    }

    /**
     * Get Platform Deployment Configuration from platform_settings table
     */
    public function getSettings(): JsonResponse
    {
        $default = [
            'countryName' => 'Nigeria',
            'countryCode' => 'NG',
            'currencyCode' => 'NGN',
            'currencySymbol' => '₦',
            'defaultGeofenceKm' => 50,
            'premiumGeofenceKm' => 200,
            'defaultNeedExpiryHours' => 24,
            'profileChangeReviewDays' => 30,
            'maxGroupMembers' => 1000,
            'requireKycForPostingNeeds' => false,
            'requireKycForMarketplace' => true,
            'plusPlanMonthlyNgn' => 2500,
            'proPlanMonthlyNgn' => 6000,
            'premiumPlanMonthlyNgn' => 15000,
            'maintenanceMode' => false,
        ];

        $record = PlatformSetting::where('key', 'tenant_config')->first();
        if (!$record) {
            $record = PlatformSetting::create([
                'key' => 'tenant_config',
                'value' => Cache::get('kiloneed_platform_settings', $default),
            ]);
        }

        return response()->json($record->value);
    }

    /**
     * Save Platform Deployment Configuration to platform_settings table
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $settings = $request->all();
        PlatformSetting::updateOrCreate(
            ['key' => 'tenant_config'],
            ['value' => $settings]
        );
        Cache::forever('kiloneed_platform_settings', $settings);

        return response()->json([
            'message' => 'Platform settings saved to database',
            'settings' => $settings,
        ]);
    }

    /**
     * Get & Update Administrator Profile (persisted in platform_settings & users table)
     */
    public function getAdminProfile(): JsonResponse
    {
        $default = [
            'name' => 'Super Admin',
            'email' => 'admin@kiloneed.com',
            'phone' => '+234 800 545 6633',
            'role' => 'Chief Executive Administrator',
            'department' => 'Platform Operations & Trust',
            'location' => 'Victoria Island, Lagos HQ',
            'bio' => 'Executive administrator overseeing KYC compliance, geofenced needs moderation, and special trade syndicates across Nigeria.',
            'avatar' => '',
            'twoFactorEnabled' => true,
            'emailAlerts' => true,
            'pushAlerts' => true,
            'kycInstantAlert' => true,
        ];

        $record = PlatformSetting::where('key', 'admin_profile')->first();
        if (!$record) {
            $record = PlatformSetting::create([
                'key' => 'admin_profile',
                'value' => $default,
            ]);
        }

        return response()->json($record->value);
    }

    public function updateAdminProfile(Request $request): JsonResponse
    {
        $profile = $request->all();
        PlatformSetting::updateOrCreate(
            ['key' => 'admin_profile'],
            ['value' => $profile]
        );

        $adminUser = User::where('role', 'admin')->first();
        if ($adminUser && !empty($profile['name'])) {
            $adminUser->update([
                'name' => $profile['name'],
                'email' => $profile['email'] ?? $adminUser->email,
                'phone' => $profile['phone'] ?? $adminUser->phone,
                'location' => $profile['location'] ?? $adminUser->location,
                'bio' => $profile['bio'] ?? $adminUser->bio,
                'avatar' => $profile['avatar'] ?? $adminUser->avatar,
            ]);
        }

        return response()->json([
            'message' => 'Administrator profile updated in database',
            'profile' => $profile,
        ]);
    }

    /**
     * List 30-Day Profile Change Requests from profile_change_requests table
     */
    public function profileChanges(): JsonResponse
    {
        $this->ensureAdminTablesSeeded();
        return response()->json(ProfileChangeRequest::latest()->get());
    }

    /**
     * Approve Early (Override 30-day lock) or Reject a Profile Change Request
     */
    public function updateProfileChange(Request $request, int $id): JsonResponse
    {
        $this->ensureAdminTablesSeeded();

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $target = ProfileChangeRequest::findOrFail($id);
        $target->update([
            'status' => $validated['status'],
            'days_remaining' => 0,
            'reviewed_at' => Carbon::now(),
        ]);

        // Apply change to User model if approved and create notification
        if ($validated['status'] === 'approved') {
            $user = User::find($target->user_id);
            if ($user) {
                if ($target->field === 'Location') {
                    $user->update(['location' => $target->new_value]);
                } elseif ($target->field === 'Category') {
                    $user->update(['industry' => $target->new_value]);
                } elseif ($target->field === 'Profile Name') {
                    $user->update(['name' => $target->new_value]);
                }

                $msg = "Your request to update your {$target->field} from '{$target->old_value}' to '{$target->new_value}' has been approved.";
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'profile_approval',
                    'title' => 'Profile Change Approved by Admin',
                    'message' => $msg,
                    'description' => $msg,
                    'detail' => "{$target->field}: {$target->new_value}",
                    'is_read' => false,
                ]);
            }
        }

        return response()->json([
            'message' => "Profile change request {$validated['status']}",
            'profile_changes' => ProfileChangeRequest::latest()->get(),
        ]);
    }

    /**
     * List Special Group Multi-Signature Treasury Disbursements from group_disbursements table
     */
    public function groupDisbursements(): JsonResponse
    {
        $this->ensureAdminTablesSeeded();
        return response()->json(GroupDisbursement::latest()->get());
    }

    /**
     * Create a new Special Group Multi-Signature Treasury Disbursement Request
     */
    public function createGroupDisbursement(Request $request): JsonResponse
    {
        $this->ensureAdminTablesSeeded();

        $validated = $request->validate([
            'group_id' => 'nullable|integer',
            'group_name' => 'required|string|max:255',
            'requested_by' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string',
        ]);

        $record = GroupDisbursement::create([
            'group_id' => $validated['group_id'] ?? null,
            'group_name' => $validated['group_name'],
            'requested_by' => $validated['requested_by'] ?? 'Group Admin',
            'amount' => $validated['amount'],
            'purpose' => $validated['purpose'],
            'signatures_count' => 3,
            'required_signatures' => 3,
            'signers' => ['Adebayo Ogunlesi', 'Fatima Bello', 'Chinedu Okoro'],
            'status' => 'pending_release',
        ]);

        AdminNotification::create([
            'title' => "New Multi-Sig Disbursement: {$validated['group_name']}",
            'message' => "Withdrawal of ₦" . number_format((float) $validated['amount']) . " requested for '{$validated['purpose']}'.",
            'type' => 'group',
            'target_tab' => 'groups',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Treasury disbursement submitted for 3-signature release',
            'disbursement' => $record,
        ], 201);
    }

    /**
     * Release or Freeze a Special Group Multi-Signature Treasury Disbursement
     */
    public function updateGroupDisbursement(Request $request, int $id): JsonResponse
    {
        $this->ensureAdminTablesSeeded();

        $validated = $request->validate([
            'status' => 'required|in:released,frozen',
        ]);

        $disbursement = GroupDisbursement::findOrFail($id);
        $disbursement->update(['status' => $validated['status']]);

        return response()->json([
            'message' => "Group treasury disbursement {$validated['status']}",
            'disbursements' => GroupDisbursement::latest()->get(),
        ]);
    }

    /**
     * Admin Operational Notifications CRUD (admin_notifications table)
     */
    public function adminNotifications(): JsonResponse
    {
        $this->ensureAdminTablesSeeded();
        return response()->json(AdminNotification::latest()->get());
    }

    public function markAdminNotificationRead(int $id): JsonResponse
    {
        $notif = AdminNotification::findOrFail($id);
        $notif->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marked as read',
            'notifications' => AdminNotification::latest()->get(),
        ]);
    }

    public function markAllAdminNotificationsRead(): JsonResponse
    {
        AdminNotification::query()->update(['is_read' => true]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'notifications' => AdminNotification::latest()->get(),
        ]);
    }

    public function deleteAdminNotification(int $id): JsonResponse
    {
        AdminNotification::where('id', $id)->delete();

        return response()->json([
            'message' => 'Notification deleted',
            'notifications' => AdminNotification::latest()->get(),
        ]);
    }

    /**
     * Broadcast a System Notification to all platform users (appears in Notifications.tsx)
     */
    public function broadcastNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|max:50',
        ]);

        $users = User::all();
        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => $validated['type'] ?? 'system',
                'title' => $validated['title'],
                'message' => $validated['message'],
                'description' => $validated['message'],
                'detail' => 'Official Kiloneed Broadcast',
                'is_read' => false,
            ]);
        }

        AdminNotification::create([
            'title' => 'System Broadcast Dispatched',
            'message' => "Delivered '{$validated['title']}' to {$users->count()} users.",
            'type' => 'system',
            'target_tab' => 'notifications',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => "Broadcast delivered to {$users->count()} users",
            'recipients_count' => $users->count(),
        ], 201);
    }

    /**
     * Admin sign-in with email + password. Issues a Sanctum token that is only
     * valid for users flagged as administrators.
     */
    public function adminLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::whereRaw('lower(email) = ?', [strtolower(trim($validated['email']))])->first();

        if (!$user || !$user->is_admin || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid administrator credentials.'], 401);
        }

        return $this->adminSession($user, 'Signed in to Kiloneed Command Center.');
    }

    public function adminMe(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user('sanctum')]);
    }

    public function adminLogout(Request $request): JsonResponse
    {
        $request->user('sanctum')?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out successfully.']);
    }

    /**
     * Change the signed-in administrator's password (requires the current password).
     */
    public function changeAdminPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|max:128|confirmed',
        ]);

        $user = $request->user('sanctum');

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->forceFill(['password' => $validated['password']])->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    /**
     * Send a 6-digit email OTP to a registered administrator.
     * The response is intentionally identical whether or not the email belongs to an admin
     * so that the endpoint cannot be used to discover administrator accounts.
     */
    public function sendAdminOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'purpose' => 'nullable|string|in:forgot_password,otp_login,security_reset',
        ]);

        $email = strtolower(trim($validated['email']));
        $purpose = $validated['purpose'] ?? 'forgot_password';
        $otp = null;

        $admin = User::whereRaw('lower(email) = ?', [$email])->where('is_admin', true)->first();

        if ($admin) {
            $otp = (string) random_int(100000, 999999);

            AdminOtp::where('email', $email)->whereNull('used_at')->update(['used_at' => Carbon::now()]);
            AdminOtp::create([
                'email' => $email,
                'otp' => $otp,
                'purpose' => $purpose,
                'expires_at' => Carbon::now()->addMinutes(15),
            ]);
            Cache::forget("kiloneed_admin_otp_attempts_{$email}");

            try {
                Mail::raw(
                    "Your Kiloneed Executive Admin verification OTP code is: {$otp}\n\nThis code expires in 15 minutes. If you did not request it, ignore this email.",
                    function ($message) use ($email) {
                        $message->to($email)->subject('Kiloneed Admin Security OTP Code');
                    }
                );
            } catch (\Throwable $e) {
                Log::error('Admin OTP email delivery failed: ' . $e->getMessage());
            }

            if (config('mail.default') === 'log') {
                Log::info("Kiloneed Admin OTP for {$email}: {$otp}");
            }
        }

        $response = [
            'message' => 'If that email belongs to an administrator, a 6-digit verification code has been sent.',
            'expires_in_minutes' => 15,
        ];

        // Convenience for local development only - never exposed in production.
        if ($otp && app()->environment('local', 'testing')) {
            $response['otp_preview'] = $otp;
        }

        return response()->json($response);
    }

    /**
     * Verify a 6-digit email OTP and sign the administrator in (passwordless OTP login).
     */
    public function verifyAdminOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|min:4|max:8',
        ]);

        $email = strtolower(trim($validated['email']));
        $record = $this->consumeAdminOtp($email, trim($validated['otp']), 'otp_login');

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired OTP code. Please check your email and try again.'], 422);
        }

        $admin = User::whereRaw('lower(email) = ?', [$email])->where('is_admin', true)->first();

        if (!$admin) {
            return response()->json(['message' => 'Invalid or expired OTP code.'], 422);
        }

        return $this->adminSession($admin, 'OTP verified successfully. Signed in to Kiloneed Command Center.');
    }

    /**
     * Reset the administrator password using a 6-digit email OTP.
     */
    public function resetAdminPasswordWithOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|min:4|max:8',
            'password' => 'required|string|min:8|max:128',
        ]);

        $email = strtolower(trim($validated['email']));
        $record = $this->consumeAdminOtp($email, trim($validated['otp']), ['forgot_password', 'security_reset']);

        $admin = $record
            ? User::whereRaw('lower(email) = ?', [$email])->where('is_admin', true)->first()
            : null;

        if (!$admin) {
            return response()->json(['message' => 'Invalid or expired OTP code.'], 422);
        }

        $admin->forceFill(['password' => $validated['password']])->save();
        $admin->tokens()->delete();

        return response()->json([
            'message' => 'Administrator password has been reset successfully via Email OTP.',
            'email' => $email,
        ]);
    }

    /**
     * Validate and consume an OTP. Returns the record on success, null otherwise.
     * Five wrong guesses invalidate the code.
     */
    protected function consumeAdminOtp(string $email, string $otp, string|array $purpose): ?AdminOtp
    {
        $record = AdminOtp::where('email', $email)
            ->whereIn('purpose', (array) $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest('id')
            ->first();

        if (!$record) {
            return null;
        }

        $attemptsKey = "kiloneed_admin_otp_attempts_{$email}";
        $attempts = (int) Cache::get($attemptsKey, 0);

        if ($attempts >= 5) {
            $record->update(['used_at' => Carbon::now()]);
            Cache::forget($attemptsKey);
            return null;
        }

        if (!hash_equals((string) $record->otp, $otp)) {
            Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(15));
            return null;
        }

        $record->update(['used_at' => Carbon::now()]);
        Cache::forget($attemptsKey);

        return $record;
    }

    protected function adminSession(User $user, string $message): JsonResponse
    {
        $token = $user->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => $message,
            'token' => $token,
            'user' => $user,
        ]);
    }
}