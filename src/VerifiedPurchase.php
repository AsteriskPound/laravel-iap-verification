<?php

namespace Asteriskpound\LaravelIapVerification;

class VerifiedPurchase
{
    public function __construct(
        public bool $valid,
        public ?string $productId = null,
        public ?\DateTimeImmutable $purchaseDate = null,
        public ?\DateTimeImmutable $expiresDate = null,
        public string $environment = 'production',
        public ?string $rawTransactionId = null,
        public ?string $error = null,
        public bool $isTrial = false,
        /**
         * The ID that stays the same across every renewal of one subscription —
         * Apple's originalTransactionId, Google's purchase token. Use it to bind a
         * subscription to a single user and to match webhook events back to them.
         */
        public ?string $originalTransactionId = null,
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    /** A purchase with an expiry date is a subscription; without, a one-time product. */
    public function isSubscription(): bool
    {
        return $this->expiresDate !== null;
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox' || $this->environment === 'Sandbox';
    }

    public static function invalid(string $error): self
    {
        return new self(valid: false, error: $error);
    }
}
