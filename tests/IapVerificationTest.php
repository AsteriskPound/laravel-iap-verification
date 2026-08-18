<?php

use Asteriskpound\LaravelIapVerification\AppleVerifier;
use Asteriskpound\LaravelIapVerification\GoogleVerifier;
use Asteriskpound\LaravelIapVerification\IapVerification;
use Asteriskpound\LaravelIapVerification\VerifiedPurchase;

test('it routes ios verification to the apple verifier', function () {
    $apple = Mockery::mock(AppleVerifier::class);
    $apple->shouldReceive('verify')->once()->with('txn_123')->andReturn(new VerifiedPurchase(valid: true));

    $service = new IapVerification($apple, Mockery::mock(GoogleVerifier::class));

    expect($service->verify(platform: 'ios', transactionId: 'txn_123')->isValid())->toBeTrue();
});

test('it routes android verification to the google verifier', function () {
    $google = Mockery::mock(GoogleVerifier::class);
    $google->shouldReceive('verify')->once()->with('premium_monthly', 'token_abc')->andReturn(new VerifiedPurchase(valid: true));

    $service = new IapVerification(Mockery::mock(AppleVerifier::class), $google);

    expect($service->verify(platform: 'android', purchaseToken: 'token_abc', productId: 'premium_monthly')->isValid())->toBeTrue();
});

test('it rejects an unknown platform', function () {
    $service = new IapVerification(Mockery::mock(AppleVerifier::class), Mockery::mock(GoogleVerifier::class));

    $service->verify(platform: 'web');
})->throws(InvalidArgumentException::class);

test('unconfigured apple credentials return an invalid result instead of throwing', function () {
    config(['iap-verification.apple.issuer_id' => null]);

    $result = (new AppleVerifier)->verify('txn_123');

    expect($result->isValid())->toBeFalse()
        ->and($result->error)->toContain('not configured');
});

test('unconfigured google credentials return an invalid result instead of throwing', function () {
    config(['iap-verification.google.package_name' => null]);

    $result = (new GoogleVerifier)->verify('premium_monthly', 'token_abc');

    expect($result->isValid())->toBeFalse()
        ->and($result->error)->toContain('not configured');
});
