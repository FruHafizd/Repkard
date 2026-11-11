<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MemberCardService
{
    /**
     * Terbitkan kartu baru; kartu aktif lama dicabut. Versi = versi terakhir + 1.
     */
    public function issue(Member $member, ?User $by, ?string $reason = null): MemberCard
    {
        return DB::transaction(function () use ($member, $by, $reason): MemberCard {
            // Kunci baris member agar dua penerbitan bersamaan tidak berebut nomor versi.
            Member::withTrashed()->lockForUpdate()->findOrFail($member->getKey());

            $member->cards()->whereNull('revoked_at')->update([
                'revoked_at' => now(),
                'revoked_by' => $by?->getKey(),
                'revoke_reason' => $reason ? 'Diganti kartu baru: '.$reason : 'Diganti kartu baru',
            ]);

            return $member->cards()->create([
                'version' => ((int) $member->cards()->max('version')) + 1,
                'issued_at' => now(),
                'issued_by' => $by?->getKey(),
            ]);
        });
    }

    public function revoke(MemberCard $card, User $by, string $reason): void
    {
        $card->update([
            'revoked_at' => now(),
            'revoked_by' => $by->getKey(),
            'revoke_reason' => $reason,
        ]);
    }
}
