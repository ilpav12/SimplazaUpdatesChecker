<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalAddonResource\Pages;
use App\Filament\Resources\LocalAddonResource\RelationManagers;
use App\Models\LocalAddon;
use App\Models\RemoteAddon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LocalAddonResource extends Resource
{
    protected static ?string $model = LocalAddon::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 2;

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
                    ->formatStateUsing(function (LocalAddon $record): string {
                        $version = "v$record->version";
                        $version .= $record->remoteAddon
                            ? " -> v{$record->remoteAddon->version}"
                            : ' -> unknown';
                        return $version;
                    })
                    ->tooltip(fn (LocalAddon $record): string => $record->remoteAddon?->details ?? 'No matching remote addon')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\TernaryFilter::make('is_excluded')
                    ->default(false),
                Tables\Filters\TernaryFilter::make('is_updated'),
            ])
            ->actions([
                Tables\Actions\Action::make('change_remote_addon')
                    ->form([
                        Forms\Components\Select::make('remote_addon_id')
                            ->relationship('remoteAddon', 'title')
                            ->getOptionLabelFromRecordUsing(fn (RemoteAddon $record) => $record->details)
                            ->searchable(['title', 'author'])
                            ->required(),
                    ])
                    ->action(function (array $data, LocalAddon $record): void {
                        $record->remote_addon_id = $data['remote_addon_id'];
                        $record->is_updated = version_compare(rtrim($record->version, ".0"), rtrim($record->remoteAddon->version, ".0"), '>=');
                        $record->save();
                        Notification::make()
                            ->title('Remote addon changed')
                            ->body("{$record->details} remote addon changed to {$record->remoteAddon->details}")
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (LocalAddon $record): string => "Change remote addon for {$record->details}"),
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
