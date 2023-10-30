<?php

namespace App\Filament\Resources\RemoteAddonResource\Pages;

use App\Filament\Resources\RemoteAddonResource;
use App\Models\RemoteAddon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRemoteAddons extends ListRecords
{
    protected static string $resource = RemoteAddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
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
        ];
    }
}
