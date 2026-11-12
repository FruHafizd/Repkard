<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'membership_id',
        'pt_subscription_id',
        'type',
        'amount',
        'original_amount',
        'discount_amount',
        'promo_code_id',
        'method',
        'proof_path',
        'note',
        'recorded_by',
        'paid_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'original_amount' => 'integer',
            'discount_amount' => 'integer',
            'type' => PaymentType::class,
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function ptSubscription(): BelongsTo
    {
        return $this->belongsTo(PtSubscription::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Deskripsi yang dibayar: nama paket membership, atau paket PT beserta trainernya. */
    public function itemLabel(): string
    {
        if ($this->pt_subscription_id !== null && ($pt = $this->ptSubscription)) {
            return "Paket PT: {$pt->package->name} ({$pt->sessions_total} sesi) — Trainer {$pt->trainer->name}";
        }

        return $this->membership?->plan?->name ?? '-';
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
