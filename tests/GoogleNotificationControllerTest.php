<?php

use Asteriskpound\LaravelIapVerification\Events\SubscriptionExpired;
use Asteriskpound\LaravelIapVerification\Events\SubscriptionRenewed;
use Asteriskpound\LaravelIapVerification\Events\SubscriptionRevoked;
use Asteriskpound\LaravelIapVerification\ProcessedNotification;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['iap-verification.webhooks.google_pubsub_token' => 'test-token']);
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

    Event::assertDispatched(SubscriptionRenewed::class, fn ($event) => $event->platform === 'android' && $event->productId === 'premium_monthly'
    );
    expect(ProcessedNotification::alreadyProcessed('android', 'msg-1'))->toBeTrue();
});

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
