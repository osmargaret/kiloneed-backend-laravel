<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NeedMedia extends Model
{
    use HasFactory;

    protected $table = 'need_media';

    protected $fillable = [
        'need_id',
        'type',
        'url',
        'name',
        'size',
        'alt',
    ];

    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }
}

