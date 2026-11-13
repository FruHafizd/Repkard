<?php

namespace App\Filament\Resources\Payments;

use App\Enums\MenuGroup;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = MenuGroup::Keuangan;

    protected static ?int $navigationSort = 2;

    public static function rupiah(int|string|null $amount): string
    {
        return 'Rp '.number_format((int) $amount, 0, ',', '.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        // Pembayaran hanya lahir dari aktivasi / perpanjangan.
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['member.user', 'membership.plan', 'ptSubscription.package', 'ptSubscription.trainer', 'recorder', 'promoCode']);
        $user = auth()->user();

        // Tanpa izin "lihat semua", hanya transaksi yang dicatat sendiri.
        if (! $user?->can('ViewAll:Payment')) {
            $query->where('recorded_by', $user?->getKey());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
