<?php

return [

    'apple' => [
        // App Store Connect > Users and Access > Integrations > In-App Purchase key.
        // NOT the same as a general App Store Connect API key.
        'issuer_id' => env('APPLE_IAP_ISSUER_ID'),
        'key_id' => env('APPLE_IAP_KEY_ID'),
        // Path to the downloaded .p8 private key file. Never commit this.
        'private_key_path' => env('APPLE_IAP_PRIVATE_KEY_PATH'),
        'bundle_id' => env('APPLE_IAP_BUNDLE_ID'),
        'environment' => env('APPLE_IAP_ENVIRONMENT', 'production'), // 'production' | 'sandbox'
    ],

    'google' => [
        'package_name' => env('GOOGLE_IAP_PACKAGE_NAME'),
        // Path to the service account JSON key. Grant it access under Play
        // Console > Users and permissions, with the Android Publisher API
        // enabled in its Google Cloud project. Never commit this.
        'service_account_json_path' => env('GOOGLE_IAP_SERVICE_ACCOUNT_JSON'),
    ],

    'webhooks' => [
        // Set to false if you'd rather mount these routes yourself.
        'register_routes' => env('IAP_VERIFICATION_REGISTER_ROUTES', true),
        'apple_path' => 'iap-verification/webhooks/apple',
        'google_path' => 'iap-verification/webhooks/google',

        // Google RTDN delivers via a Pub/Sub push subscription secured by a
        // bearer token you configure on the subscription itself — set the
        // same value here so the controller can verify the request came from
        // your Pub/Sub subscription and not an open POST endpoint.
        'google_pubsub_token' => env('IAP_VERIFICATION_GOOGLE_PUBSUB_TOKEN'),
    ],

];
