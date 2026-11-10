<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TABLE users ADD COLUMN IF NOT EXISTS country VARCHAR(255) DEFAULT 'Nigeria'");
        DB::statement("ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(255) DEFAULT 'Lagos'");
        DB::statement("ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(255) DEFAULT 'Ikeja'");

        DB::statement("ALTER TABLE needs ADD COLUMN IF NOT EXISTS country VARCHAR(255) DEFAULT 'Nigeria'");
        DB::statement("ALTER TABLE needs ADD COLUMN IF NOT EXISTS state VARCHAR(255) DEFAULT 'Lagos'");
        DB::statement("ALTER TABLE needs ADD COLUMN IF NOT EXISTS city VARCHAR(255) DEFAULT 'Ikeja'");

        DB::statement("ALTER TABLE products ADD COLUMN IF NOT EXISTS country VARCHAR(255) DEFAULT 'Nigeria'");
    }

    public function down(): void
    {
    }
};
