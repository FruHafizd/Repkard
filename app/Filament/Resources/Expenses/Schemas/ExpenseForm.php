<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Models\ExpenseCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('expense_category_id')->label('Kategori')
                    ->options(fn (): array => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->required(),
                TextInput::make('amount')->label('Jumlah (Rp)')->required()->numeric()->integer()->minValue(1),
                DatePicker::make('spent_on')->label('Tanggal')->required()->default(now())->maxDate(now()),
                TextInput::make('description')->label('Keterangan')->required()->maxLength(255),
                FileUpload::make('proof_path')->label('Bukti (foto / PDF)')
                    ->helperText('Opsional. Gambar atau PDF, maksimal 5 MB.')
                    ->disk('local')
                    ->directory('expense-proofs')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(5120)
                    ->columnSpanFull(),
            ]);
    }
}
