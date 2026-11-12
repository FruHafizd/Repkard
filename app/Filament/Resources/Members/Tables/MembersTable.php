<?php

namespace App\Filament\Resources\Members\Tables;

use App\Enums\MemberStatus;
use App\Filament\Resources\Members\Actions\MemberActions;
use App\Models\Member;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'selectedPlan']))
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->getStateUsing(fn (Member $record): ?string => $record->photoUrl())
                    ->imageHeight(48),
                TextColumn::make('member_number')->label('Nomor member')->searchable()->placeholder('-')->visibleFrom('md'),
                TextColumn::make('user.name')->label('Nama')->searchable(),
                TextColumn::make('phone')->label('Nomor HP')->searchable()->visibleFrom('md'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('selectedPlan.name')->label('Paket pilihan')->placeholder('-')->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(MemberStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                MemberActions::aktifkan(),
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    MemberActions::perpanjang(),
                    MemberActions::upgrade(),
                    MemberActions::jualPt(),
                    MemberActions::bekukan(),
                    MemberActions::cetakKartu(),
                    MemberActions::terbitkanUlangKartu(),
                    MemberActions::cabutKartu(),
                    MemberActions::refund(),
                    MemberActions::tolak(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
