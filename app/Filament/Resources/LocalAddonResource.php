<?php

namespace App\Filament\Resources;

use App\Enums\IsRecommended;
use App\Filament\Resources\LocalAddonResource\Pages;
use App\Filament\Resources\LocalAddonResource\Pages\ListLocalAddons;
use App\Filament\Resources\LocalAddonResource\RelationManagers;
use App\Models\LocalAddon;
use App\Models\RemoteAddon;
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

class LocalAddonResource extends Resource
{
    protected static ?string $model = LocalAddon::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->icon(fn (LocalAddon $localAddon): ?string => is_null($localAddon->remoteAddon?->description) ? null : 'heroicon-o-information-circle')
                    ->iconPosition(IconPosition::After)
                    ->iconColor('info')
                    ->action(
                        Tables\Actions\Action::make('info')
                            ->icon('heroicon-o-information-circle')
                            ->color('info')
                            ->disabled(fn (LocalAddon $localAddon): bool => is_null($localAddon->remoteAddon?->description))
                            ->hidden(fn (LocalAddon $localAddon): bool => is_null($localAddon->remoteAddon?->description))
                            ->requiresConfirmation()
                            ->modalDescription(fn (LocalAddon $localAddon): HtmlString => new HtmlString($localAddon->remoteAddon?->description))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalAlignment(Alignment::Left)
                    )
                    ->extraAttributes(
                        fn (LocalAddon $localAddon): array => is_null($localAddon->remoteAddon?->description)
                            ? ['class' => 'cursor-default']
                            : []
                    )
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
                    ->icon(fn (bool|string $state): string => match ($state) {
                        true => 'heroicon-o-check-circle',
                        false => 'heroicon-o-x-circle',
                    })
                    ->color(fn (bool|string $state): string => match ($state) {
                        true => 'success',
                        false => 'danger',
                    })
                    ->placeholder('Choose a matching addon'),
                Tables\Columns\ToggleColumn::make('is_excluded'),
                Tables\Columns\TextColumn::make('remoteAddon.is_recommended')
                    ->badge()
                    ->action(
                        Tables\Actions\Action::make('warning')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->color('danger')
                            ->disabled(fn (LocalAddon $localAddon): bool => is_null($localAddon->remoteAddon?->warning))
                            ->requiresConfirmation()
                            ->modalDescription(fn (LocalAddon $localAddon): HtmlString => new HtmlString($localAddon->remoteAddon?->warning))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalAlignment(Alignment::Left),
                    )
                    ->extraAttributes(
                        fn (LocalAddon $localAddon): array => is_null($localAddon->remoteAddon?->warning)
                            ? ['class' => 'cursor-default']
                            : []
                    )
                    ->placeholder('Choose a matching addon')
                    ->label('Recommended')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('remoteAddon.details')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_excluded')
                    ->default(false),
                Tables\Filters\TernaryFilter::make('is_updated')
                    ->queries(
                        true: function (Builder $query) {
                            return $query->whereExists(function (\Illuminate\Database\Query\Builder $query) {
                                return $query->select('*')
                                    ->from('remote_addons')
                                    ->whereColumn('remote_addons.id', 'local_addons.remote_addon_id')
                                    ->whereRaw("rtrim(remote_addons.version, '.0') <= rtrim(local_addons.version, '.0')");
                            });
                        },
                        false: function (Builder $query) {
                            return $query->whereExists(function (\Illuminate\Database\Query\Builder $query) {
                                return $query->select('*')
                                    ->from('remote_addons')
                                    ->whereColumn('remote_addons.id', 'local_addons.remote_addon_id')
                                    ->whereRaw("rtrim(remote_addons.version, '.0') > rtrim(local_addons.version, '.0')");
                            });
                        },
                    ),
                Tables\Filters\TernaryFilter::make('is_matched')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('remoteAddon'),
                        false: fn (Builder $query) => $query->whereDoesntHave('remoteAddon'),
                    ),
                Tables\Filters\Filter::make('is_recommended')
                    ->form([
                        Forms\Components\Select::make('is_recommended')
                            ->options(IsRecommended::toArray())
                            ->placeholder('Any')
                            ->label('Recommendation'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['is_recommended'],
                            function (Builder $query, ?string $isRecommended): Builder {
                                return $query->whereHas(
                                    'remoteAddon',
                                    function (Builder $query) use ($isRecommended): Builder {
                                        return $query->where('is_recommended', $isRecommended);
                                });
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['is_recommended']) {
                            return null;
                        }

                        return 'Recommendation: ' . IsRecommended::from($data['is_recommended'])->getLabel();
                    })
                    ->label('Recommendation'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('show_in_explorer')
                        ->action(fn (LocalAddon $record) => exec("explorer.exe /select, $record->path"))
                        ->icon('heroicon-o-folder-open')
                        ->label('Show in Explorer'),
                    Tables\Actions\Action::make('change_matching_addon')
                        ->form([
                            Forms\Components\Select::make('remote_addon_id')
                                ->relationship('remoteAddon', 'title')
                                ->getOptionLabelFromRecordUsing(fn (RemoteAddon $record) => $record->details)
                                ->searchable(['title', 'author'])
                                ->placeholder('Choose a matching addon')
                                ->default(fn (LocalAddon $record): ?int => $record->remote_addon_id)
                                ->required(),
                        ])
                        ->action(function (array $data, LocalAddon $record): void {
                            $record->remote_addon_id = $data['remote_addon_id'];
                            $record->save();
                            Notification::make()
                                ->title('Remote addon changed')
                                ->body("{$record->details} remote addon changed to {$record->remoteAddon->details}")
                                ->success()
                                ->send();
                        })
                        ->icon('heroicon-o-arrows-right-left')
                        ->label('Change Matching Remote Addon')
                        ->modalHeading(fn (LocalAddon $record): string => "Change remote addon for {$record->details}"),
                    Tables\Actions\Action::make('view_matching_addon')
                        ->url(fn (LocalAddon $record): string => $record->remoteAddon?->page ?? '#')
                        ->disabled(fn (LocalAddon $record): bool => is_null($record->remoteAddon))
                        ->icon('heroicon-o-eye')
                        ->label('View Matching Remote Addon')
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('download_matching_addon')
                        ->url(fn (LocalAddon $localAddon) => $localAddon->remoteAddon->torrent ?? '#')
                        ->disabled(fn (LocalAddon $localAddon): bool => is_null($localAddon->remoteAddon))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->label('Download Matching Remote Addon')
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(fn (LocalAddon $record): HtmlString => new HtmlString("Are you sure you want to delete <b>$record->details</b>?"))
                        ->modalSubmitActionLabel('Delete')
                        ->action(function (LocalAddon $record): void {
                            exec("rmdir /s /q $record->path");

                            if (LocalAddon::getLocalAddons($record->path)->count() > 0) {
                                Notification::make()
                                    ->title('Error deleting local addon')
                                    ->body(new HtmlString("<b>$record->details</b> not deleted, please try again."))
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $record->delete();
                            Notification::make()
                                ->title('Local addon deleted')
                                ->body(new HtmlString("<b>$record->details</b> deleted successfully."))
                                ->success()
                                ->send();
                        })
                        ->label('Delete Local Addon'),
                ])
                ->color('grey')
                ->dropdownWidth('sm'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->action(function (ListLocalAddons $livewire, Collection $localAddons) {
                        $localAddons->each(function (LocalAddon $localAddon) use ($livewire) {
                            $livewire->js("window.open('" . ($localAddon->remoteAddon?->torrent ?? '#') . "', '_blank')");
                        });
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (LocalAddon $record): bool => !is_null($record->remoteAddon)
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocalAddons::route('/'),
        ];
    }
}
