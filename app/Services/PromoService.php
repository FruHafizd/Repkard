<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PromoCode;

class PromoService
{
    public function check(string $code, MembershipPlan $plan, Member $member, bool $isActivation): PromoResult
    {
        $price = (int) $plan->price;
        $tolak = fn (string $message): PromoResult => new PromoResult(false, $message, null, 0, $price);

        $promo = PromoCode::query()
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();

        if (! $promo) {
            return $tolak('Kode promo tidak ditemukan');
        }

        $today = now()->startOfDay();

        if ($promo->starts_on && $today->lt($promo->starts_on)) {
            return $tolak('Kode promo belum berlaku');
        }

        if ($promo->ends_on && $today->gt($promo->ends_on)) {
            return $tolak('Kode promo sudah berakhir');
        }

        if ($promo->plans()->exists() && ! $promo->plans()->whereKey($plan->getKey())->exists()) {
            return $tolak('Kode promo tidak berlaku untuk paket ini');
        }

        if ($promo->new_members_only && ! $isActivation) {
            return $tolak('Kode promo khusus member baru');
        }

        $dipakai = fn () => Payment::query()->where('promo_code_id', $promo->getKey())->whereNull('cancelled_at');

        if ($promo->max_uses !== null && $dipakai()->count() >= $promo->max_uses) {
            return $tolak('Kuota kode promo sudah habis');
        }

        if ($dipakai()->where('member_id', $member->getKey())->count() >= $promo->max_uses_per_member) {
            return $tolak('Anda sudah memakai kode promo ini');
        }

        $discount = match ($promo->discount_type) {
            DiscountType::Percent => intdiv($price * $promo->discount_value, 100),
            DiscountType::Fixed => min($promo->discount_value, $price),
        };
        $discount = min($discount, $price);

        return new PromoResult(true, 'Kode promo dipakai', $promo, $discount, $price - $discount);
    }
}
