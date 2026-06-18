<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public readonly array $validTransitions;

    public function __construct(string $from, string $to, array $validTransitions = [])
    {
        parent::__construct("Cannot transition bus status from '{$from}' to '{$to}'.");
        $this->validTransitions = $validTransitions;
    }
}
