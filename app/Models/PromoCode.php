<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'starts_on',
        'ends_on',
        'max_uses',
        'max_uses_per_member',
        'new_members_only',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'max_uses' => 'integer',
            'max_uses_per_member' => 'integer',
            'new_members_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Kode selalu disimpan huruf besar. */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value): string => strtoupper(trim($value)));
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(MembershipPlan::class, 'membership_plan_promo_code');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Jumlah pemakaian: pembayaran yang tidak dibatalkan. */
    public function usesCount(): int
    {
        return $this->payments()->whereNull('cancelled_at')->count();
    }

    public function discountLabel(): string
    {
        return $this->discount_type === DiscountType::Percent
            ? $this->discount_value.'%'
            : 'Rp '.number_format($this->discount_value, 0, ',', '.');
    }
}
