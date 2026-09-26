<?php

namespace Asteriskpound\LaravelIapVerification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $platform,
        public ?string $productId,
        public ?string $transactionId,
        /** Stable across renewals — see VerifiedPurchase::$originalTransactionId. */
        public ?string $originalTransactionId = null,
    ) {}
}
