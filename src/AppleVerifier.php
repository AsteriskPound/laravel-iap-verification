<?php

namespace Asteriskpound\LaravelIapVerification;

use ReceiptValidator\AppleAppStore\Validator as AppleAppStoreValidator;
use ReceiptValidator\Environment;
use Throwable;

/**
 * Wraps aporat/store-receipt-validator's App Store Server API v2 client.
 *
 * Class/method names verified 2026-08-18 by reflection against the actually
 * installed v9.0.0 (not just the README) — Validator's constructor params,
 * Transaction's field getters, and Environment being a string-backed enum
 * are all confirmed real. What's still UNTESTED is calling
 * getTransactionInfo() with a genuine transaction ID from a sandbox or
 * production purchase (see PLAN.md Phase 2 checkpoint) — no network call
 * has actually been made against Apple's API.
 */
class AppleVerifier
{
    public function verify(?string $transactionId): VerifiedPurchase
    {
        if ($transactionId === null) {
            return VerifiedPurchase::invalid('transactionId is required for Apple verification');
        }

        $config = config('iap-verification.apple');

        if (! $config['issuer_id'] || ! $config['key_id'] || ! $config['private_key_path'] || ! $config['bundle_id']) {
            return VerifiedPurchase::invalid('Apple IAP verification is not configured — set APPLE_IAP_* in .env');
        }

        try {
            $validator = new AppleAppStoreValidator(
                signingKey: file_get_contents($config['private_key_path']),
                keyId: $config['key_id'],
                issuerId: $config['issuer_id'],
                bundleId: $config['bundle_id'],
                environment: $config['environment'] === 'sandbox' ? Environment::SANDBOX : Environment::PRODUCTION,
            );

            $transaction = $validator->getTransactionInfo($transactionId);

            if ($transaction->getRevocationDate() !== null) {
                return VerifiedPurchase::invalid(
                    'Transaction was revoked'.($transaction->getRevocationReason() !== null ? " (reason: {$transaction->getRevocationReason()})" : '')
                );
            }

            return new VerifiedPurchase(
                valid: true,
                productId: $transaction->getProductId(),
                purchaseDate: $this->toDateTime($transaction->getPurchaseDate()),
                expiresDate: $this->toDateTime($transaction->getExpiresDate()),
                environment: $transaction->getEnvironment()->value,
                rawTransactionId: $transaction->getTransactionId(),
                isTrial: $transaction->getOfferDiscountType() === 'FREE_TRIAL',
            );
        } catch (Throwable $e) {
            return VerifiedPurchase::invalid($e->getMessage());
        }
    }

    private function toDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        // Some receipt-validator versions return epoch milliseconds instead
        // of epoch seconds — a real timestamp in seconds won't exceed ~1e11
        // until the year 5138, so treat anything past that as milliseconds.
        if (is_numeric($value)) {
            $numeric = (float) $value;
            $seconds = $numeric > 1e11 ? (int) ($numeric / 1000) : (int) $numeric;

            return (new \DateTimeImmutable)->setTimestamp($seconds);
        }

        return null;
    }
}
