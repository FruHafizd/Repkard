<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Events\MemberActivated;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PromoCode;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MembershipService
{
    public const MAX_FREEZE_DAYS = 30;

    /**
     * Aktivasi pertama: beri nomor member, buat membership dan catat pembayaran.
     */
    public function activate(Member $member, MembershipPlan $plan, PaymentMethod $method, int $amount, User $by, ?string $proofPath = null, ?PromoCode $promo = null): Membership
    {
        $membership = DB::transaction(function () use ($member, $plan, $method, $amount, $by, $proofPath, $promo): Membership {
            $member = Member::withTrashed()->lockForUpdate()->findOrFail($member->getKey());

            if ($member->status !== MemberStatus::Pending) {
                throw new InvalidArgumentException('Hanya member berstatus "Menunggu aktivasi" yang bisa diaktifkan.');
            }

            // Nomor urut berjalan, tidak di-reset per bulan.
            $seq = ((int) Member::withTrashed()->lockForUpdate()->max('member_seq')) + 1;

            $member->forceFill([
                'member_seq' => $seq,
                'member_number' => sprintf('RPK-%s-%04d', now()->format('ym'), $seq),
                'status' => MemberStatus::Active,
                'activated_at' => now(),
                'activated_by' => $by->getKey(),
                'selected_plan_id' => $plan->getKey(),
            ])->save();

            return $this->buat($member, $plan, now()->startOfDay(), $method, $amount, $by, $proofPath, $promo, true);
        });

        MemberActivated::dispatch($membership->member);

        return $membership;
    }

    public function renew(Member $member, MembershipPlan $plan, PaymentMethod $method, int $amount, User $by, ?string $proofPath = null, ?PromoCode $promo = null): Membership
    {
        return DB::transaction(function () use ($member, $plan, $method, $amount, $by, $proofPath, $promo): Membership {
            $member = Member::withTrashed()->lockForUpdate()->findOrFail($member->getKey());

            if ($member->status !== MemberStatus::Active) {
                throw new InvalidArgumentException('Hanya member aktif yang bisa diperpanjang.');
            }

            $today = now()->startOfDay();
            $terakhir = $member->memberships()->whereNull('cancelled_at')->orderByDesc('ends_on')->first();

            $mulai = $terakhir && $terakhir->ends_on->gte($today)
                ? $terakhir->ends_on->copy()->addDay()
                : $today;

            return $this->buat($member, $plan, $mulai, $method, $amount, $by, $proofPath, $promo, false);
        });
    }

    public function freeze(Membership $membership, int $days, User $by): void
    {
        if ($days < 1 || $days > self::MAX_FREEZE_DAYS) {
            throw new InvalidArgumentException('Pembekuan maksimal '.self::MAX_FREEZE_DAYS.' hari.');
        }

        DB::transaction(function () use ($membership, $days): void {
            $membership = Membership::lockForUpdate()->findOrFail($membership->getKey());

            if ($membership->cancelled_at) {
                throw new InvalidArgumentException('Membership sudah dibatalkan.');
            }

            if ($membership->isFrozen()) {
                throw new InvalidArgumentException('Membership sedang dibekukan.');
            }

            $today = now()->startOfDay();

            $membership->update([
                'frozen_from' => $today,
                'frozen_until' => $today->copy()->addDays($days - 1),
                'ends_on' => $membership->ends_on->copy()->addDays($days),
            ]);
        });
    }

    /**
     * Nilai uang membership: semua pembayaran yang tidak dibatalkan + kredit bawaan paket lama.
     */
    public function membershipValue(Membership $membership): int
    {
        return (int) $membership->payments()->whereNull('cancelled_at')->sum('amount') + $membership->credit_amount;
    }

    /**
     * Sisa nilai prorata: nilai × rasio terkecil antara sisa hari dan sisa kunjungan, dibulatkan ke bawah.
     */
    public function remainingValue(Membership $membership): int
    {
        $today = now()->startOfDay();

        if ($membership->cancelled_at || $membership->ends_on->lt($today)) {
            return 0;
        }

        $nilai = $this->membershipValue($membership);

        // Belum mulai: belum ada yang terpakai.
        if ($today->lt($membership->starts_on)) {
            return $nilai;
        }

        $pembilang = (int) $today->diffInDays($membership->ends_on) + 1;
        $penyebut = (int) $membership->starts_on->diffInDays($membership->ends_on) + 1;

        if ($membership->visits_total && $membership->visits_remaining !== null) {
            $sisaKunjungan = max(0, $membership->visits_remaining);

            // Bandingkan pecahan lewat perkalian silang (tanpa pembulatan).
            if ($sisaKunjungan * $penyebut < $pembilang * $membership->visits_total) {
                $pembilang = $sisaKunjungan;
                $penyebut = $membership->visits_total;
            }
        }

        return intdiv($nilai * $pembilang, $penyebut);
    }

    /**
     * Upgrade ke paket lebih mahal mulai hari ini; sisa nilai paket lama jadi kredit.
     */
    public function upgrade(Member $member, MembershipPlan $newPlan, PaymentMethod $method, int $amount, User $by): Membership
    {
        return DB::transaction(function () use ($member, $newPlan, $method, $amount, $by): Membership {
            $member = Member::withTrashed()->lockForUpdate()->findOrFail($member->getKey());

            $lama = $member->currentMembership();

            if (! $lama) {
                throw new InvalidArgumentException('Member tidak punya membership yang sedang berjalan.');
            }

            $lama = Membership::lockForUpdate()->findOrFail($lama->getKey());

            if ($lama->cancelled_at) {
                throw new InvalidArgumentException('Membership sudah dibatalkan.');
            }

            if ($lama->isFrozen()) {
                throw new InvalidArgumentException('Membership sedang dibekukan.');
            }

            $today = now()->startOfDay();

            $belumMulai = $member->memberships()
                ->whereNull('cancelled_at')
                ->whereDate('starts_on', '>', $today->toDateString())
                ->exists();

            if ($belumMulai) {
                throw new InvalidArgumentException('Ada perpanjangan yang belum dimulai. Batalkan dulu atau tunggu sampai berjalan.');
            }

            if ($newPlan->price <= $lama->plan->price) {
                throw new InvalidArgumentException('Untuk paket yang sama atau lebih murah, gunakan Perpanjang (berlaku periode berikutnya).');
            }

            if ($amount < 0) {
                throw new InvalidArgumentException('Jumlah bayar tidak boleh negatif.');
            }

            $kredit = min($this->remainingValue($lama), $newPlan->price);

            $lama->update([
                'cancelled_at' => now(),
                'cancel_reason' => 'Diupgrade ke '.$newPlan->name,
            ]);

            $baru = $this->buat(
                $member, $newPlan, $today, $method, $amount, $by, null, null, false,
                $kredit, PaymentType::Upgrade, 'Kredit dari paket lama: Rp '.number_format($kredit, 0, ',', '.'),
            );

            $lama->update(['replaced_by_id' => $baru->getKey()]);

            return $baru;
        });
    }

    /**
     * Refund: uang dikembalikan ke member dan membership dibatalkan. Status member tidak diubah.
     */
    public function refund(Membership $membership, int $amount, PaymentMethod $method, string $reason, User $by): Refund
    {
        return DB::transaction(function () use ($membership, $amount, $method, $reason, $by): Refund {
            $membership = Membership::lockForUpdate()->findOrFail($membership->getKey());

            if ($membership->cancelled_at) {
                throw new InvalidArgumentException('Membership sudah dibatalkan.');
            }

            if (blank(trim($reason))) {
                throw new InvalidArgumentException('Alasan refund wajib diisi.');
            }

            $nilai = $this->membershipValue($membership);

            if ($amount < 1 || $amount > $nilai) {
                throw new InvalidArgumentException('Jumlah refund harus antara Rp 1 dan Rp '.number_format($nilai, 0, ',', '.').'.');
            }

            $membership->update([
                'cancelled_at' => now(),
                'cancel_reason' => 'Refund: '.trim($reason),
            ]);

            return Refund::create([
                'member_id' => $membership->member_id,
                'membership_id' => $membership->getKey(),
                'amount' => $amount,
                'method' => $method,
                'reason' => trim($reason),
                'recorded_by' => $by->getKey(),
                'refunded_at' => now(),
            ]);
        });
    }

    private function buat(Member $member, MembershipPlan $plan, Carbon $mulai, PaymentMethod $method, int $amount, User $by, ?string $proofPath, ?PromoCode $promo, bool $isActivation, int $kredit = 0, PaymentType $type = PaymentType::Purchase, ?string $note = null): Membership
    {
        $diskon = 0;

        if ($promo) {
            // Kunci baris promo lalu cek ulang: dua pegawai tidak bisa melewati kuota bersamaan.
            $promo = PromoCode::lockForUpdate()->findOrFail($promo->getKey());
            $hasil = app(PromoService::class)->check($promo->code, $plan, $member, $isActivation);

            if (! $hasil->valid) {
                throw new InvalidArgumentException($hasil->message);
            }

            $diskon = $hasil->discount;
        }

        $membership = $member->memberships()->create([
            'membership_plan_id' => $plan->getKey(),
            'starts_on' => $mulai,
            'ends_on' => $mulai->copy()->addDays($plan->duration_days - 1),
            'visits_remaining' => $plan->visit_quota,
            'visits_total' => $plan->visit_quota,
            'credit_amount' => $kredit,
        ]);

        $member->payments()->create([
            'membership_id' => $membership->getKey(),
            'type' => $type,
            'note' => $note,
            'amount' => $amount,
            'original_amount' => $plan->price,
            'discount_amount' => $diskon,
            'promo_code_id' => $promo?->getKey(),
            'method' => $method,
            'proof_path' => $proofPath,
            'recorded_by' => $by->getKey(),
            'paid_at' => now(),
        ]);

        return $membership;
    }
}
