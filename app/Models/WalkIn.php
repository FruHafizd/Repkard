<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\WalkInFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pendatang / tamu harian tanpa akun member: hanya dicatat kunjungan dan pembayarannya.
 */
class WalkIn extends Model
{
    /** @use HasFactory<WalkInFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'method',
        'note',
        'recorded_by',
        'visited_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'visited_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
