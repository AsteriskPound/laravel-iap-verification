<?php

namespace Asteriskpound\LaravelIapVerification\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Asteriskpound\LaravelIapVerification\VerifiedPurchase verify(string $platform, ?string $transactionId = null, ?string $purchaseToken = null, ?string $productId = null)
 *
 * @see \Asteriskpound\LaravelIapVerification\IapVerification
 */
class IapVerification extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Asteriskpound\LaravelIapVerification\IapVerification::class;
    }
}
