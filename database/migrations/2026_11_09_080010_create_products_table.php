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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('price', 14, 2);
            $table->text('description');
            $table->string('seller_type')->default('Business'); // Individual, Dealer, Business
            $table->string('state')->default('Lagos');
            $table->string('city')->default('Ikeja');
            $table->decimal('latitude', 10, 7)->default(6.5966);
            $table->decimal('longitude', 10, 7)->default(3.3421);
            $table->string('category')->default('Electronics');
            $table->string('subcategory')->nullable();
            $table->text('image')->nullable();
            $table->boolean('featured')->default(false);
            $table->string('condition')->default('Brand New');
            $table->string('availability')->default('Available');
            $table->string('listing_type')->default('For Sale');
            $table->boolean('negotiable')->default(true);
            $table->enum('status', ['active', 'flagged', 'removed'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

