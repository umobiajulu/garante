<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_IN_REVIEW = 'in_review';
    const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'guarantee_id',
        'initiated_by',
        'reason',
        'description',
        'evidence',
        'defense',
        'defense_description',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'defense' => 'array',
        'resolved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($dispute) {
            if (!in_array($dispute->status, [
                self::STATUS_PENDING,
                self::STATUS_IN_REVIEW,
                self::STATUS_RESOLVED
            ])) {
                throw new \InvalidArgumentException('Invalid dispute status');
            }
        });
    }

    public function guarantee()
    {
        return $this->belongsTo(Guarantee::class);
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved()
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function canBeResolved()
    {
        // Can be resolved if in review
        if ($this->status === self::STATUS_IN_REVIEW) {
            return true;
        }

        // Can be resolved if pending and 3 working days have passed since creation
        if ($this->status === self::STATUS_PENDING) {
            $workingDays = 0;
            $date = $this->created_at->copy();
            $endDate = now();

            while ($date->lt($endDate)) {
                // Skip weekends (Saturday = 6, Sunday = 0)
                if (!$date->isWeekend()) {
                    $workingDays++;
                }
                $date->addDay();
            }

            \Log::info('Dispute resolution check', [
                'dispute_id' => $this->id,
                'created_at' => $this->created_at->toDateTimeString(),
                'now' => $endDate->toDateTimeString(),
                'working_days' => $workingDays,
                'can_resolve' => $workingDays >= 3
            ]);

            return $workingDays >= 3;
        }

        return false;
    }

    public function hasDefense()
    {
        return !empty($this->defense);
    }

    public function canSubmitDefense()
    {
        return $this->status === self::STATUS_PENDING;
    }
}
