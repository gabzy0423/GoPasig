<?php

namespace App\Exceptions;

class ScheduleConflictException extends DispatchException
{
    public function __construct(string $message = "Schedule conflict detected for the selected bus or driver.", int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
