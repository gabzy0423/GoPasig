<?php

namespace App\Enums;

enum GeometryStatus: string
{
    case VALID   = 'valid';
    case WARNING = 'warning';
    case INVALID = 'invalid';

    public function label(): string
    {
        return match($this) {
            self::VALID   => 'Valid',
            self::WARNING => 'Warning',
            self::INVALID => 'Invalid',
        };
    }

    public function isHealthy(): bool
    {
        return $this === self::VALID;
    }
}
