<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RemoteAddonResource\Pages;
use App\Filament\Resources\RemoteAddonResource\Pages\ListRemoteAddons;
use App\Filament\Resources\RemoteAddonResource\RelationManagers;
use App\Models\RemoteAddon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class RemoteAddonResource extends Resource
{
    protected static ?string $model = RemoteAddon::class;

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
                Tables\Columns\TextColumn::make('updated_at')
                    ->date('F j, Y')
                    ->label('Published')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('info')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->disabled(fn (RemoteAddon $remoteAddon): bool => $remoteAddon->description === '')
                    ->requiresConfirmation()
                    ->modalDescription(fn (RemoteAddon $remoteAddon): HtmlString => new HtmlString($remoteAddon->description))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalAlignment(Alignment::Left),
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (RemoteAddon $remoteAddon) => $remoteAddon->page)
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
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
            ->emptyStateActions([
                //
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('updated_at', 'desc'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRemoteAddons::route('/'),
        ];
    }
}
