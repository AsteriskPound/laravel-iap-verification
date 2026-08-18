<?php

namespace Asteriskpound\LaravelIapVerification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Family Sharing revocation, a chargeback, or Apple/Google pulling access for
 * policy reasons — distinct from a refund the user requested themselves.
 */
class SubscriptionRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $platform,
        public ?string $productId,
        public ?string $transactionId,
    ) {}
}
