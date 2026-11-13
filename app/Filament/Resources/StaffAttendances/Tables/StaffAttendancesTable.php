<?php

namespace App\Filament\Resources\StaffAttendances\Tables;

use App\Enums\UserRole;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class StaffAttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('clock_in_at', 'desc')
            ->deferFilters(false)
            ->columns([
                TextColumn::make('user.name')->label('Nama')->searchable(),
                TextColumn::make('clock_in_at')->label('Tanggal')->date('d M Y')->sortable(),
                ImageColumn::make('clock_in_photo')->label('Foto masuk')
                    ->getStateUsing(fn (StaffAttendance $record): string => route('staff-attendance.photo', [$record, 'masuk']))
                    ->imageSize(40)->circular()->visibleFrom('md'),
                TextColumn::make('masuk')->label('Masuk')
                    ->state(fn (StaffAttendance $record): string => $record->clock_in_at->format('H:i')),
                ImageColumn::make('clock_out_photo')->label('Foto pulang')
                    ->getStateUsing(fn (StaffAttendance $record): ?string => $record->clock_out_photo_path
                        ? route('staff-attendance.photo', [$record, 'pulang'])
                        : null)
                    ->imageSize(40)->circular()->visibleFrom('md'),
                TextColumn::make('pulang')->label('Pulang')
                    ->state(fn (StaffAttendance $record): ?string => $record->clock_out_at?->format('H:i'))
                    ->placeholder('-'),
                TextColumn::make('durasi')->label('Durasi')
                    ->state(fn (StaffAttendance $record): ?string => $record->durationLabel())
                    ->placeholder('-')
                    ->visibleFrom('md'),
                TextColumn::make('keterangan')->label('Keterangan')
                    ->state(fn (StaffAttendance $record): array => array_values(array_filter([
                        $record->is_late ? 'Terlambat' : null,
                        $record->isForgotClockOut() ? 'Lupa pulang' : null,
                    ])))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Terlambat' ? 'danger' : 'warning'),
                IconColumn::make('corrected')->label('Dikoreksi')->boolean()
                    ->trueColor('warning')->falseIcon(Heroicon::OutlinedMinus)->falseColor('gray')
                    ->state(fn (StaffAttendance $record): bool => $record->isCorrected())
                    ->tooltip(fn (StaffAttendance $record): ?string => $record->correction_reason)
                    ->visibleFrom('lg'),
            ])
            ->filters([
                Filter::make('bulan')
                    ->schema([
                        Select::make('bulan')->label('Bulan')
                            ->options(fn (): array => self::monthOptions())
                            ->default(now()->format('Y-m'))
                            ->selectablePlaceholder(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['bulan'] ?? null)) {
                            return $query;
                        }
                        $start = CarbonImmutable::createFromFormat('Y-m-d', $data['bulan'].'-01')->startOfMonth();

                        return $query->whereBetween('clock_in_at', [$start, $start->endOfMonth()]);
                    }),
                SelectFilter::make('user_id')->label('Orang')
                    ->options(fn (): array => User::query()
                        ->role([UserRole::Pegawai->value, UserRole::Trainer->value])
                        ->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (): bool => (bool) auth()->user()?->can('ViewAll:StaffAttendance')),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                self::correctAction(),
            ]);
    }

    /** @return array<string, string> 24 bulan terakhir, mis. '2026-11' => 'November 2026' */
    private static function monthOptions(): array
    {
        $options = [];
        $month = now()->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $options[$month->format('Y-m')] = $month->translatedFormat('F Y');
            $month = $month->subMonthNoOverflow();
        }

        return $options;
    }

    public static function correctAction(): Action
    {
        return Action::make('koreksi')
            ->label('Koreksi')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->authorize('correct')
            ->modalHeading('Koreksi absensi')
            ->modalSubmitActionLabel('Simpan koreksi')
            ->fillForm(fn (StaffAttendance $record): array => [
                'clock_in_at' => $record->clock_in_at,
                'clock_out_at' => $record->clock_out_at,
            ])
            ->schema([
                DateTimePicker::make('clock_in_at')->label('Jam masuk')->seconds(false)->required(),
                DateTimePicker::make('clock_out_at')->label('Jam pulang')->seconds(false)->after('clock_in_at'),
                Textarea::make('reason')->label('Alasan koreksi')->required()->maxLength(500),
            ])
            ->action(function (StaffAttendance $record, array $data): void {
                try {
                    app(AttendanceService::class)->correct(
                        $record,
                        Carbon::parse($data['clock_in_at']),
                        filled($data['clock_out_at'] ?? null) ? Carbon::parse($data['clock_out_at']) : null,
                        $data['reason'],
                        auth()->user(),
                    );
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

                    return;
                }

                Notification::make()->success()->title('Absensi dikoreksi')->send();
            });
    }
}
