<?php

namespace Asteriskpound\LaravelIapVerification\Http\Controllers;

use Asteriskpound\LaravelIapVerification\Events\SubscriptionExpired;
use Asteriskpound\LaravelIapVerification\Events\SubscriptionRefunded;
use Asteriskpound\LaravelIapVerification\Events\SubscriptionRenewed;
use Asteriskpound\LaravelIapVerification\Events\SubscriptionRevoked;
use Asteriskpound\LaravelIapVerification\ProcessedNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Google Real-time Developer Notifications (RTDN), delivered via a Pub/Sub
 * push subscription. Configure that subscription to push here with a bearer
 * token matching config('iap-verification.webhooks.google_pubsub_token').
 *
 * NOTE: a shared bearer token is simpler but weaker than Google Cloud's
 * recommended OIDC token verification for push subscriptions — good enough
 * for a v1 scaffold behind an unguessable path, worth upgrading to real OIDC
 * verification before this is load-bearing for revenue (see PLAN.md Phase 3).
 * The token is required — an unconfigured token fails closed (401) rather
 * than skipping auth, since an open webhook can forge subscription events.
 *
 * UNTESTED — not yet received a real notification from a live Pub/Sub
 * subscription (see PLAN.md Phase 3 checkpoint).
 */
class GoogleNotificationController extends Controller
{
    /** @see https://developer.android.com/google/play/billing/rtdn-reference */
    private const NOTIFICATION_TYPE_RENEWED = 2;

    private const NOTIFICATION_TYPE_REVOKED = 12;

    private const NOTIFICATION_TYPE_EXPIRED = 13;

    public function __invoke(Request $request): Response
    {
        $expectedToken = config('iap-verification.webhooks.google_pubsub_token');
        if (! $expectedToken || ! hash_equals($expectedToken, (string) $request->bearerToken())) {
            return response('Unauthorized', 401);
        }

        $messageId = $request->input('message.messageId');
        $data = $request->input('message.data');

        if (! $messageId || ! $data) {
            return response('Malformed Pub/Sub envelope', 400);
        }

        $payload = json_decode(base64_decode($data), associative: true);

        $expectedPackageName = config('iap-verification.google.package_name');
        if ($expectedPackageName && ($payload['packageName'] ?? null) !== $expectedPackageName) {
            return response('Package name mismatch', 400);
        }

        $subscriptionNotification = $payload['subscriptionNotification'] ?? null;
        $voidedPurchaseNotification = $payload['voidedPurchaseNotification'] ?? null;

        $type = $subscriptionNotification['notificationType'] ?? null;
        $claimType = match (true) {
            (bool) $subscriptionNotification => (string) $type,
            (bool) $voidedPurchaseNotification => 'voided',
            default => 'unhandled',
        };

        // Claim before dispatching: this is the atomic step that prevents two
        // concurrent redeliveries of the same message from both firing events —
        // see ProcessedNotification::claim().
        if (! ProcessedNotification::claim('android', $messageId, $claimType)) {
            return response('Already processed', 200);
        }

        if ($subscriptionNotification) {
            $productId = $subscriptionNotification['subscriptionId'] ?? null;
            $purchaseToken = $subscriptionNotification['purchaseToken'] ?? null;

            match ($type) {
                self::NOTIFICATION_TYPE_RENEWED => SubscriptionRenewed::dispatch('android', $productId, $purchaseToken, null),
                self::NOTIFICATION_TYPE_EXPIRED => SubscriptionExpired::dispatch('android', $productId, $purchaseToken),
                self::NOTIFICATION_TYPE_REVOKED => SubscriptionRevoked::dispatch('android', $productId, $purchaseToken),
                default => null,
            };
        } elseif ($voidedPurchaseNotification) {
            $purchaseToken = $voidedPurchaseNotification['purchaseToken'] ?? null;
            SubscriptionRefunded::dispatch('android', null, $purchaseToken);
        }
        // else: oneTimeProductNotification or a type this controller doesn't
        // handle yet — already claimed above so Pub/Sub stops retrying.

        return response('OK', 200);
    }
}
