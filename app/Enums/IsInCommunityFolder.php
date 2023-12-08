<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum IsInCommunityFolder: string implements HasLabel, HasColor, HasIcon
{
    case Inside = 'inside';
    case Symlinked = 'symlinked';
    case Outside = 'outside';

    public static function toArray(): array
    {
        return [
            'true' => 'Inside',
            'symlinked' => 'Symlinked',
            'false' => 'Outside',
        ];
    }


    public function getLabel(): ?string
    {
        return match ($this) {
            self::Inside => 'Inside',
            self::Symlinked => 'Symlinked',
            self::Outside => 'Outside',
        };
    }


    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Inside => 'success',
            self::Symlinked => 'info',
            self::Outside => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Inside => 'heroicon-o-check-circle',
            self::Symlinked => 'heroicon-o-information-circle',
            self::Outside => 'heroicon-o-x-circle',
        };
    }
}
