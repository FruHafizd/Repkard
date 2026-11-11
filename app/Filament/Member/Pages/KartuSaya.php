<?php

namespace App\Filament\Member\Pages;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Services\CardTokenService;
use BackedEnum;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class KartuSaya extends Page
{
    protected string $view = 'filament.member.pages.kartu-saya';

    protected static ?string $title = 'Kartu Saya';

    protected static ?string $slug = 'kartu-saya';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected function getViewData(): array
    {
        /** @var Member|null $member */
        $member = Filament::auth()->user()?->member;
        $card = $member?->status === MemberStatus::Active ? $member->activeCard() : null;

        if (! $card) {
            return ['member' => $member, 'card' => null, 'qr' => null];
        }

        $qr = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 10,
            'outputBase64' => true,
        ])))->render(app(CardTokenService::class)->make($card));

        return ['member' => $member->load('user'), 'card' => $card, 'qr' => $qr];
    }
}
