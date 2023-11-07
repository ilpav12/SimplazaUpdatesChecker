<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalAddonResource\Pages;
use App\Filament\Resources\LocalAddonResource\RelationManagers;
use App\Models\LocalAddon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LocalAddonResource extends Resource
{
    protected static ?string $model = LocalAddon::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('remoteAddon.title')
                    ->default('No matching remote addon')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_updated')
                    ->default('')
                    ->icon(fn (bool|string $state): string => match ($state) {
                        true => 'heroicon-o-check-circle',
                        false => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (bool|string $state): string => match ($state) {
                        true => 'success',
                        false => 'danger',
                        default => 'info',
                    }),
                Tables\Columns\ToggleColumn::make('is_excluded'),
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocalAddons::route('/'),
        ];
    }
}
