<?php

namespace App\Listeners;

use App\Events\MemberActivated;
use App\Models\User;
use App\Services\MemberCardService;

class IssueCardOnActivation
{
    public function __construct(private MemberCardService $cards) {}

    public function handle(MemberActivated $event): void
    {
        $member = $event->member;

        if ($member->photo_path && ! $member->activeCard()) {
            $this->cards->issue($member, User::find($member->activated_by));
        }
    }
}
