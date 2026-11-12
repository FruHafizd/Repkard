<?php

namespace App\Filament\Pages;

use App\Enums\FollowUpChannel;
use App\Enums\FollowUpOutcome;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Models\Membership;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberMenghilang extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static ?string $title = 'Member Menghilang';

    protected static ?string $navigationLabel = 'Member Menghilang';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected string $view = 'filament.pages.member-menghilang';

    public function table(Table $table): Table
    {
        $warning = (int) config('repkard.inactive_warning_days');
        $danger = (int) config('repkard.inactive_danger_days');
        $today = now()->toDateString();

        return $table
            ->query(fn (): Builder => Member::query()
                ->mulaiMenghilang($warning)
                ->selectSub(
                    Membership::query()
                        ->selectRaw('MAX(ends_on)')
                        ->whereColumn('memberships.member_id', 'members.id')
                        ->whereNull('cancelled_at')
                        ->whereDate('starts_on', '<=', $today)
                        ->whereDate('ends_on', '>=', $today),
                    'berlaku_sampai',
                )
                ->with(['user', 'latestFollowUp.user']))
            ->defaultSort('absent_since')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->getStateUsing(fn (Member $record): ?string => $record->photoUrl())
                    ->imageHeight(40)
                    ->circular(),
                TextColumn::make('user.name')->label('Nama'),
                TextColumn::make('member_number')->label('Nomor member')->placeholder('-')->visibleFrom('md'),
                TextColumn::make('phone')
                    ->label('Nomor HP')
                    ->url(fn (Member $record): string => 'tel:'.$record->phone)
                    ->visibleFrom('md'),
                TextColumn::make('last_visit_at')
                    ->label('Terakhir datang')
                    ->date('d M Y')
                    ->placeholder('Belum pernah')
                    ->visibleFrom('lg'),
                TextColumn::make('days_absent')
                    ->label('Absen')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => "{$state} hari")
                    ->color(fn (int $state): string => $state >= $danger ? 'danger' : 'warning'),
                TextColumn::make('berlaku_sampai')->label('Berlaku sampai')->date('d M Y')->visibleFrom('lg'),
                TextColumn::make('latestFollowUp.contacted_at')
                    ->label('Tindak lanjut terakhir')
                    ->formatStateUsing(fn (Member $record): string => $record->latestFollowUp->contacted_at->format('d M Y').' · '.$record->latestFollowUp->outcome->getLabel())
                    ->placeholder('Belum pernah')
                    ->visibleFrom('lg'),
                TextColumn::make('latestFollowUp.next_contact_on')
                    ->label('Hubungi lagi')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->visibleFrom('xl'),
            ])
            ->filters([
                Filter::make('belum_dihubungi')
                    ->label('Belum dihubungi 7 hari terakhir')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave(
                        'followUps',
                        fn (Builder $q) => $q->where('contacted_at', '>=', now()->subDays(7)->startOfDay()),
                    )),
                Filter::make('ambang_bahaya')
                    ->label("≥ {$danger} hari")
                    ->query(fn (Builder $query): Builder => $query->whereRaw(
                        'COALESCE((SELECT MAX(checked_in_at) FROM check_ins WHERE check_ins.member_id = members.id AND result = ?), members.activated_at) <= ?',
                        ['accepted', now()->subDays($danger)->endOfDay()],
                    )),
            ])
            ->recordUrl(fn (Member $record): string => MemberResource::getUrl('view', ['record' => $record]))
            ->recordActions([$this->catatTindakLanjut()])
            ->emptyStateHeading('Tidak ada member yang mulai menghilang');
    }

    private function catatTindakLanjut(): Action
    {
        return Action::make('catatTindakLanjut')
            ->label('Catat tindak lanjut')
            ->icon(Heroicon::OutlinedPhone)
            ->schema([
                Select::make('channel')->label('Cara menghubungi')->options(FollowUpChannel::class)->required(),
                Select::make('outcome')->label('Hasil')->options(FollowUpOutcome::class)->required(),
                Textarea::make('note')->label('Catatan')->rows(3)->maxLength(1000),
                DatePicker::make('next_contact_on')->label('Hubungi lagi tanggal')->minDate(now()->startOfDay()),
            ])
            ->action(function (Member $record, array $data): void {
                $record->followUps()->create([
                    'user_id' => auth()->id(),
                    'contacted_at' => now(),
                    'channel' => $data['channel'],
                    'outcome' => $data['outcome'],
                    'note' => $data['note'] ?? null,
                    'next_contact_on' => $data['next_contact_on'] ?? null,
                ]);

                Notification::make()->success()->title('Tindak lanjut dicatat')->send();
            });
    }
}
