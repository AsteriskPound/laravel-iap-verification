<?php

namespace Asteriskpound\LaravelIapVerification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $platform,
        public ?string $productId,
        public ?string $transactionId,
    ) {}
}
