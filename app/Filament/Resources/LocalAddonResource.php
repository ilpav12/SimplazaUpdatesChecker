<?php

namespace App\Filament\Resources;

use App\Enums\IsRecommended;
use App\Filament\Resources\LocalAddonResource\Pages;
use App\Filament\Resources\LocalAddonResource\RelationManagers;
use App\Models\LocalAddon;
use App\Models\RemoteAddon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

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
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_excluded')
                    ->default(false),
                Tables\Filters\TernaryFilter::make('is_updated'),
                Tables\Filters\TernaryFilter::make('is_matched')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('remoteAddon'),
                        false: fn (Builder $query) => $query->whereDoesntHave('remoteAddon'),
                    ),
                Tables\Filters\Filter::make('is_recommended')
                    ->form([
                        Forms\Components\Select::make('is_recommended')
                            ->options([
                                'fully' => 'Fully Recommended',
                                'partially' => 'Partially Recommended',
                                'not' => 'Not Recommended',
                                'none' => 'No Recommendation',
                                null => 'No Conflicts',
                            ])
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
                    ->label('Recommendation'),
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
