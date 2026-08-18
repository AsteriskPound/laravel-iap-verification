<?php

/**
 * ServerNotification verifies the JWS signature chain against Apple's real
 * root certificates on construction, so only the invalid-signature path is
 * testable without genuine Apple-signed payload material (which needs a real
 * sandbox notification to obtain — see PLAN.md Phase 3 checkpoint). The
 * success path (idempotency, event mapping) mirrors the Google controller's
 * tested logic but isn't independently verifiable here yet.
 */
test('it rejects a payload that is not a validly signed apple notification', function () {
    $this->postJson('/iap-verification/webhooks/apple', ['signedPayload' => 'not-a-real-jws'])
        ->assertStatus(400);
});
