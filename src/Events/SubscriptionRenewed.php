<?php

namespace Asteriskpound\LaravelIapVerification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $platform,
        public ?string $productId,
        public ?string $transactionId,
        public ?\DateTimeImmutable $expiresDate,
        /** Stable across renewals — see VerifiedPurchase::$originalTransactionId. */
        public ?string $originalTransactionId = null,
    ) {}
}
