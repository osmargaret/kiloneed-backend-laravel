<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'price',
        'description',
        'seller_type',
        'state',
        'city',
        'latitude',
        'longitude',
        'category',
        'subcategory',
        'image',
        'featured',
        'condition',
        'availability',
        'listing_type',
        'negotiable',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'featured' => 'boolean',
        'negotiable' => 'boolean',
    ];

    protected $appends = [
        'seller',
        'sellerAvatar',
        'sellerType',
        'sellerVerified',
        'rating',
        'reviews',
        'listingType',
        'datePosted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSellerAttribute(): string
    {
        return $this->user ? ($this->user->company ?: $this->user->name) : 'Verified Merchant';
    }

    public function getSellerAvatarAttribute(): string
    {
        $name = $this->getSellerAttribute();
        $parts = explode(' ', trim($name));
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $initials .= mb_substr($p, 0, 1);
        }
        return strtoupper($initials) ?: 'VM';
    }

    public function getSellerTypeAttribute(): string
    {
        return $this->attributes['seller_type'] ?? 'Business';
    }

    public function getSellerVerifiedAttribute(): bool
    {
        return $this->user ? ($this->user->kyc_status === 'verified') : true;
    }

    public function getRatingAttribute(): float
    {
        return $this->user ? (float) $this->user->rating : 4.9;
    }

    public function getReviewsAttribute(): int
    {
        return $this->user ? (int) $this->user->review_count : 25;
    }

    public function getListingTypeAttribute(): string
    {
        return $this->attributes['listing_type'] ?? 'For Sale';
    }

    public function getDatePostedAttribute(): string
    {
        return $this->created_at ? $this->created_at->toIso8601String() : now()->toIso8601String();
    }
}

