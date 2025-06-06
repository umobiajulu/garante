<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'bank_name',
        'account_name',
        'account_number',
        'status'
    ];

    /**
     * Get the business that owns the account.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Check if the account is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
