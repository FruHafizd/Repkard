<?php

namespace App\Models;

use Database\Factories\PtSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PtSubscription extends Model
{
    /** @use HasFactory<PtSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'pt_package_id',
        'trainer_id',
        'sessions_total',
        'sessions_remaining',
        'starts_on',
        'expires_on',
        'cancelled_at',
        'cancel_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sessions_total' => 'integer',
            'sessions_remaining' => 'integer',
            'starts_on' => 'date',
            'expires_on' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PtPackage::class, 'pt_package_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PtSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isExpired(): bool
    {
        return now()->toDateString() > $this->expires_on->toDateString();
    }

    public function isUsable(): bool
    {
        return ! $this->isCancelled() && ! $this->isExpired() && $this->sessions_remaining > 0;
    }

    /** Aktif | Habis | Kedaluwarsa | Dibatalkan */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isCancelled() => 'Dibatalkan',
            $this->isExpired() => 'Kedaluwarsa',
            $this->sessions_remaining < 1 => 'Habis',
            default => 'Aktif',
        };
    }
}
