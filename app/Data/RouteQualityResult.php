<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class RouteQualityResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly int $score,
        public readonly string $grade,
        public readonly array $warnings,
        public readonly array $recommendations,
    ) {}

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'grade' => $this->grade,
            'warnings' => $this->warnings,
            'recommendations' => $this->recommendations,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
