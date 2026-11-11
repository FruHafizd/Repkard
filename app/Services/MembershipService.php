<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Events\MemberActivated;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
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
    public function activate(Member $member, MembershipPlan $plan, PaymentMethod $method, int $amount, User $by, ?string $proofPath = null): Membership
    {
        $membership = DB::transaction(function () use ($member, $plan, $method, $amount, $by, $proofPath): Membership {
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

            return $this->buat($member, $plan, now()->startOfDay(), $method, $amount, $by, $proofPath);
        });

        MemberActivated::dispatch($membership->member);

        return $membership;
    }

    public function renew(Member $member, MembershipPlan $plan, PaymentMethod $method, int $amount, User $by, ?string $proofPath = null): Membership
    {
        return DB::transaction(function () use ($member, $plan, $method, $amount, $by, $proofPath): Membership {
            $member = Member::withTrashed()->lockForUpdate()->findOrFail($member->getKey());

            if ($member->status !== MemberStatus::Active) {
                throw new InvalidArgumentException('Hanya member aktif yang bisa diperpanjang.');
            }

            $today = now()->startOfDay();
            $terakhir = $member->memberships()->whereNull('cancelled_at')->orderByDesc('ends_on')->first();

            $mulai = $terakhir && $terakhir->ends_on->gte($today)
                ? $terakhir->ends_on->copy()->addDay()
                : $today;

            return $this->buat($member, $plan, $mulai, $method, $amount, $by, $proofPath);
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

    private function buat(Member $member, MembershipPlan $plan, Carbon $mulai, PaymentMethod $method, int $amount, User $by, ?string $proofPath): Membership
    {
        $membership = $member->memberships()->create([
            'membership_plan_id' => $plan->getKey(),
            'starts_on' => $mulai,
            'ends_on' => $mulai->copy()->addDays($plan->duration_days - 1),
            'visits_remaining' => $plan->visit_quota,
        ]);

        $member->payments()->create([
            'membership_id' => $membership->getKey(),
            'amount' => $amount,
            'method' => $method,
            'proof_path' => $proofPath,
            'recorded_by' => $by->getKey(),
            'paid_at' => now(),
        ]);

        return $membership;
    }
}
