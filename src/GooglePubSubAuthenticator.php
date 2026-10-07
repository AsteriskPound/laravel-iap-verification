<?php

namespace Asteriskpound\LaravelIapVerification;

use Google\AccessToken\Verify;
use Throwable;

/**
 * Authenticates a Pub/Sub push request. With authentication enabled on the
 * push subscription, Pub/Sub sends a Google-signed OIDC token as the bearer
 * token — a fresh one per request, so it can't be compared to a static
 * secret. This checks the token's signature against Google's public keys,
 * then that it was minted for this endpoint (the subscription's audience) on
 * behalf of the service account configured on the subscription.
 *
 * Fails closed: with either config value unset, every request is rejected,
 * since an open webhook can forge subscription events.
 */
class GooglePubSubAuthenticator
{
    public function authenticates(?string $bearerToken): bool
    {
        $audience = config('iap-verification.webhooks.google_pubsub_audience');
        $serviceAccount = config('iap-verification.webhooks.google_pubsub_service_account');

        if (! $audience || ! $serviceAccount || ! $bearerToken) {
            return false;
        }

        $claims = $this->verifiedClaims($bearerToken, $audience);

        return $claims !== null
            && ($claims['aud'] ?? null) === $audience
            && ($claims['email'] ?? null) === $serviceAccount
            && ($claims['email_verified'] ?? false) === true;
    }

    /**
     * Signature, expiry and issuer are checked by google/apiclient's Verify,
     * which fetches Google's signing certificates.
     *
     * @return array<string, mixed>|null
     */
    private function verifiedClaims(string $token, string $audience): ?array
    {
        try {
            $claims = app(Verify::class)->verifyIdToken($token, $audience);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return is_array($claims) ? $claims : null;
    }
}
