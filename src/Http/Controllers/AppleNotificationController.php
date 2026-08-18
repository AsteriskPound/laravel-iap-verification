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
use ReceiptValidator\AppleAppStore\ServerNotification;
use Throwable;

/**
 * App Store Server Notifications V2 endpoint. Apple POSTs a signed JWS
 * envelope ({"signedPayload": "..."}) — ServerNotification (from
 * aporat/store-receipt-validator) verifies the signature chain and parses it.
 *
 * UNTESTED — not yet received a real notification from Apple's sandbox or
 * production notification service (see PLAN.md Phase 3 checkpoint). The
 * notification-id accessor name in particular needs confirming against the
 * installed library version.
 */
class AppleNotificationController extends Controller
{
    public function __invoke(Request $request): Response
    {
        try {
            $notification = new ServerNotification($request->all());
        } catch (Throwable $e) {
            report($e);

            return response('Invalid signature', 400);
        }

        $notificationId = method_exists($notification, 'getNotificationUUID')
            ? $notification->getNotificationUUID()
            : hash('sha256', $request->getContent());

        $type = $notification->getNotificationType()?->value;
        $transaction = $notification->getTransaction();
        $productId = $transaction?->getProductId();
        $transactionId = $transaction?->getTransactionId();
        $expiresDate = $transaction?->getExpiresDate();

        // Claim before dispatching: this is the atomic step that prevents two
        // concurrent redeliveries of the same notification from both firing
        // events — see ProcessedNotification::claim().
        if (! ProcessedNotification::claim('ios', $notificationId, $type)) {
            return response('Already processed', 200);
        }

        match ($type) {
            'DID_RENEW' => SubscriptionRenewed::dispatch('ios', $productId, $transactionId, $expiresDate ? \DateTimeImmutable::createFromInterface($expiresDate) : null),
            'EXPIRED' => SubscriptionExpired::dispatch('ios', $productId, $transactionId),
            'REFUND' => SubscriptionRefunded::dispatch('ios', $productId, $transactionId),
            'REVOKE' => SubscriptionRevoked::dispatch('ios', $productId, $transactionId),
            default => null, // SUBSCRIBED, DID_CHANGE_RENEWAL_STATUS, PRICE_INCREASE, etc. — no event, just recorded above
        };

        return response('OK', 200);
    }
}
