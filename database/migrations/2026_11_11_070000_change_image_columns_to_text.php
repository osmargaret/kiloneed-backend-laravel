<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE groups ALTER COLUMN cover_image TYPE TEXT;');
        DB::statement('ALTER TABLE groups ALTER COLUMN icon TYPE TEXT;');
        DB::statement('ALTER TABLE users ALTER COLUMN avatar TYPE TEXT;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE groups ALTER COLUMN cover_image TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE groups ALTER COLUMN icon TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE users ALTER COLUMN avatar TYPE VARCHAR(255);');
    }
};
