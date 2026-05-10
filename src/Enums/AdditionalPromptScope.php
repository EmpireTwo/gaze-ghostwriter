<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Enums;

enum AdditionalPromptScope: string
{
    case GLOBAL = 'global';
    case USER = 'user';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global',
            self::USER => 'Persönlich',
        };
    }
}
