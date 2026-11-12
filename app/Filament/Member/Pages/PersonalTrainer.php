<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
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

        return [
            'subscriptions' => $member
                ? $member->ptSubscriptions()->with(['package', 'trainer'])->latest('id')->get()
                : collect(),
        ];
    }
}
