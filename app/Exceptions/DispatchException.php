<?php

namespace App\Exceptions;

use Exception;

class DispatchException extends Exception
{
    public function __construct(string $message = "Dispatch operation failed.", int $code = 422, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
