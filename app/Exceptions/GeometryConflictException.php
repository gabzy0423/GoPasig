<?php

namespace App\Exceptions;

use Exception;

class GeometryConflictException extends Exception
{
    // Custom exception for optimistic locking conflict (409)
}
