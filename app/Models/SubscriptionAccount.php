<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'bank_name',
        'account_name',
        'account_number',
        'external_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function isActive()
    {
        return $this->status === 'active';
    }
}
