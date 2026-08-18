# laravel-iap-verification

Server-side in-app-purchase receipt verification and webhook handling for the Apple App Store and Google
Play — plain Laravel, **no NativePHP dependency**. Usable by any Laravel app, mobile or not.

Pairs with `asteriskpound/nativephp-mobile-payments` if your purchases come from a NativePHP app, but doesn't
require it — any source of a transaction ID / purchase token works.

> **Status: scaffolding.** The Apple/Google client wrappers and webhook controllers are a first pass, not yet
> run against a real sandbox purchase or a live webhook delivery.

## Install

```bash
composer require asteriskpound/laravel-iap-verification
php artisan vendor:publish --tag=iap-verification-config
php artisan migrate
```

Set in `.env` (see `config/iap-verification.php` for what each does):

```dotenv
APPLE_IAP_ISSUER_ID=
APPLE_IAP_KEY_ID=
APPLE_IAP_PRIVATE_KEY_PATH=
APPLE_IAP_BUNDLE_ID=
APPLE_IAP_ENVIRONMENT=production

GOOGLE_IAP_PACKAGE_NAME=
GOOGLE_IAP_SERVICE_ACCOUNT_JSON=

IAP_VERIFICATION_GOOGLE_PUBSUB_TOKEN=
```

## Usage

```php
use Asteriskpound\LaravelIapVerification\Facades\IapVerification;

$result = IapVerification::verify(platform: 'ios', transactionId: $transactionId);
// or: IapVerification::verify(platform: 'android', purchaseToken: $token, productId: $productId);

if ($result->isValid()) {
    // grant entitlement in YOUR OWN subscription/user model, then tell the
    // mobile app it's safe to call Payments::finish($transactionId)
}
```

## Webhooks

Two routes are registered automatically (disable via `IAP_VERIFICATION_REGISTER_ROUTES=false` and mount them
yourself if you'd rather):

- `POST /iap-verification/webhooks/apple` — register this URL in App Store Connect as your App Store Server
  Notifications V2 endpoint.
- `POST /iap-verification/webhooks/google` — point a Google Cloud Pub/Sub push subscription at this URL,
  configured for Real-time Developer Notifications in Play Console. Secure it with a bearer token matching
  `IAP_VERIFICATION_GOOGLE_PUBSUB_TOKEN`.

Both dispatch Laravel events (`SubscriptionRenewed`, `SubscriptionExpired`, `SubscriptionRefunded`,
`SubscriptionRevoked`) — listen for those in your own app to keep your entitlement model in sync. This
package deliberately doesn't assume your schema; it only tells you what happened.

## License

MIT
