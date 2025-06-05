<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuaranteeTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'service_description',
        'price',
        'terms',
        'expires_in_days',
        'created_by'
    ];

    protected $casts = [
        'terms' => 'array',
        'price' => 'decimal:2',
        'expires_in_days' => 'integer'
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
} 