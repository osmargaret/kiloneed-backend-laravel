<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Purges all initial demo mock data (users, mock needs, mock comments, fake connections)
     * so that the production environment is completely clean for real users.
     */
    public function up(): void
    {
        $demoEmails = [
            'user@kiloneed.com',
            'adaeze@kiloneed.com',
            'emeka@kiloneed.com',
            'halima@kiloneed.com',
            'bola@kiloneed.com',
        ];

        $demoUserIds = DB::table('users')->whereIn('email', $demoEmails)->pluck('id')->toArray();

        if (!empty($demoUserIds)) {
            $demoNeedIds = DB::table('needs')->whereIn('user_id', $demoUserIds)->pluck('id')->toArray();

            if (!empty($demoNeedIds)) {
                DB::table('comments')->whereIn('need_id', $demoNeedIds)->delete();
                DB::table('need_chats')->whereIn('need_id', $demoNeedIds)->delete();
                DB::table('need_media')->whereIn('need_id', $demoNeedIds)->delete();
                DB::table('bookmarks')->whereIn('need_id', $demoNeedIds)->delete();
                DB::table('reposts')->whereIn('need_id', $demoNeedIds)->delete();
                DB::table('needs')->whereIn('id', $demoNeedIds)->delete();
            }

            // Remove products from demo users
            $demoProductIds = DB::table('products')->whereIn('user_id', $demoUserIds)->pluck('id')->toArray();
            if (!empty($demoProductIds)) {
                DB::table('products')->whereIn('id', $demoProductIds)->delete();
            }

            DB::table('connections')->whereIn('user_id', $demoUserIds)->orWhereIn('connected_user_id', $demoUserIds)->delete();
            DB::table('group_members')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('groups')->whereIn('owner_id', $demoUserIds)->delete();
            DB::table('notifications')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('reports')->whereIn('reporter_id', $demoUserIds)->delete();
            DB::table('transactions')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('users')->whereIn('id', $demoUserIds)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
