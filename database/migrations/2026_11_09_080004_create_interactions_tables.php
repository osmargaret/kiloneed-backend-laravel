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
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('need_id')->constrained('needs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'need_id']);
        });

        Schema::create('reposts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('need_id')->constrained('needs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'need_id']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('need_id')->constrained('needs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'need_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('reposts');
        Schema::dropIfExists('bookmarks');
    }
};

