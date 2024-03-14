<?php

namespace App\Filament\Resources;

use App\Enums\IsRecommended;
use App\Filament\Resources\RemoteAddonResource\Pages;
use App\Filament\Resources\RemoteAddonResource\Pages\ListRemoteAddons;
use App\Filament\Resources\RemoteAddonResource\RelationManagers;
use App\Models\RemoteAddon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class RemoteAddonResource extends Resource
{
    protected static ?string $model = RemoteAddon::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->description('Last check: ' . cache('lastCheck'))
            ->poll('1s')    // trick force to update the description
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->icon(fn (RemoteAddon $remoteAddon): ?string => is_null($remoteAddon->description) ? null : 'heroicon-o-information-circle')
                    ->iconPosition(IconPosition::After)
                    ->iconColor('info')
                    ->action(
                        Tables\Actions\Action::make('info')
                            ->icon('heroicon-o-information-circle')
                            ->color('info')
                            ->disabled(fn (RemoteAddon $remoteAddon): bool => is_null($remoteAddon->description))
                            ->hidden(fn (RemoteAddon $remoteAddon): bool => is_null($remoteAddon->description))
                            ->requiresConfirmation()
                            ->modalDescription(fn(RemoteAddon $remoteAddon): HtmlString => new HtmlString($remoteAddon->description))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalAlignment(Alignment::Left)
                    )
                    ->extraAttributes(
                        fn (RemoteAddon $remoteAddon): array => is_null($remoteAddon->description)
                            ? ['class' => 'cursor-default']
                            : []
                    )
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_recommended')
                    ->badge()
                    ->action(
                        Tables\Actions\Action::make('recommendation')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->color('danger')
                            ->disabled(fn (RemoteAddon $remoteAddon): bool => is_null($remoteAddon->warning))
                            ->requiresConfirmation()
                            ->modalDescription(fn (RemoteAddon $remoteAddon): HtmlString => new HtmlString($remoteAddon->warning))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalAlignment(Alignment::Left),
                    )
                    ->extraAttributes(
                        fn (RemoteAddon $remoteAddon): array => is_null($remoteAddon->warning)
                            ? ['class' => 'cursor-default']
                            : []
                    )
                    ->placeholder('No Conflicts')
                    ->label('Recommended')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->date('F j, Y')
                    ->label('Published')
                    ->toggleable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_recommended')
                    ->options(IsRecommended::toArray())
                    ->indicator('Recommendation')
                    ->placeholder('Any')
                    ->label('Recommendation'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (RemoteAddon $remoteAddon) => $remoteAddon->page)
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn (RemoteAddon $remoteAddon) => $remoteAddon->torrent)
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->action(function (ListRemoteAddons $livewire, Collection $remoteAddons) {
                        $remoteAddons->each(function (RemoteAddon $remoteAddon) use ($livewire) {
                            $livewire->js("window.open('$remoteAddon->torrent', '_blank')");
                        });
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('refresh')
                    ->label('Refresh')
                    ->tooltip('This will get the latest list of addons from the Simplaza website.')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        $updatedAddons = RemoteAddon::saveRemoteAddons();
                        Notification::make()
                            ->title($updatedAddons == 0
                                ? 'All addons are up to date'
                                : "$updatedAddons addons have been updated")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateActions([
                //
            ])
            ->recordUrl(null)
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('published_at', 'desc'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRemoteAddons::route('/'),
        ];
    }
}
