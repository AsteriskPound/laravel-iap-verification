<?php

use Asteriskpound\LaravelIapVerification\AppleVerifier;
use ReceiptValidator\AppleAppStore\APIError;
use ReceiptValidator\AppleAppStore\Transaction;
use ReceiptValidator\AppleAppStore\Validator;
use ReceiptValidator\Environment;
use ReceiptValidator\Exceptions\ValidationException;

const FAKE_PEM = "-----BEGIN PRIVATE KEY-----\nMIGTAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBHkwdwIBAQQg\n-----END PRIVATE KEY-----";

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

/**
 * An AppleVerifier whose App Store Server API clients are stubbed per environment.
 *
 * @param  array<string, Closure(string): Transaction>  $responses  keyed by Environment value
 * @param  list<string>  $calls  receives each environment queried, in order
 */
function verifierWithEnvironments(array $responses, array &$calls): AppleVerifier
{
    config()->set('iap-verification.apple', [
        'issuer_id' => 'issuer',
        'key_id' => 'key',
        'private_key' => FAKE_PEM,
        'private_key_path' => null,
        'bundle_id' => 'com.example.app',
        'environment' => 'production',
    ]);

    return new class($responses, $calls) extends AppleVerifier
    {
        public function __construct(private array $responses, private array &$calls) {}

        protected function makeValidator(array $config, Environment $environment): Validator
        {
            $this->calls[] = $environment->value;
            $validator = Mockery::mock(Validator::class);
            $validator->shouldReceive('getTransactionInfo')->andReturnUsing($this->responses[$environment->value]);

            return $validator;
        }
    };
}

function appleTransaction(string $environment): Transaction
{
    return new Transaction([
        'transactionId' => '2000000123456789',
        'originalTransactionId' => '2000000000000001',
        'productId' => 'premium_monthly',
        'purchaseDate' => 1_790_000_000_000,
        'expiresDate' => 1_792_600_000_000,
        'environment' => $environment,
    ]);
}

function transactionNotFound(): Closure
{
    return fn () => throw new ValidationException('App Store API error [4040010]', APIError::TRANSACTION_ID_NOT_FOUND->value);
}

test('a production server falls back to sandbox for an App Review or TestFlight purchase', function () {
    $calls = [];
    $verifier = verifierWithEnvironments([
        Environment::PRODUCTION->value => transactionNotFound(),
        Environment::SANDBOX->value => fn () => appleTransaction('Sandbox'),
    ], $calls);

    $result = $verifier->verify('2000000123456789');

    expect($result->isValid())->toBeTrue()
        ->and($result->isSandbox())->toBeTrue()
        ->and($result->originalTransactionId)->toBe('2000000000000001')
        ->and($calls)->toBe([Environment::PRODUCTION->value, Environment::SANDBOX->value]);
});

test('a production purchase is never looked up in sandbox', function () {
    $calls = [];
    $verifier = verifierWithEnvironments([
        Environment::PRODUCTION->value => fn () => appleTransaction('Production'),
    ], $calls);

    $result = $verifier->verify('2000000123456789');

    expect($result->isValid())->toBeTrue()
        ->and($result->isSandbox())->toBeFalse()
        ->and($calls)->toBe([Environment::PRODUCTION->value]);
});

test('other production errors are not retried against sandbox', function () {
    $calls = [];
    $verifier = verifierWithEnvironments([
        Environment::PRODUCTION->value => fn () => throw new ValidationException('App Store API error [401]: Unauthenticated', 401),
    ], $calls);

    $result = $verifier->verify('2000000123456789');

    expect($result->isValid())->toBeFalse()
        ->and($calls)->toBe([Environment::PRODUCTION->value]);
});

test('a transaction found in neither environment is invalid', function () {
    $calls = [];
    $verifier = verifierWithEnvironments([
        Environment::PRODUCTION->value => transactionNotFound(),
        Environment::SANDBOX->value => transactionNotFound(),
    ], $calls);

    expect($verifier->verify('2000000123456789')->isValid())->toBeFalse();
});

test('the signing key can come straight from the env, as PEM with real or literal line breaks', function (string $envValue) {
    expect((new AppleVerifier)->signingKey(['private_key' => $envValue]))->toBe(FAKE_PEM."\n");
})->with([
    'real line breaks' => FAKE_PEM,
    'literal \\n sequences' => str_replace("\n", '\n', FAKE_PEM),
    'base64-encoded file' => base64_encode(FAKE_PEM."\n"),
]);

test('the signing key falls back to the key file when no env key is set', function () {
    $path = tempnam(sys_get_temp_dir(), 'p8');
    file_put_contents($path, FAKE_PEM."\n");

    expect((new AppleVerifier)->signingKey(['private_key' => null, 'private_key_path' => $path]))->toBe(FAKE_PEM."\n");

    unlink($path);
});

test('verification reports not configured when there is no signing key', function () {
    config()->set('iap-verification.apple', [
        'issuer_id' => 'issuer',
        'key_id' => 'key',
        'private_key' => null,
        'private_key_path' => '/nonexistent/key.p8',
        'bundle_id' => 'com.example.app',
        'environment' => 'production',
    ]);

    expect((new AppleVerifier)->verify('2000000123456789')->error)->toContain('not configured');
});

test('an env key that is not a PEM key is rejected', function () {
    (new AppleVerifier)->signingKey(['private_key' => 'not-a-key']);
})->throws(InvalidArgumentException::class);
