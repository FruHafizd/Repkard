<?php

namespace App\Filament\Resources\Members\Actions;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Filament\Forms\Components\WebcamPhoto;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PromoCode;
use App\Services\MemberCardService;
use App\Services\MemberPhotoService;
use App\Services\MembershipService;
use App\Services\PromoResult;
use App\Services\PromoService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MemberActions
{
    public static function aktifkan(): Action
    {
        return Action::make('aktifkan')
            ->label('Aktifkan')
            ->color('success')
            ->authorize('update')
            ->visible(fn (Member $record): bool => $record->status === MemberStatus::Pending)
            ->modalHeading('Aktifkan member')
            ->modalSubmitActionLabel('Aktifkan')
            ->fillForm(fn (Member $record): array => [
                'plan_id' => $record->selected_plan_id,
                'promo_code' => $record->requested_promo_code,
                'amount' => self::hargaAwal($record, $record->selected_plan_id, $record->requested_promo_code, true),
                'method' => PaymentMethod::Cash->value,
            ])
            ->schema([
                Image::make(fn (Member $record): string => (string) $record->photoUrl(), 'Foto member')
                    ->imageHeight('20rem'),
                Text::make(fn (Member $record): string => $record->user->name.' — '.$record->phone),
                Checkbox::make('face_checked')
                    ->label('Saya sudah mencocokkan wajah dengan foto')
                    ->accepted()
                    ->validationMessages(['accepted' => 'Cocokkan wajah dengan foto terlebih dahulu.']),
                WebcamPhoto::make('photo_webcam')
                    ->label('Ganti foto dari kamera (opsional)'),
                ...self::bayarFields(true),
            ])
            ->action(function (Member $record, array $data): void {
                self::jalankan(function () use ($record, $data): void {
                    if (filled($data['photo_webcam'] ?? null)) {
                        self::gantiFoto($record, $data['photo_webcam']);
                    }

                    app(MembershipService::class)->activate(
                        $record,
                        MembershipPlan::findOrFail($data['plan_id']),
                        self::metode($data['method']),
                        (int) $data['amount'],
                        auth()->user(),
                        $data['proof_path'] ?? null,
                        self::promo($record, $data, true),
                    );

                    Notification::make()->success()->title('Member diaktifkan')->send();
                });
            });
    }

    public static function tolak(): Action
    {
        return Action::make('tolak')
            ->label('Tolak pendaftaran')
            ->color('danger')
            ->authorize('update')
            ->visible(fn (Member $record): bool => $record->status === MemberStatus::Pending)
            ->requiresConfirmation()
            ->modalDescription('Akun, data, dan foto pendaftar akan dihapus permanen.')
            ->schema([
                Textarea::make('reason')->label('Alasan penolakan')->required()->maxLength(500),
            ])
            ->successRedirectUrl(fn (): string => MemberResource::getUrl())
            ->action(function (Member $record, array $data): void {
                if ($record->status !== MemberStatus::Pending) {
                    return;
                }

                Log::info('Pendaftaran member ditolak', [
                    'member_id' => $record->getKey(),
                    'by' => auth()->id(),
                    'reason' => $data['reason'],
                ]);

                DB::transaction(function () use ($record): void {
                    $user = $record->user;
                    // Foto ikut terhapus lewat event forceDeleted di model Member.
                    $record->forceDelete();
                    $user?->delete();
                });

                Notification::make()->success()->title('Pendaftaran ditolak dan dihapus')->send();
            });
    }

    public static function perpanjang(): Action
    {
        return Action::make('perpanjang')
            ->label('Perpanjang')
            ->authorize('update')
            ->visible(fn (Member $record): bool => $record->status === MemberStatus::Active)
            ->modalSubmitActionLabel('Perpanjang')
            ->fillForm(fn (Member $record): array => [
                'plan_id' => $record->selected_plan_id,
                'amount' => $record->selectedPlan?->price,
                'method' => PaymentMethod::Cash->value,
            ])
            ->schema(self::bayarFields(false))
            ->action(function (Member $record, array $data): void {
                self::jalankan(function () use ($record, $data): void {
                    app(MembershipService::class)->renew(
                        $record,
                        MembershipPlan::findOrFail($data['plan_id']),
                        self::metode($data['method']),
                        (int) $data['amount'],
                        auth()->user(),
                        $data['proof_path'] ?? null,
                        self::promo($record, $data, false),
                    );

                    Notification::make()->success()->title('Membership diperpanjang')->send();
                });
            });
    }

    public static function bekukan(): Action
    {
        return Action::make('bekukan')
            ->label('Bekukan')
            ->color('warning')
            ->authorize('update')
            ->visible(fn (Member $record): bool => $record->status === MemberStatus::Active
                && ($ms = $record->currentMembership()) !== null
                && ! $ms->isFrozen())
            ->schema([
                TextInput::make('days')
                    ->label('Lama pembekuan (hari)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(MembershipService::MAX_FREEZE_DAYS)
                    ->required()
                    ->helperText('Maksimal '.MembershipService::MAX_FREEZE_DAYS.' hari. Masa aktif diperpanjang sebanyak itu.'),
            ])
            ->action(function (Member $record, array $data): void {
                self::jalankan(function () use ($record, $data): void {
                    $membership = $record->currentMembership()
                        ?? throw new InvalidArgumentException('Tidak ada membership aktif.');

                    app(MembershipService::class)->freeze($membership, (int) $data['days'], auth()->user());

                    Notification::make()->success()->title('Membership dibekukan')->send();
                });
            });
    }

    public static function cetakKartu(): Action
    {
        return Action::make('cetakKartu')
            ->label('Cetak kartu')
            ->authorize('downloadCard')
            ->visible(fn (Member $record): bool => $record->status === MemberStatus::Active
                && $record->activeCard() !== null)
            ->url(fn (Member $record): string => route('member.card.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function terbitkanUlangKartu(): Action
    {
        return Action::make('terbitkanUlangKartu')
            ->label('Terbitkan ulang kartu')
            ->color('warning')
            ->authorize('reissueCard')
            ->visible(fn (Member $record): bool => $record->status === MemberStatus::Active)
            ->requiresConfirmation()
            ->modalDescription('Kartu lama tidak akan berlaku lagi.')
            ->schema([
                Textarea::make('reason')->label('Alasan (mis. Kartu hilang)')->required()->maxLength(200),
            ])
            ->action(function (Member $record, array $data): void {
                app(MemberCardService::class)->issue($record, auth()->user(), $data['reason']);

                Notification::make()->success()->title('Kartu baru diterbitkan. Kartu lama tidak berlaku lagi.')->send();
            });
    }

    public static function cabutKartu(): Action
    {
        return Action::make('cabutKartu')
            ->label('Cabut kartu')
            ->color('danger')
            ->authorize('revokeCard')
            ->visible(fn (Member $record): bool => $record->activeCard() !== null)
            ->requiresConfirmation()
            ->modalDescription('Kartu tidak bisa dipakai lagi sampai kartu baru diterbitkan.')
            ->schema([
                Textarea::make('reason')->label('Alasan pencabutan')->required()->maxLength(200),
            ])
            ->action(function (Member $record, array $data): void {
                $card = $record->activeCard();

                if ($card) {
                    app(MemberCardService::class)->revoke($card, auth()->user(), $data['reason']);
                }

                Notification::make()->success()->title('Kartu dicabut')->send();
            });
    }

    /**
     * Field bersama untuk aktivasi & perpanjangan: paket, metode, jumlah, bukti.
     *
     * @return array<int, mixed>
     */
    private static function bayarFields(bool $isActivation): array
    {
        return [
            Select::make('plan_id')
                ->label('Paket')
                ->options(fn (): array => MembershipPlan::active()->pluck('name', 'id')->all())
                ->required()
                ->live()
                ->afterStateUpdated(fn ($state, $get, $set, Member $record) => $set('amount', self::hargaAwal($record, $state, $get('promo_code'), $isActivation))),
            TextInput::make('promo_code')
                ->label('Kode promo (opsional)')
                ->maxLength(30)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $get, $set, Member $record) => $set('amount', self::hargaAwal($record, $get('plan_id'), $state, $isActivation)))
                ->helperText(function ($get, Member $record) use ($isActivation): ?string {
                    $hasil = self::cekPromo($record, $get('plan_id'), $get('promo_code'), $isActivation);

                    if (! $hasil) {
                        return null;
                    }

                    return $hasil->valid
                        ? 'Diskon '.self::rupiah($hasil->discount).' → bayar '.self::rupiah($hasil->finalPrice)
                        : $hasil->message;
                }),
            Select::make('method')
                ->label('Metode bayar')
                ->options(PaymentMethod::class)
                ->required(),
            TextInput::make('amount')
                ->label('Jumlah (Rp)')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->required(),
            FileUpload::make('proof_path')
                ->label('Bukti transfer (opsional)')
                ->disk('local')
                ->directory('payment-proofs')
                ->visibility('private')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                ->maxSize(5120),
        ];
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private static function cekPromo(Member $member, mixed $planId, ?string $code, bool $isActivation): ?PromoResult
    {
        $plan = $planId ? MembershipPlan::find($planId) : null;

        if (blank($code) || ! $plan) {
            return null;
        }

        return app(PromoService::class)->check($code, $plan, $member, $isActivation);
    }

    /** Harga yang disarankan: harga paket, atau harga setelah diskon bila kode valid. */
    private static function hargaAwal(Member $member, mixed $planId, ?string $code, bool $isActivation): ?int
    {
        $hasil = self::cekPromo($member, $planId, $code, $isActivation);

        return $hasil?->valid ? $hasil->finalPrice : ($planId ? MembershipPlan::find($planId)?->price : null);
    }

    /** @param array<string, mixed> $data */
    private static function promo(Member $member, array $data, bool $isActivation): ?PromoCode
    {
        $hasil = self::cekPromo($member, $data['plan_id'], $data['promo_code'] ?? null, $isActivation);

        if ($hasil && ! $hasil->valid) {
            throw new InvalidArgumentException($hasil->message);
        }

        return $hasil?->promo;
    }

    private static function metode(PaymentMethod|string $method): PaymentMethod
    {
        return $method instanceof PaymentMethod ? $method : PaymentMethod::from($method);
    }

    private static function gantiFoto(Member $member, string $dataUrl): void
    {
        $service = app(MemberPhotoService::class);
        $lama = $member->photo_path;

        $member->update(['photo_path' => $service->storeFromDataUrl($dataUrl)]);
        $service->delete($lama);
    }

    private static function jalankan(\Closure $callback): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
