<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Comment;
use App\Models\Connection;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Need;
use App\Models\NeedChat;
use App\Models\NeedMedia;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::count() > 0) {
            return;
        }

        // 1. Create Default Test User (John Doe)
        $testUser = User::create([
            'name' => 'John Doe',
            'email' => 'user@kiloneed.com',
            'password' => Hash::make('password123'),
            'phone' => '+234 801 234 5678',
            'avatar' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
            'location' => 'Ikeja, Lagos',
            'latitude' => 6.5966,
            'longitude' => 3.3421,
            'industry' => 'Electronics',
            'bio' => 'Licensed wholesale electronics distributor & IT procurement contractor.',
            'company' => 'NexTech Solutions Nigeria Ltd',
            'role' => 'Enterprise Buyer',
            'kyc_status' => 'verified',
            'rating' => 4.9,
            'review_count' => 32,
            'active_needs_count' => 3,
            'completed_deals_count' => 47,
        ]);

        // 2. Peer Users
        $adaeze = User::create([
            'name' => 'Adaeze Okafor',
            'email' => 'adaeze@kiloneed.com',
            'password' => Hash::make('password123'),
            'phone' => '+234 802 345 6789',
            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
            'location' => 'Computer Village, Ikeja',
            'latitude' => 6.5930,
            'longitude' => 3.3400,
            'industry' => 'Electronics',
            'company' => 'Apex Digital Systems',
            'kyc_status' => 'verified',
            'rating' => 4.95,
            'review_count' => 54,
        ]);

        $emeka = User::create([
            'name' => 'Emeka Nwosu',
            'email' => 'emeka@kiloneed.com',
            'password' => Hash::make('password123'),
            'phone' => '+234 803 456 7890',
            'avatar' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&auto=format&fit=crop&q=80',
            'location' => 'Alaba Int\'l Market, Lagos',
            'latitude' => 6.4600,
            'longitude' => 3.1900,
            'industry' => 'Electronics',
            'company' => 'Nwosu Telecomms & Power',
            'kyc_status' => 'verified',
            'rating' => 4.88,
            'review_count' => 41,
        ]);

        $halima = User::create([
            'name' => 'Halima Sani',
            'email' => 'halima@kiloneed.com',
            'password' => Hash::make('password123'),
            'phone' => '+234 804 567 8901',
            'avatar' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80',
            'location' => 'Wuse II, Abuja',
            'latitude' => 9.0765,
            'longitude' => 7.4700,
            'industry' => 'Fashion',
            'company' => 'Aura Couture & Textiles',
            'kyc_status' => 'verified',
            'rating' => 4.92,
            'review_count' => 39,
        ]);

        $bola = User::create([
            'name' => 'Bola Tinubu Jr',
            'email' => 'bola@kiloneed.com',
            'password' => Hash::make('password123'),
            'phone' => '+234 805 678 9012',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
            'location' => 'Victoria Island, Lagos',
            'latitude' => 6.4281,
            'longitude' => 3.4219,
            'industry' => 'Logistics',
            'company' => 'Coastline Transit Logistics',
            'kyc_status' => 'verified',
            'rating' => 4.79,
            'review_count' => 22,
        ]);

        // 3. Establish Connections with test user
        foreach ([$adaeze, $emeka, $halima, $bola] as $peer) {
            Connection::create([
                'user_id' => $testUser->id,
                'connected_user_id' => $peer->id,
                'status' => 'accepted',
            ]);
            Connection::create([
                'user_id' => $peer->id,
                'connected_user_id' => $testUser->id,
                'status' => 'accepted',
            ]);
        }

        // 4. Create Groups
        $g1 = Group::create([
            'owner_id' => $adaeze->id,
            'name' => 'Lagos Electronics Traders',
            'description' => 'Wholesale verified electronics importers, distributors and retail merchants across Computer Village and Alaba.',
            'category' => 'Electronics',
            'is_private' => false,
        ]);

        $g2 = Group::create([
            'owner_id' => $emeka->id,
            'name' => 'Bulk Importers Network',
            'description' => 'Container clearing, bulk freight logistics and commercial trade syndicate across West Africa.',
            'category' => 'Logistics',
            'is_private' => true,
        ]);

        $g3 = Group::create([
            'owner_id' => $halima->id,
            'name' => 'Tech Sellers Hub',
            'description' => 'Hub for corporate IT suppliers, warranty parts suppliers, and enterprise hardware dealers.',
            'category' => 'Technology',
            'is_private' => false,
        ]);

        GroupMember::create(['group_id' => $g1->id, 'user_id' => $testUser->id, 'role' => 'member']);
        GroupMember::create(['group_id' => $g2->id, 'user_id' => $testUser->id, 'role' => 'member']);
        GroupMember::create(['group_id' => $g3->id, 'user_id' => $testUser->id, 'role' => 'member']);

        // 5. Seed Needs
        // Need 1 (Adaeze)
        $need1 = Need::create([
            'user_id' => $adaeze->id,
            'title' => 'Urgent need for 50 pieces MacBook 96W USB-C Adapters',
            'text' => 'Urgent need for 50 pieces of Original MacBook 96W USB-C Power Adapters. Wholesale lot needed for corporate client delivery on Friday. Budget ₦1.8M–₦2.2M.',
            'location' => 'Computer Village, Ikeja',
            'latitude' => 6.5930,
            'longitude' => 3.3400,
            'radius' => '5km',
            'categories' => ['Electronics', 'Services'],
            'privacy' => 'connections',
            'expires_at' => Carbon::now()->addHours(6),
            'clicks' => 42,
            'responses' => 5,
            'mentions' => 3,
            'saves_count' => 8,
            'shares_count' => 4,
            'reposts_count' => 2,
            'recos_count' => 7,
            'created_at' => Carbon::now()->subHours(18),
        ]);

        NeedMedia::create([
            'need_id' => $need1->id,
            'type' => 'image',
            'url' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=600&auto=format&fit=crop&q=80',
            'alt' => 'Original MacBook USB-C Power Adapters boxed in warehouse',
        ]);

        NeedMedia::create([
            'need_id' => $need1->id,
            'type' => 'document',
            'url' => '#',
            'name' => 'Delivery_Tender_Specs.pdf',
            'size' => '1.2 MB',
        ]);

        Comment::create([
            'need_id' => $need1->id,
            'user_id' => $emeka->id,
            'text' => 'I have 30 units in stock right now at Computer Village. Let\'s talk.',
            'created_at' => Carbon::now()->subHours(16),
        ]);

        NeedChat::create([
            'need_id' => $need1->id,
            'sender_id' => $testUser->id,
            'recipient_id' => $adaeze->id,
            'text' => 'Hi Adaeze, I saw your need for MacBook adapters. Are you looking for OEM or original sealed?',
            'created_at' => Carbon::now()->subHours(17),
        ]);

        NeedChat::create([
            'need_id' => $need1->id,
            'sender_id' => $adaeze->id,
            'recipient_id' => $testUser->id,
            'text' => 'Hi! Original sealed only please, it\'s for an enterprise deployment.',
            'created_at' => Carbon::now()->subHours(16)->addMinutes(30),
        ]);

        // Need 2 (Emeka)
        $need2 = Need::create([
            'user_id' => $emeka->id,
            'title' => 'Need Certified Fiber Optic Technician for LAN & Rack Wiring',
            'text' => 'Seeking a certified fiber optic cable technician for warehouse LAN installation and 42U server rack patch panel termination in Alaba. Must have fusion splicer and OTDR testing kit.',
            'location' => 'Alaba Int\'l Market, Lagos',
            'latitude' => 6.4600,
            'longitude' => 3.1900,
            'radius' => '20km',
            'categories' => ['Electronics', 'Services'],
            'privacy' => 'public',
            'expires_at' => Carbon::now()->addHours(12),
            'clicks' => 28,
            'responses' => 3,
            'mentions' => 1,
            'saves_count' => 4,
            'shares_count' => 2,
            'reposts_count' => 1,
            'recos_count' => 5,
            'created_at' => Carbon::now()->subHours(12),
        ]);

        NeedMedia::create([
            'need_id' => $need2->id,
            'type' => 'document',
            'url' => '#',
            'name' => 'Warehouse_Cabling_Blueprint.pdf',
            'size' => '3.4 MB',
        ]);

        // Need 3 (Halima)
        $need3 = Need::create([
            'user_id' => $halima->id,
            'title' => 'Bulk supply of 200 Yards Premium Aso-Oke & Italian Lace',
            'text' => 'Looking for wholesale supplier of genuine champagne gold Aso-Oke and raw Swiss voile lace for a high-profile bridal party. Must be available for physical inspection in Abuja or express dispatch from Lagos.',
            'location' => 'Wuse II, Abuja',
            'latitude' => 9.0765,
            'longitude' => 7.4700,
            'radius' => '50km',
            'categories' => ['Fashion'],
            'privacy' => 'public',
            'expires_at' => Carbon::now()->addHours(20),
            'clicks' => 65,
            'responses' => 8,
            'mentions' => 4,
            'saves_count' => 12,
            'shares_count' => 9,
            'reposts_count' => 5,
            'recos_count' => 11,
            'created_at' => Carbon::now()->subHours(8),
        ]);

        NeedMedia::create([
            'need_id' => $need3->id,
            'type' => 'image',
            'url' => 'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=600&auto=format&fit=crop&q=80',
            'alt' => 'Luxury textiles fabrics and lace materials',
        ]);

        // Need 4 (Bola)
        $need4 = Need::create([
            'user_id' => $bola->id,
            'title' => 'Need 5 Air-Conditioned Hiace Buses for Corporate Retreat',
            'text' => 'Required: 5 units of clean, fully air-conditioned Toyota Hiace buses with professional drivers for a 3-day corporate executive retreat from Victoria Island to Epe Resort. Departure this Saturday 7 AM.',
            'location' => 'Victoria Island, Lagos',
            'latitude' => 6.4281,
            'longitude' => 3.4219,
            'radius' => '50km',
            'categories' => ['Logistics', 'Services'],
            'privacy' => 'public',
            'expires_at' => Carbon::now()->addHours(14),
            'clicks' => 51,
            'responses' => 7,
            'mentions' => 2,
            'saves_count' => 6,
            'shares_count' => 8,
            'reposts_count' => 3,
            'recos_count' => 6,
            'created_at' => Carbon::now()->subHours(4),
        ]);

        // 6. Seed Notifications
        Notification::create([
            'user_id' => $testUser->id,
            'type' => 'mention',
            'title' => 'Adaeze Okafor mentioned you to attend to a need',
            'description' => 'Regarding: "Urgent need for 50 pieces MacBook 96W USB-C Adapters..."',
            'detail' => 'You were recommended by Adaeze Okafor to attend to this need within your industry. Click to view need details and respond.',
            'from_user_id' => $adaeze->id,
            'need_id' => $need1->id,
            'is_read' => false,
        ]);

        Notification::create([
            'user_id' => $testUser->id,
            'type' => 'connection',
            'title' => 'Emeka Nwosu accepted your connection request',
            'description' => 'You can now share private needs and collaborate.',
            'from_user_id' => $emeka->id,
            'is_read' => true,
            'read_at' => Carbon::now()->subHours(5),
        ]);

        // 7. Seed Wallet & Transactions
        Transaction::create([
            'user_id' => $testUser->id,
            'type' => 'deposit',
            'amount' => 150000.00,
            'currency' => 'NGN',
            'reference' => 'TXN_' . strtoupper(Str::random(10)),
            'status' => 'successful',
            'description' => 'Paystack Bank Transfer Funding',
        ]);

        Transaction::create([
            'user_id' => $testUser->id,
            'type' => 'payment',
            'amount' => 15000.00,
            'currency' => 'NGN',
            'reference' => 'TXN_' . strtoupper(Str::random(10)),
            'status' => 'successful',
            'description' => '30-Day Pro Merchant Verified Plan',
        ]);

        Transaction::create([
            'user_id' => $adaeze->id,
            'type' => 'payment',
            'amount' => 120000.00,
            'currency' => 'NGN',
            'reference' => 'TXN_' . strtoupper(Str::random(10)),
            'status' => 'successful',
            'description' => 'Annual Premium Merchant Plan & Ad Boost',
        ]);

        // 8. Seed Admin & Pending KYC Accounts
        User::create([
            'name' => 'Kiloneed Super Admin',
            'email' => 'admin@kiloneed.com',
            'password' => Hash::make('admin123'),
            'phone' => '+234 800 000 0000',
            'location' => 'Victoria Island, Lagos',
            'industry' => 'Administration',
            'company' => 'Kiloneed Platform HQ',
            'role' => 'admin',
            'kyc_status' => 'verified',
            'rating' => 5.0,
            'review_count' => 100,
        ]);

        $david = User::create([
            'name' => 'David Okonkwo',
            'email' => 'david@kiloneed.com',
            'password' => Hash::make('password123'),
            'phone' => '+234 806 789 0123',
            'location' => 'Onitsha Main Market, Anambra',
            'industry' => 'FMCG & Wholesale',
            'company' => 'Okonkwo Global Commodities',
            'role' => 'Wholesale Supplier',
            'kyc_status' => 'pending',
            'rating' => 4.7,
            'review_count' => 14,
        ]);

        // 9. Seed Sponsored Ads
        Ad::create([
            'user_id' => $adaeze->id,
            'title' => 'Wholesale MacBook & Enterprise IT Hardware - Computer Village',
            'description' => 'Authorized distributor for Apple, Dell, and Cisco enterprise hardware. Same-day dispatch across Lagos.',
            'media_url' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&auto=format&fit=crop&q=80',
            'target_url' => 'https://kiloneed.com/marketplace',
            'budget' => 75000,
            'spent' => 32400,
            'impressions' => 14820,
            'clicks' => 642,
            'status' => 'active',
        ]);

        Ad::create([
            'user_id' => $bola->id,
            'title' => 'Coastline Express Haulage & Corporate Bus Charter',
            'description' => 'Insured interstate haulage and executive coaster/Hiace fleet rentals across Nigeria.',
            'media_url' => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&auto=format&fit=crop&q=80',
            'target_url' => 'https://kiloneed.com/marketplace',
            'budget' => 50000,
            'spent' => 18500,
            'impressions' => 8930,
            'clicks' => 319,
            'status' => 'active',
        ]);

        // 10. Seed Safety & Moderation Reports
        Report::create([
            'reporter_id' => $emeka->id,
            'target_type' => 'need',
            'target_id' => $need4->id,
            'reason' => 'Misleading Pricing / Off-Category',
            'details' => 'Posted under general services without specifying daily charter rate range.',
            'status' => 'pending',
        ]);

        Report::create([
            'reporter_id' => $halima->id,
            'target_type' => 'user',
            'target_id' => $david->id,
            'reason' => 'Unverified CAC Document Check Requested',
            'details' => 'Supplier requested advance deposit before completing KYC badge verification.',
            'status' => 'pending',
        ]);

        // 11. Seed Marketplace Products
        Product::create([
            'user_id' => $adaeze->id,
            'title' => 'iPhone 15 Pro Max 256GB - Titanium Blue',
            'price' => 950000,
            'description' => 'Brand new, sealed in box. Direct US import with receipt and 1-year Apple international warranty.',
            'seller_type' => 'Business',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'category' => 'Electronics',
            'subcategory' => 'Phones & Tablets',
            'image' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=500&h=500&fit=crop',
            'featured' => true,
            'condition' => 'Brand New',
            'availability' => 'Available',
            'listing_type' => 'For Sale',
            'negotiable' => false,
            'status' => 'active',
        ]);

        Product::create([
            'user_id' => $adaeze->id,
            'title' => 'MacBook Pro 16-inch M3 Max (36GB RAM / 1TB SSD)',
            'price' => 2850000,
            'description' => 'Factory sealed Space Black MacBook Pro M3 Max. Available for immediate pickup at Computer Village Ikeja.',
            'seller_type' => 'Business',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'category' => 'Electronics',
            'subcategory' => 'Laptops & Computers',
            'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=500&h=500&fit=crop',
            'featured' => true,
            'condition' => 'Brand New',
            'availability' => 'Available',
            'listing_type' => 'For Sale',
            'negotiable' => true,
            'status' => 'active',
        ]);

        Product::create([
            'user_id' => $emeka->id,
            'title' => '5kVA Hybrid Solar Inverter + 4x Tubular Batteries',
            'price' => 1420000,
            'description' => 'Complete pure sine wave inverter bundle with 24-month manufacturer warranty and optional Lagos installation.',
            'seller_type' => 'Dealer',
            'state' => 'Lagos',
            'city' => 'Surulere',
            'category' => 'Electronics',
            'subcategory' => 'Accessories',
            'image' => 'https://images.unsplash.com/photo-1509391366360-2e959784a276?w=500&h=500&fit=crop',
            'featured' => false,
            'condition' => 'Brand New',
            'availability' => 'Available',
            'listing_type' => 'For Sale',
            'negotiable' => true,
            'status' => 'active',
        ]);

        Product::create([
            'user_id' => $bola->id,
            'title' => 'Toyota Hilux 2022 Adventure 4x4 Automatic',
            'price' => 38500000,
            'description' => 'Accident-free, custom duty fully paid, thumbstart, reverse camera, leather interior. Inspection in Victoria Island.',
            'seller_type' => 'Dealer',
            'state' => 'Lagos',
            'city' => 'Victoria Island',
            'category' => 'Vehicles',
            'subcategory' => 'Cars',
            'image' => 'https://images.unsplash.com/photo-1559416523-140ddc3d238c?w=500&h=500&fit=crop',
            'featured' => true,
            'condition' => 'Like New',
            'availability' => 'Available',
            'listing_type' => 'For Sale',
            'negotiable' => true,
            'status' => 'active',
        ]);
    }
}
