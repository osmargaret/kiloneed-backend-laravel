<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'group_name',
        'requested_by',
        'amount',
        'purpose',
        'signatures_count',
        'required_signatures',
        'signers',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
        'signatures_count' => 'integer',
        'required_signatures' => 'integer',
        'signers' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}

