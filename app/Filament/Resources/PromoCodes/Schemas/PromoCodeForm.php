<?php

namespace App\Filament\Resources\PromoCodes\Schemas;

use App\Enums\DiscountType;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PromoCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->maxLength(30)
                    ->regex('/^[A-Za-z0-9-]+$/')
                    ->validationMessages(['regex' => 'Kode hanya boleh huruf, angka, dan tanda -.'])
                    ->mutateStateForValidationUsing(fn (?string $state): string => strtoupper(trim((string) $state)))
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper(trim($state)))
                    ->unique(ignoreRecord: true)
                    ->helperText('Otomatis disimpan huruf besar.'),
                TextInput::make('name')->label('Nama promo')->required()->maxLength(255),
                Select::make('discount_type')
                    ->label('Jenis diskon')
                    ->options(DiscountType::class)
                    ->default(DiscountType::Percent->value)
                    ->required()
                    ->live(),
                TextInput::make('discount_value')
                    ->label('Nilai diskon')
                    ->helperText('Persen: 1-100. Potongan nominal: jumlah rupiah.')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->rule(fn ($get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if ($get('discount_type') === DiscountType::Percent->value && (int) $value > 100) {
                            $fail('Diskon persen tidak boleh lebih dari 100.');
                        }
                    }),
                DatePicker::make('starts_on')->label('Mulai berlaku')->helperText('Kosong = tanpa batas.'),
                DatePicker::make('ends_on')->label('Berakhir')->helperText('Kosong = tanpa batas.')->afterOrEqual('starts_on'),
                TextInput::make('max_uses')->label('Maks. pemakaian total')->numeric()->integer()->minValue(1)->helperText('Kosong = tanpa batas.'),
                TextInput::make('max_uses_per_member')->label('Maks. pemakaian per member')->numeric()->integer()->minValue(1)->default(1)->required(),
                Select::make('plans')
                    ->label('Berlaku untuk paket')
                    ->relationship('plans', 'name')
                    ->multiple()
                    ->preload()
                    ->helperText('Kosong = berlaku untuk semua paket.'),
                Toggle::make('new_members_only')->label('Khusus member baru (aktivasi pertama)'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]);
    }
}
