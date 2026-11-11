<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Membership;

final readonly class CheckInResult
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?Member $member = null,
        public ?MemberCard $card = null,
        public ?Membership $membership = null,
    ) {}
}
