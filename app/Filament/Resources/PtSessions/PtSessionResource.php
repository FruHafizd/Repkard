<?php

namespace App\Filament\Resources\PtSessions;

use App\Enums\UserRole;
use App\Filament\Resources\PtSessions\Pages\CreatePtSession;
use App\Filament\Resources\PtSessions\Pages\ListPtSessions;
use App\Filament\Resources\PtSessions\Schemas\PtSessionForm;
use App\Filament\Resources\PtSessions\Tables\PtSessionsTable;
use App\Models\PtSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PtSessionResource extends Resource
{
    protected static ?string $model = PtSession::class;

    protected static ?string $modelLabel = 'Sesi PT';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole(UserRole::Trainer->value) ? 'Sesi Saya' : 'Jadwal PT';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Jadwal PT';
    }

    public static function getTitleCaseModelLabel(): string
    {
        return 'Sesi PT';
    }

    public static function form(Schema $schema): Schema
    {
        return PtSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PtSessionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['subscription.member.user', 'trainer']);
        $user = auth()->user();

        // Tanpa izin "lihat semua", trainer hanya melihat sesinya sendiri.
        if (! $user?->can('ViewAll:PtSession')) {
            $query->where('trainer_id', $user?->getKey());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPtSessions::route('/'),
            'create' => CreatePtSession::route('/create'),
        ];
    }
}
