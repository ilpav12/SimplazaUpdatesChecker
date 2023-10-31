<?php

namespace App\Filament\Resources\LocalAddonResource\Pages;

use App\Filament\Resources\LocalAddonResource;
use App\Models\LocalAddon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocalAddons extends ListRecords
{
    protected static string $resource = LocalAddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => LocalAddon::saveLocalAddons()),
        ];
    }
}
