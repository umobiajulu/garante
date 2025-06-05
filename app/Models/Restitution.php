<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restitution extends Model
{
    protected $fillable = [
        'verdict_id',
        'amount',
        'status',
        'proof_of_payment',
        'processed_at',
        'completed_at',
        'completed_by'
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function verdict()
    {
        return $this->belongsTo(Verdict::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canBeProcessed()
    {
        return $this->status === 'pending';
    }

    public function canBeCompleted()
    {
        return $this->status === 'processed';
    }
} 