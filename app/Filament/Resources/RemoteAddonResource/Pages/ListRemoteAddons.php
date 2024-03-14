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
}
