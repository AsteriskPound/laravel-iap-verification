<?php

use Asteriskpound\LaravelIapVerification\Events\SubscriptionExpired;
use Asteriskpound\LaravelIapVerification\Events\SubscriptionRenewed;
use Asteriskpound\LaravelIapVerification\Events\SubscriptionRevoked;
use Asteriskpound\LaravelIapVerification\ProcessedNotification;
use Google\AccessToken\Verify;
use Illuminate\Support\Facades\Event;

const PUBSUB_AUDIENCE = 'https://example.com/iap-verification/webhooks/google';

const PUBSUB_SERVICE_ACCOUNT = 'rtdn-push@example.iam.gserviceaccount.com';

/**
 * Stands in for Google's signature check: 'test-token' verifies to $claims,
 * anything else fails as a bad signature would.
 *
 * @param  array<string, mixed>  $claims
 */
function fakeGoogleTokenVerifier(array $claims = []): void
{
    $verifier = Mockery::mock(Verify::class);
    $verifier->shouldReceive('verifyIdToken')->andReturnUsing(
        fn (string $token, string $audience): array|false => $token === 'test-token'
            ? [...['aud' => PUBSUB_AUDIENCE, 'email' => PUBSUB_SERVICE_ACCOUNT, 'email_verified' => true], ...$claims]
            : false
    );

    app()->instance(Verify::class, $verifier);
}

beforeEach(function () {
    config([
        'iap-verification.webhooks.google_pubsub_audience' => PUBSUB_AUDIENCE,
        'iap-verification.webhooks.google_pubsub_service_account' => PUBSUB_SERVICE_ACCOUNT,
    ]);

    fakeGoogleTokenVerifier();
});

function pubSubPayload(array $subscriptionNotification, string $messageId = 'msg-1'): array
{
    return [
        'message' => [
            'messageId' => $messageId,
            'data' => base64_encode(json_encode([
                'packageName' => 'com.example.app',
                'subscriptionNotification' => $subscriptionNotification,
            ])),
        ],
        'subscription' => 'projects/example/subscriptions/rtdn',
    ];
}

test('it rejects a request without the configured bearer token', function () {
    $this->postJson('/iap-verification/webhooks/google', pubSubPayload(['notificationType' => 2]))
        ->assertStatus(401);
});

test('it rejects a token that fails Google signature verification', function () {
    $this->withToken('forged-token')
        ->postJson('/iap-verification/webhooks/google', pubSubPayload(['notificationType' => 2]))
        ->assertStatus(401);
});

test('it rejects a validly signed token minted for another service account or audience', function (array $claims) {
    fakeGoogleTokenVerifier($claims);

    $this->withToken('test-token')
        ->postJson('/iap-verification/webhooks/google', pubSubPayload(['notificationType' => 2]))
        ->assertStatus(401);
})->with([
    'other service account' => [['email' => 'someone-else@example.iam.gserviceaccount.com']],
    'unverified email' => [['email_verified' => false]],
    'other audience' => [['aud' => 'https://attacker.example/hook']],
]);

test('it rejects every push while the audience or service account is unconfigured', function (string $key) {
    config(["iap-verification.webhooks.{$key}" => null]);

    $this->withToken('test-token')
        ->postJson('/iap-verification/webhooks/google', pubSubPayload(['notificationType' => 2]))
        ->assertStatus(401);
})->with(['google_pubsub_audience', 'google_pubsub_service_account']);

test('it rejects a malformed pub/sub envelope', function () {
    $this->withToken('test-token')
        ->postJson('/iap-verification/webhooks/google', ['nonsense' => true])
        ->assertStatus(400);
});

test('it dispatches SubscriptionRenewed for notification type 2 and records it as processed', function () {
    Event::fake([SubscriptionRenewed::class]);

    $this->withToken('test-token')
        ->postJson('/iap-verification/webhooks/google', pubSubPayload([
            'notificationType' => 2,
            'subscriptionId' => 'premium_monthly',
            'purchaseToken' => 'token_abc',
        ]))
        ->assertOk();

    Event::assertDispatched(SubscriptionRenewed::class, fn ($event) => $event->platform === 'android'
        && $event->productId === 'premium_monthly'
        && $event->originalTransactionId === 'token_abc'
    );
    expect(ProcessedNotification::alreadyProcessed('android', 'msg-1'))->toBeTrue();
});

test('it treats a recovered or restarted subscription as a renewal', function (int $notificationType) {
    Event::fake([SubscriptionRenewed::class]);

    $this->withToken('test-token')
        ->postJson('/iap-verification/webhooks/google', pubSubPayload([
            'notificationType' => $notificationType,
            'subscriptionId' => 'premium_monthly',
            'purchaseToken' => 'token_abc',
        ]))
        ->assertOk();

    Event::assertDispatched(SubscriptionRenewed::class, fn ($event) => $event->originalTransactionId === 'token_abc');
})->with(['recovered' => 1, 'restarted' => 7]);

test('it dispatches SubscriptionExpired for notification type 13', function () {
    Event::fake([SubscriptionExpired::class]);

    $this->withToken('test-token')
        ->postJson('/iap-verification/webhooks/google', pubSubPayload([
            'notificationType' => 13,
            'subscriptionId' => 'premium_monthly',
            'purchaseToken' => 'token_abc',
        ]))
        ->assertOk();

    Event::assertDispatched(SubscriptionExpired::class);
});

test('it dispatches SubscriptionRevoked for notification type 12', function () {
    Event::fake([SubscriptionRevoked::class]);

    $this->withToken('test-token')
        ->postJson('/iap-verification/webhooks/google', pubSubPayload([
            'notificationType' => 12,
            'subscriptionId' => 'premium_monthly',
            'purchaseToken' => 'token_abc',
        ]))
        ->assertOk();

    Event::assertDispatched(SubscriptionRevoked::class);
});

test('it is idempotent against a redelivered message id', function () {
    Event::fake([SubscriptionRenewed::class]);

    $payload = pubSubPayload(['notificationType' => 2, 'subscriptionId' => 'premium_monthly', 'purchaseToken' => 'token_abc'], messageId: 'msg-dup');

    $this->withToken('test-token')->postJson('/iap-verification/webhooks/google', $payload)->assertOk();
    $this->withToken('test-token')->postJson('/iap-verification/webhooks/google', $payload)->assertOk();

    Event::assertDispatchedTimes(SubscriptionRenewed::class, 1);
});

test('the token verifier resolves from the container without symfony/cache installed', function () {
    app()->forgetInstance(Verify::class);

    expect(app(Verify::class))->toBeInstanceOf(Verify::class);
});
