<?php

namespace App\Exceptions;

class DriverUnavailableException extends DispatchException
{
    public function __construct(string $message = "The selected driver is not available for dispatch.", int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
