<?php

use Asteriskpound\LaravelIapVerification\Http\Controllers\AppleNotificationController;
use Asteriskpound\LaravelIapVerification\Http\Controllers\GoogleNotificationController;
use Illuminate\Support\Facades\Route;

// Unauthenticated, server-to-server endpoints — verified by signature (Apple)
// or bearer token (Google), not by session/CSRF. Deliberately registered
// outside the 'web' middleware group (only that group applies CSRF/session
// in a standard Laravel 11+ skeleton), so no explicit CSRF exemption is
// needed as long as the host app doesn't wrap these in its own 'web' group.
Route::post(config('iap-verification.webhooks.apple_path'), AppleNotificationController::class)
    ->name('iap-verification.webhooks.apple');

Route::post(config('iap-verification.webhooks.google_path'), GoogleNotificationController::class)
    ->name('iap-verification.webhooks.google');
