<?php

use Asteriskpound\LaravelIapVerification\VerifiedPurchase;

test('a purchase with an expiry date is treated as a subscription', function () {
    $purchase = new VerifiedPurchase(valid: true, expiresDate: new DateTimeImmutable('+1 month'));

    expect($purchase->isSubscription())->toBeTrue();
});

test('a purchase without an expiry date is treated as a one-time product', function () {
    $purchase = new VerifiedPurchase(valid: true);

    expect($purchase->isSubscription())->toBeFalse();
});

test('invalid() produces an invalid purchase carrying the error', function () {
    $purchase = VerifiedPurchase::invalid('not configured');

    expect($purchase->isValid())->toBeFalse()
        ->and($purchase->error)->toBe('not configured');
});

test('sandbox environment detection is case-insensitive between the two APIs', function () {
    expect((new VerifiedPurchase(valid: true, environment: 'Sandbox'))->isSandbox())->toBeTrue()
        ->and((new VerifiedPurchase(valid: true, environment: 'sandbox'))->isSandbox())->toBeTrue()
        ->and((new VerifiedPurchase(valid: true, environment: 'production'))->isSandbox())->toBeFalse();
});
