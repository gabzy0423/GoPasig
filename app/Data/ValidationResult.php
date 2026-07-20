<?php

namespace App\Data;

use App\Enums\GeometryStatus;
use ArrayAccess;

class ValidationResult implements ArrayAccess
{
    public function __construct(
        public readonly GeometryStatus $status,
        public readonly array          $issues   = [],  // blocking errors → INVALID
        public readonly array          $warnings = [],  // non-blocking → WARNING
    ) {}

    public function isValid(): bool  { return $this->status === GeometryStatus::VALID; }
    public function hasWarnings(): bool { return !empty($this->warnings); }

    // ArrayAccess implementation for backward compatibility
    public function offsetExists($offset): bool
    {
        return in_array($offset, ['valid', 'error', 'issues', 'warnings', 'status']);
    }

    public function offsetGet($offset): mixed
    {
        return match ($offset) {
            'valid' => $this->isValid(),
            'error' => $this->isValid() ? null : ($this->issues[0] ?? $this->warnings[0] ?? 'Invalid geometry'),
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'status' => $this->status->value,
            default => null
        };
    }

    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
}
