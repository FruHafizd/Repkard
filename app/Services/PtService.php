<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PtSessionStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PtPackage;
use App\Models\PtSession;
use App\Models\PtSubscription;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

    public function schedule(PtSubscription $subscription, Carbon $at, int $duration, User $by): PtSession
    {
        if ($duration < 15 || $duration > 480) {
            throw new InvalidArgumentException('Durasi sesi harus antara 15 dan 480 menit.');
        }

        return DB::transaction(function () use ($subscription, $at, $duration, $by): PtSession {
            $subscription = PtSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if (! $subscription->isUsable()) {
                throw new InvalidArgumentException('Paket PT ini tidak bisa dipakai (habis, kedaluwarsa, atau dibatalkan).');
            }

            if ($at->lt(now())) {
                throw new InvalidArgumentException('Jadwal tidak boleh di masa lalu.');
            }

            if ($at->toDateString() > $subscription->expires_on->toDateString()) {
                throw new InvalidArgumentException('Jadwal melewati masa berlaku paket PT.');
            }

            $terjadwal = $subscription->sessions()->where('status', PtSessionStatus::Scheduled->value)->count();

            if ($subscription->sessions_remaining - $terjadwal < 1) {
                throw new InvalidArgumentException('Sisa sesi sudah terjadwal semua.');
            }

            $end = $at->copy()->addMinutes($duration);

            if ($this->overlaps(PtSession::query()->where('trainer_id', $subscription->trainer_id), $at, $end)) {
                throw new InvalidArgumentException('Trainer sudah punya sesi di jam tersebut.');
            }

            $memberSessions = PtSession::query()
                ->whereHas('subscription', fn (Builder $q) => $q->where('member_id', $subscription->member_id));

            if ($this->overlaps($memberSessions, $at, $end)) {
                throw new InvalidArgumentException('Member sudah punya sesi PT di jam tersebut.');
            }

            return PtSession::create([
                'pt_subscription_id' => $subscription->getKey(),
                'trainer_id' => $subscription->trainer_id,
                'scheduled_at' => $at,
                'duration_minutes' => $duration,
                'status' => PtSessionStatus::Scheduled,
                'created_by' => $by->getKey(),
            ]);
        });
    }

    /** Selesai / tidak datang: memotong 1 sesi dan mengunci komisi trainer saat ini. */
    public function finish(PtSession $session, PtSessionStatus $status, User $by, ?string $note = null): PtSession
    {
        if (! in_array($status, [PtSessionStatus::Completed, PtSessionStatus::NoShow], true)) {
            throw new InvalidArgumentException('Status akhir harus Selesai atau Tidak datang.');
        }

        return DB::transaction(function () use ($session, $status, $by, $note): PtSession {
            $subscription = PtSubscription::query()->lockForUpdate()->findOrFail($session->pt_subscription_id);
            $session = PtSession::query()->lockForUpdate()->findOrFail($session->getKey());

            if ($session->status !== PtSessionStatus::Scheduled) {
                throw new InvalidArgumentException('Sesi ini sudah ditandai.');
            }

            if ($subscription->sessions_remaining < 1) {
                throw new InvalidArgumentException('Sisa sesi paket PT sudah habis.');
            }

            $subscription->decrement('sessions_remaining');

            $session->update([
                'status' => $status,
                'commission_amount' => (int) TrainerProfile::query()->where('user_id', $session->trainer_id)->value('commission_per_session'),
                'finished_at' => now(),
                'finished_by' => $by->getKey(),
                'note' => $note,
            ]);

            return $session;
        });
    }

    /** Tidak memotong sesi dan tidak ada komisi. (Bernama cancelSession karena cancel() sudah dipakai untuk paket PT.) */
    public function cancelSession(PtSession $session, User $by, string $reason): PtSession
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($session, $by, $reason): PtSession {
            $session = PtSession::query()->lockForUpdate()->findOrFail($session->getKey());

            if ($session->status !== PtSessionStatus::Scheduled) {
                throw new InvalidArgumentException('Hanya sesi terjadwal yang bisa dibatalkan.');
            }

            $session->update([
                'status' => PtSessionStatus::Cancelled,
                'cancel_reason' => $reason,
                'finished_at' => now(),
                'finished_by' => $by->getKey(),
            ]);

            return $session;
        });
    }

    /** Ada sesi terjadwal/selesai yang tumpang tindih dengan [$start, $end)? Sesi yang mulai tepat saat yang lain berakhir tidak bentrok. */
    private function overlaps(Builder $sessions, Carbon $start, Carbon $end): bool
    {
        return $sessions
            ->whereIn('status', [PtSessionStatus::Scheduled->value, PtSessionStatus::Completed->value])
            ->where('scheduled_at', '<', $end)
            ->where('scheduled_at', '>', $start->copy()->subMinutes(480))
            ->get()
            ->contains(fn (PtSession $s): bool => $s->scheduled_at->copy()->addMinutes($s->duration_minutes)->gt($start));
    }

    private function assertTrainer(User $trainer): void
    {
        if (! $trainer->is_active || ! $trainer->hasRole(UserRole::Trainer->value)) {
            throw new InvalidArgumentException('Trainer harus pengguna aktif dengan role trainer.');
        }
    }
}
