<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProviderRecovered
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $provider) {}
}
