<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('group_posts')) {
            Schema::create('group_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('text');
                $table->string('media_url')->nullable();
                $table->unsignedInteger('likes_count')->default(0);
                $table->unsignedInteger('comments_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_posts');
    }
};
