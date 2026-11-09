<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ad extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'ad_type',
        'placement',
        'media_url',
        'target_url',
        'budget',
        'spent',
        'impressions',
        'clicks',
        'status',
    ];

    protected $casts = [
        'budget' => 'float',
        'spent' => 'float',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
