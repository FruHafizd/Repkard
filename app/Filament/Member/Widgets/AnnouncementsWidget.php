<?php

namespace App\Filament\Member\Widgets;

use App\Enums\MemberStatus;
use App\Models\Announcement;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class AnnouncementsWidget extends Widget
{
    protected string $view = 'filament.member.widgets.announcements';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -20;

    public static function canView(): bool
    {
        $status = Filament::auth()->user()?->member?->status;

        return in_array($status, [MemberStatus::Pending, MemberStatus::Active], true)
            && Announcement::query()->published()->exists();
    }

    protected function getViewData(): array
    {
        return [
            'announcements' => Announcement::query()->published()->limit(3)->get(),
        ];
    }
}
