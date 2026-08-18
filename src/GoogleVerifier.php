<?php

namespace Asteriskpound\LaravelIapVerification;

use Google\Client as GoogleClient;
use Google\Service\AndroidPublisher;
use Google\Service\Exception as GoogleServiceException;
use Throwable;

/**
 * Wraps the official google/apiclient Android Publisher API service.
 *
 * UNTESTED — written against the documented purchases.subscriptionsv2 /
 * purchases.products REST shape, not yet run against a real purchase token
 * from a Play Console test track (see PLAN.md Phase 2 checkpoint).
 */
class GoogleVerifier
{
    public function verify(?string $productId, ?string $purchaseToken): VerifiedPurchase
    {
        if ($purchaseToken === null) {
            return VerifiedPurchase::invalid('purchaseToken is required for Google verification');
        }

        $config = config('iap-verification.google');

        if (! $config['package_name'] || ! $config['service_account_json_path']) {
            return VerifiedPurchase::invalid('Google IAP verification is not configured — set GOOGLE_IAP_* in .env');
        }

        try {
            $client = new GoogleClient;
            $client->setAuthConfig($config['service_account_json_path']);
            $client->addScope(AndroidPublisher::ANDROIDPUBLISHER);

            $service = new AndroidPublisher($client);

            // A purchase token alone doesn't say which product type it is —
            // try subscriptions first (the more common billing type for an
            // app like this), fall back to a one-time product lookup.
            try {
                return $this->fromSubscription(
                    $service->purchases_subscriptionsv2->get($config['package_name'], $purchaseToken),
                    $purchaseToken,
                );
            } catch (GoogleServiceException $e) {
                // Only a genuine "not a subscription" response should trigger the
                // one-time-product fallback — a transient auth/network/quota error
                // here must surface as a real failure, not silently misroute to the
                // wrong endpoint.
                if ($e->getCode() !== 404) {
                    throw $e;
                }

                if ($productId === null) {
                    throw new \RuntimeException('Not a subscription and no productId given to try a one-time-product lookup');
                }

                return $this->fromProduct(
                    $service->purchases_products->get($config['package_name'], $productId, $purchaseToken),
                    $productId,
                    $purchaseToken,
                );
            }
        } catch (Throwable $e) {
            return VerifiedPurchase::invalid($e->getMessage());
        }
    }

    private function fromSubscription(mixed $subscription, string $purchaseToken): VerifiedPurchase
    {
        $lineItem = $subscription->getLineItems()[0] ?? null;

        return new VerifiedPurchase(
            valid: $subscription->getSubscriptionState() === 'SUBSCRIPTION_STATE_ACTIVE',
            productId: $lineItem?->getProductId(),
            expiresDate: $lineItem?->getExpiryTime() ? new \DateTimeImmutable($lineItem->getExpiryTime()) : null,
            environment: 'production', // subscriptionsv2 doesn't distinguish test purchases the way Apple does
            rawTransactionId: $purchaseToken,
            isTrial: $lineItem?->getOfferPhase()?->getFreeTrial() !== null,
        );
    }

    private function fromProduct(mixed $product, string $productId, string $purchaseToken): VerifiedPurchase
    {
        // purchaseState: 0 = purchased, 1 = canceled, 2 = pending
        return new VerifiedPurchase(
            valid: $product->getPurchaseState() === 0,
            productId: $productId,
            purchaseDate: $product->getPurchaseTimeMillis()
                ? (new \DateTimeImmutable)->setTimestamp((int) ($product->getPurchaseTimeMillis() / 1000))
                : null,
            expiresDate: null,
            environment: $product->getPurchaseType() === 0 ? 'sandbox' : 'production', // purchaseType: 0 = test
            rawTransactionId: $purchaseToken,
        );
    }
}
