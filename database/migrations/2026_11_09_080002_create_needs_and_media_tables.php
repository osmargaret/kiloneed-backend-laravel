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
        Schema::create('needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('text');
            $table->string('location')->default('Ikeja, Lagos');
            $table->decimal('latitude', 10, 7)->default(6.5966);
            $table->decimal('longitude', 10, 7)->default(3.3421);
            $table->string('radius')->default('50km');
            $table->json('categories');
            $table->enum('privacy', ['public', 'connections', 'groups', 'only_me'])->default('public');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('responses')->default(0);
            $table->unsignedBigInteger('mentions')->default(0);
            $table->unsignedBigInteger('saves_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('reposts_count')->default(0);
            $table->unsignedBigInteger('recos_count')->default(0);
            $table->enum('status', ['active', 'fulfilled', 'expired', 'archived'])->default('active');
            $table->timestamps();
        });

        Schema::create('need_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('need_id')->constrained('needs')->cascadeOnDelete();
            $table->enum('type', ['image', 'video', 'document'])->default('image');
            $table->text('url');
            $table->string('name')->nullable();
            $table->string('size')->nullable();
            $table->string('alt')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('need_media');
        Schema::dropIfExists('needs');
    }
};

