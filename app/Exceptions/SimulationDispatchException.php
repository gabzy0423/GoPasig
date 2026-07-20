<?php

namespace App\Exceptions;

use Exception;

class SimulationDispatchException extends DispatchException
{
    public function __construct(string $message = "Simulation dispatch failed.", int $code = 422, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
