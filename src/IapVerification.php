<?php

namespace Asteriskpound\LaravelIapVerification;

use InvalidArgumentException;

class IapVerification
{
    public function __construct(
        protected AppleVerifier $apple,
        protected GoogleVerifier $google,
    ) {}

    public function verify(
        string $platform,
        ?string $transactionId = null,
        ?string $purchaseToken = null,
        ?string $productId = null,
    ): VerifiedPurchase {
        return match ($platform) {
            'ios' => $this->apple->verify($transactionId),
            'android' => $this->google->verify($productId, $purchaseToken),
            default => throw new InvalidArgumentException("Unknown platform [{$platform}] — expected 'ios' or 'android'"),
        };
    }
}
