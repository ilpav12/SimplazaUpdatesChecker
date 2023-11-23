<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum IsRecommended: string implements HasLabel, HasColor, HasIcon
{
    case FullyRecommended = 'fully';
    case PartiallyRecommended = 'partially';
    case NotRecommended = 'not';
    case NoRecommendation = 'none';
    case NoConflicts = 'zero';

    public static function toArray(): array
    {
        return [
            'fully' => 'Fully Recommended',
            'partially' => 'Partially Recommended',
            'not' => 'Not Recommended',
            'none' => 'No Recommendation',
            'zero' => 'No Conflicts',
        ];
    }


    public function getLabel(): ?string
    {
        return match ($this) {
            self::FullyRecommended => 'Fully Recommended',
            self::PartiallyRecommended => 'Partially Recommended',
            self::NotRecommended => 'Not Recommended',
            self::NoRecommendation => 'No Recommendation',
            self::NoConflicts => 'No Conflicts',
        };
    }


    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FullyRecommended => 'success',
            self::PartiallyRecommended => 'warning',
            self::NotRecommended => 'danger',
            self::NoRecommendation => 'info',
            self::NoConflicts => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FullyRecommended => 'heroicon-o-check-circle',
            self::PartiallyRecommended => 'heroicon-o-exclamation-circle',
            self::NotRecommended => 'heroicon-o-x-circle',
            self::NoRecommendation => 'heroicon-o-question-mark-circle',
            self::NoConflicts => 'heroicon-o-minus-circle',
        };
    }
}
