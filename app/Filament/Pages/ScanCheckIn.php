<?php

namespace App\Filament\Pages;

use App\Models\CheckIn;
use App\Models\Member;
use App\Services\CheckInResult;
use App\Services\CheckInService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;

class ScanCheckIn extends Page
{
    use HasPageShield;

    protected static ?string $title = 'Scan Check-in';

    protected static ?string $navigationLabel = 'Scan Check-in';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected string $view = 'filament.pages.scan-check-in';

    private const MAX_SCAN_PER_MENIT = 60;

    /** Hanya token yang disimpan dari browser; semuanya diperiksa ulang di server setiap langkah. */
    public ?string $token = null;

    #[Locked]
    public ?int $manualMemberId = null;

    #[Locked]
    public ?string $manualReason = null;

    /** Alasan penolakan yang sedang ditampilkan (panel merah). */
    public ?string $rejectedReason = null;

    public function scan(string $token): void
    {
        $this->resetPanel();
        $token = trim($token);

        if ($token === '') {
            return;
        }

        $key = 'scan-checkin:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, self::MAX_SCAN_PER_MENIT)) {
            Notification::make()->danger()->title('Terlalu banyak scan, coba lagi sebentar lagi')->send();

            return;
        }

        RateLimiter::hit($key, 60);

        $result = app(CheckInService::class)->inspect($token);

        if (! $result->allowed) {
            $this->tolakOtomatis($result);

            return;
        }

        $this->token = $token;
    }

    public function konfirmasi(): void
    {
        $result = $this->inspectSaatIni();

        if (! $result) {
            return;
        }

        $checkIn = app(CheckInService::class)->confirm($result, auth()->user(), $this->metode(), $this->manualReason);
        $reason = $checkIn->reject_reason;
        $accepted = $checkIn->result === CheckIn::RESULT_ACCEPTED;

        $this->resetPanel();

        if ($accepted) {
            Notification::make()->success()->title('Check-in berhasil dicatat')->send();
        } else {
            $this->rejectedReason = $reason;
        }
    }

    public function tolakWajah(): void
    {
        $result = $this->inspectSaatIni();

        if (! $result) {
            return;
        }

        app(CheckInService::class)->reject($result, auth()->user(), 'Wajah tidak cocok', $this->metode(), $this->manualReason);

        $this->resetPanel();
        $this->rejectedReason = 'Wajah tidak cocok';
    }

    /**
     * Hasil pengecekan terkini untuk panel (hanya membaca).
     */
    public function currentResult(): ?CheckInResult
    {
        $service = app(CheckInService::class);

        if ($this->manualMemberId) {
            $member = Member::find($this->manualMemberId);

            return $member ? $service->inspectMember($member) : null;
        }

        return $this->token ? $service->inspect($this->token) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manual')
                ->label('Check-in manual')
                ->color('gray')
                ->schema([
                    Select::make('member_id')
                        ->label('Member')
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(fn (string $search): array => Member::query()
                            ->with('user')
                            ->where(fn ($q) => $q
                                ->where('phone', 'like', "%{$search}%")
                                ->orWhere('member_number', 'like', "%{$search}%")
                                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")))
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn (Member $m): array => [$m->getKey() => "{$m->user?->name} ({$m->member_number})"])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Member::with('user')->find($value)?->user?->name),
                    Textarea::make('reason')
                        ->label('Alasan check-in manual')
                        ->placeholder('Contoh: Kartu ketinggalan')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->resetPanel();
                    $service = app(CheckInService::class);
                    $member = Member::findOrFail($data['member_id']);
                    $result = $service->inspectMember($member);

                    if (! $result->allowed) {
                        $service->reject($result, auth()->user(), $result->reason, CheckInService::METHOD_MANUAL, $data['reason']);
                        $this->rejectedReason = $result->reason;

                        return;
                    }

                    $this->manualMemberId = $member->getKey();
                    $this->manualReason = $data['reason'];
                }),
        ];
    }

    private function metode(): string
    {
        return $this->manualMemberId ? CheckInService::METHOD_MANUAL : CheckInService::METHOD_CARD;
    }

    private function inspectSaatIni(): ?CheckInResult
    {
        $result = $this->currentResult();

        if (! $result) {
            return null;
        }

        if (! $result->allowed) {
            $this->tolakOtomatis($result);

            return null;
        }

        return $result;
    }

    private function tolakOtomatis(CheckInResult $result): void
    {
        app(CheckInService::class)->reject($result, auth()->user(), $result->reason, $this->metode(), $this->manualReason);

        $reason = $result->reason;
        $this->resetPanel();
        $this->rejectedReason = $reason;
    }

    private function resetPanel(): void
    {
        $this->token = null;
        $this->manualMemberId = null;
        $this->manualReason = null;
        $this->rejectedReason = null;
    }
}
