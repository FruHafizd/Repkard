<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    public const METHOD_CARD = 'card';

    public const METHOD_MANUAL = 'manual';

    public function __construct(private CardTokenService $tokens) {}

    /**
     * Hanya membaca; tidak menyimpan apa pun.
     */
    public function inspect(string $token): CheckInResult
    {
        $card = $this->tokens->resolve($token);

        if (! $card) {
            return new CheckInResult(false, 'Kartu tidak valid atau sudah tidak berlaku');
        }

        return $this->evaluate($card->member, $card);
    }

    /**
     * Pengecekan untuk check-in manual (tanpa kartu).
     */
    public function inspectMember(Member $member): CheckInResult
    {
        return $this->evaluate($member, null);
    }

    public function confirm(CheckInResult $result, User $staff, string $method = self::METHOD_CARD, ?string $manualReason = null): CheckIn
    {
        // Jangan percaya hasil lama: ulangi seluruh pengecekan di dalam transaksi.
        return DB::transaction(function () use ($result, $staff, $method, $manualReason): CheckIn {
            $member = $result->member ? Member::find($result->member->getKey()) : null;
            $card = $result->card ? MemberCard::find($result->card->getKey()) : null;

            if ($member && $method === self::METHOD_MANUAL) {
                $card = null;
            } elseif ($result->card && (! $card || $card->revoked_at !== null)) {
                return $this->reject(new CheckInResult(false, null, $member), $staff, 'Kartu tidak valid atau sudah tidak berlaku', $method, $manualReason);
            }

            if ($result->membership) {
                Membership::whereKey($result->membership->getKey())->lockForUpdate()->first();
            }

            $fresh = $member
                ? $this->evaluate($member, $card)
                : new CheckInResult(false, 'Kartu tidak valid atau sudah tidak berlaku');

            if (! $fresh->allowed) {
                return $this->reject($fresh, $staff, $fresh->reason, $method, $manualReason);
            }

            if ($fresh->membership->visits_remaining !== null) {
                // Nilai di model bisa basi (snapshot transaksi); kurangi hanya bila kuota di database masih ada.
                $terpakai = Membership::whereKey($fresh->membership->getKey())
                    ->where('visits_remaining', '>', 0)
                    ->decrement('visits_remaining');

                if ($terpakai === 0) {
                    return $this->reject($fresh, $staff, 'Kuota kunjungan habis', $method, $manualReason);
                }
            }

            return $this->record($fresh, $staff, CheckIn::RESULT_ACCEPTED, null, $method, $manualReason);
        });
    }

    public function reject(CheckInResult $result, User $staff, string $reason, string $method = self::METHOD_CARD, ?string $manualReason = null): CheckIn
    {
        return $this->record($result, $staff, CheckIn::RESULT_REJECTED, $reason, $method, $manualReason);
    }

    private function evaluate(?Member $member, ?MemberCard $card): CheckInResult
    {
        if (! $member) {
            return new CheckInResult(false, 'Kartu tidak valid atau sudah tidak berlaku', null, $card);
        }

        if ($member->status !== MemberStatus::Active) {
            return new CheckInResult(false, 'Akun member tidak aktif', $member, $card);
        }

        $membership = $member->currentMembership();

        if (! $membership) {
            return new CheckInResult(false, 'Masa aktif sudah habis', $member, $card);
        }

        if ($membership->isFrozen()) {
            return new CheckInResult(false, 'Membership sedang dibekukan', $member, $card, $membership);
        }

        if ($membership->visits_remaining === 0) {
            return new CheckInResult(false, 'Kuota kunjungan habis', $member, $card, $membership);
        }

        return new CheckInResult(true, null, $member, $card, $membership);
    }

    private function record(CheckInResult $result, User $staff, string $status, ?string $reason, string $method, ?string $manualReason): CheckIn
    {
        return CheckIn::create([
            'member_id' => $result->member?->getKey(),
            'member_card_id' => $result->card?->getKey(),
            'membership_id' => $result->membership?->getKey(),
            'method' => $method,
            'result' => $status,
            'reject_reason' => $reason,
            'manual_reason' => $manualReason,
            'scanned_by' => $staff->getKey(),
            'checked_in_at' => now(),
        ]);
    }
}
