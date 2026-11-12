<?php

namespace App\Filament\Member\Pages;

use App\Enums\PtSessionStatus;
use App\Models\Member;
use App\Models\PtSession;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PersonalTrainer extends Page
{
    protected string $view = 'filament.member.pages.personal-trainer';

    protected static ?string $title = 'Personal Trainer';

    protected static ?string $slug = 'personal-trainer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected function getViewData(): array
    {
        /** @var Member|null $member */
        $member = Filament::auth()->user()?->member;

        $sessions = fn () => PtSession::query()
            ->whereHas('subscription', fn ($q) => $q->where('member_id', $member?->getKey()))
            ->with('trainer');

        return [
            'subscriptions' => $member
                ? $member->ptSubscriptions()->with(['package', 'trainer'])->latest('id')->get()
                : collect(),
            'upcoming' => $member
                ? $sessions()->where('status', PtSessionStatus::Scheduled->value)->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->get()
                : collect(),
            'history' => $member
                ? $sessions()->where('status', '!=', PtSessionStatus::Scheduled->value)->orderByDesc('scheduled_at')->limit(50)->get()
                : collect(),
        ];
    }
}
