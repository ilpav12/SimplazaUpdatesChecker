<?php

namespace App\Filament\Resources\RemoteAddonResource\Pages;

use App\Filament\Resources\RemoteAddonResource;
use App\Models\RemoteAddon;
use Filament\Actions;
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
                ->action(fn () => RemoteAddon::saveRemoteAddons()),
        ];
    }
}
