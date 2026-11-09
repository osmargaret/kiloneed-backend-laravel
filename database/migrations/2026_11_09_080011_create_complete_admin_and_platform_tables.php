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
        // 1. Add missing columns to ads table (ad_type, placement)
        Schema::table('ads', function (Blueprint $table) {
            if (!Schema::hasColumn('ads', 'ad_type')) {
                $table->string('ad_type')->default('Image & Text')->after('description');
            }
            if (!Schema::hasColumn('ads', 'placement')) {
                $table->string('placement')->default('Marketplace Featured')->after('ad_type');
            }
        });

        // 2. Add missing Special Group columns to groups table
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'group_type')) {
                $table->string('group_type')->default('open')->after('is_private'); // open, closed, special
            }
            if (!Schema::hasColumn('groups', 'monthly_dues')) {
                $table->decimal('monthly_dues', 12, 2)->default(0)->after('group_type');
            }
            if (!Schema::hasColumn('groups', 'treasury_balance')) {
                $table->decimal('treasury_balance', 14, 2)->default(0)->after('monthly_dues');
            }
            if (!Schema::hasColumn('groups', 'max_members')) {
                $table->unsignedInteger('max_members')->default(1000)->after('treasury_balance');
            }
        });

        // 3. Add message column to notifications table if not present
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable()->after('title');
            }
        });

        // 4. Create profile_change_requests table (30-Day Mandatory Identity Review Queue)
        if (!Schema::hasTable('profile_change_requests')) {
            Schema::create('profile_change_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('user_name');
                $table->string('user_email');
                $table->string('field'); // Profile Name, Location, Category
                $table->string('old_value')->nullable();
                $table->string('new_value');
                $table->unsignedInteger('days_remaining')->default(30);
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. Create group_disbursements table (Special Group 3-Member Multi-Sig Treasury Queue)
        if (!Schema::hasTable('group_disbursements')) {
            Schema::create('group_disbursements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
                $table->string('group_name');
                $table->string('requested_by');
                $table->decimal('amount', 14, 2);
                $table->text('purpose');
                $table->unsignedInteger('signatures_count')->default(3);
                $table->unsignedInteger('required_signatures')->default(3);
                $table->json('signers');
                $table->enum('status', ['pending_release', 'released', 'frozen'])->default('pending_release');
                $table->timestamps();
            });
        }

        // 6. Create platform_settings table (Persistent Tenant, Geofence & Pricing Config)
        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->json('value');
                $table->timestamps();
            });
        }

        // 7. Create admin_notifications table (Persistent Admin Operational Alerts)
        if (!Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('message');
                $table->string('type')->default('system'); // kyc, report, group, transaction, system
                $table->string('target_tab')->nullable(); // users, reports, groups, transactions, needs
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }

        // 8. Create admin_otps table (Registered Email 6-Digit OTP Verification & Password Reset)
        if (!Schema::hasTable('admin_otps')) {
            Schema::create('admin_otps', function (Blueprint $table) {
                $table->id();
                $table->string('email')->index();
                $table->string('otp', 10);
                $table->string('purpose')->default('forgot_password'); // forgot_password, otp_login, security_reset
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_otps');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('group_disbursements');
        Schema::dropIfExists('profile_change_requests');

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'message')) {
                $table->dropColumn('message');
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            $cols = array_filter(
                ['group_type', 'monthly_dues', 'treasury_balance', 'max_members'],
                fn ($c) => Schema::hasColumn('groups', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('ads', function (Blueprint $table) {
            $cols = array_filter(
                ['ad_type', 'placement'],
                fn ($c) => Schema::hasColumn('ads', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

