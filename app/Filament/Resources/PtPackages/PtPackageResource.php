<?php

namespace App\Filament\Resources\PtPackages;

use App\Filament\Resources\PtPackages\Pages\CreatePtPackage;
use App\Filament\Resources\PtPackages\Pages\EditPtPackage;
use App\Filament\Resources\PtPackages\Pages\ListPtPackages;
use App\Filament\Resources\PtPackages\Pages\ViewPtPackage;
use App\Filament\Resources\PtPackages\Schemas\PtPackageForm;
use App\Filament\Resources\PtPackages\Schemas\PtPackageInfolist;
use App\Filament\Resources\PtPackages\Tables\PtPackagesTable;
use App\Models\PtPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PtPackageResource extends Resource
{
    protected static ?string $model = PtPackage::class;

    protected static ?string $modelLabel = 'Paket PT';

    protected static ?string $pluralModelLabel = 'Paket PT';

    protected static ?string $navigationLabel = 'Paket PT';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public static function form(Schema $schema): Schema
    {
        return PtPackageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PtPackageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PtPackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPtPackages::route('/'),
            'create' => CreatePtPackage::route('/create'),
            'view' => ViewPtPackage::route('/{record}'),
            'edit' => EditPtPackage::route('/{record}/edit'),
        ];
    }
}
