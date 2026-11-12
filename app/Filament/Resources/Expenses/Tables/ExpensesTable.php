<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('spent_on', 'desc')
            ->columns([
                TextColumn::make('spent_on')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('description')->label('Keterangan')->searchable()->visibleFrom('md'),
                TextColumn::make('amount')->label('Jumlah')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state))
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Total (tanpa yang dibatalkan)')
                            ->query(fn (QueryBuilder $query): QueryBuilder => $query->whereNull('cancelled_at'))
                            ->formatStateUsing(fn ($state): string => PaymentResource::rupiah($state)),
                    ),
                TextColumn::make('recorder.name')->label('Dicatat oleh')->visibleFrom('lg'),
                IconColumn::make('has_proof')->label('Bukti')->boolean()
                    ->state(fn (Expense $record): bool => $record->proof_path !== null)
                    ->visibleFrom('md'),
                TextColumn::make('status')->label('Status')
                    ->state(fn (Expense $record): string => $record->isCancelled() ? 'Dibatalkan' : 'Tercatat')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Dibatalkan' ? 'danger' : 'success'),
            ])
            ->filters([
                Filter::make('bulan')
                    ->schema([
                        Select::make('bulan')->label('Bulan')
                            ->options(fn (): array => self::monthOptions()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['bulan'] ?? null)) {
                            return $query;
                        }
                        $start = CarbonImmutable::createFromFormat('Y-m-d', $data['bulan'].'-01')->startOfMonth();

                        return $query->whereBetween('spent_on', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
                    }),
                SelectFilter::make('expense_category_id')->label('Kategori')
                    ->options(fn (): array => ExpenseCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::cancelAction(),
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

    public static function cancelAction(): Action
    {
        return Action::make('batalkan')
            ->label('Batalkan')
            ->color('danger')
            ->requiresConfirmation()
            ->authorize('cancel')
            ->schema([
                Textarea::make('reason')->label('Alasan pembatalan')->required()->maxLength(500),
            ])
            ->action(function (Expense $record, array $data): void {
                $record->update([
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                    'cancel_reason' => $data['reason'],
                ]);

                Notification::make()->success()->title('Pengeluaran dibatalkan')->send();
            });
    }
}
