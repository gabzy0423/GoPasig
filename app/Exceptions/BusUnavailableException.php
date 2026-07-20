<?php

namespace App\Exceptions;

class BusUnavailableException extends DispatchException
{
    public function __construct(string $message = "The selected bus is not available for dispatch.", int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
