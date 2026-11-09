<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->string('location')->nullable()->default('Ikeja, Lagos')->after('avatar');
            $table->decimal('latitude', 10, 7)->nullable()->default(6.5966)->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->default(3.3421)->after('latitude');
            $table->string('industry')->nullable()->default('Electronics')->after('longitude');
            $table->text('bio')->nullable()->after('industry');
            $table->string('company')->nullable()->after('bio');
            $table->string('role')->nullable()->default('member')->after('company');
            $table->string('kyc_status')->default('verified')->after('role'); // unverified, pending, verified
            $table->decimal('rating', 3, 2)->default(4.9)->after('kyc_status');
            $table->integer('review_count')->default(28)->after('rating');
            $table->integer('active_needs_count')->default(0)->after('review_count');
            $table->integer('completed_deals_count')->default(0)->after('active_needs_count');
            $table->json('settings')->nullable()->after('completed_deals_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'avatar',
                'location',
                'latitude',
                'longitude',
                'industry',
                'bio',
                'company',
                'role',
                'kyc_status',
                'rating',
                'review_count',
                'active_needs_count',
                'completed_deals_count',
                'settings',
            ]);
        });
    }
};

