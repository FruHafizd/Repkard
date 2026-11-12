<?php

namespace App\Filament\Resources\Refunds;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Refunds\Pages\ManageRefunds;
use App\Models\Refund;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static ?string $modelLabel = 'Refund';

    protected static ?string $pluralModelLabel = 'Refund';

    protected static ?string $navigationLabel = 'Refund';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('refunded_at', 'desc')
            ->columns([
                TextColumn::make('refunded_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('member.member_number')->label('Nomor member')->placeholder('-')->visibleFrom('lg'),
                TextColumn::make('member.user.name')->label('Member'),
                TextColumn::make('membership.plan.name')->label('Paket')->visibleFrom('md'),
                TextColumn::make('amount')->label('Jumlah')
                    ->formatStateUsing(fn (int $state): string => PaymentResource::rupiah($state))
                    ->sortable(),
                TextColumn::make('method')->label('Metode')->badge()->visibleFrom('md'),
                TextColumn::make('reason')->label('Alasan')->wrap()->visibleFrom('md'),
                TextColumn::make('recorder.name')->label('Dicatat oleh')->visibleFrom('lg'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['member.user', 'membership.plan', 'recorder']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRefunds::route('/'),
        ];
    }
}
