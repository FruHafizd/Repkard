<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PtPackage;
use App\Models\PtSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PtService
{
    public function sell(Member $member, PtPackage $package, User $trainer, PaymentMethod $method, int $amount, User $by): PtSubscription
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Jumlah bayar tidak boleh negatif.');
        }

        return DB::transaction(function () use ($member, $package, $trainer, $method, $amount, $by): PtSubscription {
            $member = Member::query()->lockForUpdate()->findOrFail($member->getKey());
            $package = PtPackage::query()->findOrFail($package->getKey());

            if ($member->status !== MemberStatus::Active || $member->currentMembership() === null) {
                throw new InvalidArgumentException('PT hanya untuk member dengan membership aktif.');
            }

            if (! $package->is_active) {
                throw new InvalidArgumentException('Paket PT ini tidak aktif.');
            }

            $this->assertTrainer($trainer);

            $today = now()->startOfDay();

            $subscription = PtSubscription::create([
                'member_id' => $member->getKey(),
                'pt_package_id' => $package->getKey(),
                'trainer_id' => $trainer->getKey(),
                'sessions_total' => $package->sessions_count,
                'sessions_remaining' => $package->sessions_count,
                'starts_on' => $today->toDateString(),
                'expires_on' => $today->copy()->addDays($package->validity_days - 1)->toDateString(),
                'created_by' => $by->getKey(),
            ]);

            Payment::create([
                'member_id' => $member->getKey(),
                'membership_id' => null,
                'pt_subscription_id' => $subscription->getKey(),
                'type' => PaymentType::Pt,
                'original_amount' => $package->price,
                'discount_amount' => 0,
                'amount' => $amount,
                'method' => $method,
                'recorded_by' => $by->getKey(),
                'paid_at' => now(),
            ]);

            return $subscription;
        });
    }

    public function changeTrainer(PtSubscription $subscription, User $trainer, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan ganti trainer wajib diisi.');
        }

        $this->assertTrainer($trainer);

        DB::transaction(function () use ($subscription, $trainer): void {
            $subscription = PtSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if ($subscription->isCancelled()) {
                throw new InvalidArgumentException('Paket PT ini sudah dibatalkan.');
            }

            $subscription->update(['trainer_id' => $trainer->getKey()]);
        });
    }

    /** Tidak otomatis refund: uang dikembalikan lewat proses terpisah. */
    public function cancel(PtSubscription $subscription, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        DB::transaction(function () use ($subscription, $reason): void {
            $subscription = PtSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if ($subscription->isCancelled()) {
                throw new InvalidArgumentException('Paket PT ini sudah dibatalkan.');
            }

            $subscription->update(['cancelled_at' => now(), 'cancel_reason' => $reason]);
        });
    }

    private function assertTrainer(User $trainer): void
    {
        if (! $trainer->is_active || ! $trainer->hasRole(UserRole::Trainer->value)) {
            throw new InvalidArgumentException('Trainer harus pengguna aktif dengan role trainer.');
        }
    }
}
