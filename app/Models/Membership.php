<?php

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'membership_plan_id',
        'starts_on',
        'ends_on',
        'visits_remaining',
        'visits_total',
        'credit_amount',
        'frozen_from',
        'frozen_until',
        'cancelled_at',
        'cancel_reason',
        'replaced_by_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'visits_remaining' => 'integer',
            'visits_total' => 'integer',
            'credit_amount' => 'integer',
            'frozen_from' => 'date',
            'frozen_until' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isFrozen(): bool
    {
        return $this->frozen_from !== null
            && $this->frozen_until !== null
            && now()->startOfDay()->betweenIncluded($this->frozen_from, $this->frozen_until);
    }
}
