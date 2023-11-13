<?php

namespace App\Filament\Resources\LocalAddonResource\Pages;

use App\Filament\Resources\LocalAddonResource;
use App\Models\LocalAddon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListLocalAddons extends ListRecords
{
    protected static string $resource = LocalAddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('match')
                ->label('Match with remote addons')
                ->icon('heroicon-o-link')
                ->action(fn () => LocalAddon::matchLocalAddons()),
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $addonsPaths = config('settings.addons_paths');
                    if (empty($addonsPaths)) {
                        Notification::make('missing_addons_paths')
                            ->title('Missing addons paths')
                            ->body(new HtmlString('Please add at least one addons path in the <a href="' . route('filament.admin.pages.settings') . '" style="text-decoration: underline">settings page</a>.'))
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }
                    LocalAddon::saveLocalAddons($addonsPaths);
                }),
        ];
    }
}
