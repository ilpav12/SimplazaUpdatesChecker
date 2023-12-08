<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Rules\ValidPath;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 4;

    public function mount(): void
    {
        $this->form->fill([
            'community_folder' => config('settings.community_folder'),
            'addons_folders' => config('settings.addons_folders'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('community_folder')
                    ->rules([new ValidPath()])
                    ->required(),
                Forms\Components\Repeater::make('addons_folders')
                    ->simple(
                        Forms\Components\TextInput::make('folder')
                            ->rules([new ValidPath()])
                            ->required(),
                    )
                    ->reorderable(false),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            $this->form->validate($data);

            Setting::updateOrCreate(
                ['name' => 'community_folder'],
                ['value' => $data['community_folder']]
            );

            Setting::updateOrCreate(
                ['name' => 'addons_folders'],
                ['value' => $data['addons_folders']]
            );
        } catch (Halt $exception) {
            return;
        }

        Notification::make()
            ->success()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
            ->send();
    }
}
