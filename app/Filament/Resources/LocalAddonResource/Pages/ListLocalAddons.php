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
                ->label('Match addons')
                ->tooltip('This will automatically try to match local addons with remote addons based on their titles and authors. This operation is done only for not excluded or already matched addons.')
                ->icon('heroicon-o-link')
                ->action(fn () => LocalAddon::matchLocalAddons()),
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->tooltip('This will get the latest list of addons from the paths configured in the settings page.')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $communityFolder = config('settings.community_folder');
                    if (empty($communityFolder)) {
                        Notification::make('missing_community_folder')
                            ->title('Missing community folder')
                            ->body(new HtmlString('Please add the community folder path in the <a href="' . route('filament.admin.pages.settings') . '" style="text-decoration: underline">settings page</a>.'))
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }
                    LocalAddon::saveLocalAddons($communityFolder, config('settings.addons_folders'));
                }),
        ];
    }
}
