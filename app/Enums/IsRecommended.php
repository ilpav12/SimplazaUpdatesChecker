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


    public function getLabel(): ?string
    {
        return match ($this) {
            self::FullyRecommended => 'Fully Recommended',
            self::PartiallyRecommended => 'Partially Recommended',
            self::NotRecommended => 'Not Recommended',
            self::NoRecommendation => 'No Recommendation',
        };
    }


    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FullyRecommended => 'success',
            self::PartiallyRecommended => 'warning',
            self::NotRecommended => 'danger',
            self::NoRecommendation => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FullyRecommended => 'heroicon-o-check-circle',
            self::PartiallyRecommended => 'heroicon-o-exclamation-circle',
            self::NotRecommended => 'heroicon-o-x-circle',
            self::NoRecommendation => 'heroicon-o-question-mark-circle',
        };
    }
}
