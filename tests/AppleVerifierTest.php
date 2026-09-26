<?php

use Asteriskpound\LaravelIapVerification\AppleVerifier;

function fakeJws(array $payload): string
{
    $encode = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

    return $encode(['alg' => 'ES256']).'.'.$encode($payload).'.signature';
}

test('it looks up a bare transaction id as-is', function () {
    expect((new AppleVerifier)->lookupTransactionId('2000000123456789'))->toBe('2000000123456789');
});

test('it extracts the transaction id from a StoreKit 2 signed transaction', function () {
    $jws = fakeJws(['transactionId' => '2000000123456789', 'originalTransactionId' => '2000000000000001']);

    expect((new AppleVerifier)->lookupTransactionId($jws))->toBe('2000000123456789');
});

test('it rejects a signed transaction without a transaction id', function () {
    (new AppleVerifier)->lookupTransactionId(fakeJws(['productId' => 'premium_monthly']));
})->throws(InvalidArgumentException::class);
