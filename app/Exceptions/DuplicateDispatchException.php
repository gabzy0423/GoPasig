<?php

namespace App\Exceptions;

class DuplicateDispatchException extends DispatchException
{
    public function __construct(string $message = "Duplicate dispatch detected: bus or driver is already active.", int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
