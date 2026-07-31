<?php

namespace App\Exceptions;

class RouteSuspendedException extends DispatchException
{
    public function __construct(string $message = "Dispatch Denied: The selected route is currently suspended by an active Service Alert.", int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
