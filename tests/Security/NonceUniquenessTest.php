<?php

use Chronicle\Encryption\PayloadCipher;

it('produces unique nonces across a large batch of encryptions', function () {
    $cipher = app(PayloadCipher::class);
    $dek = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    $aad = PayloadCipher::aad('01J0000000000000000000000', 'stdClass', 's2', 'order.placed');

    $nonces = [];
    for ($i = 0; $i < 2000; $i++) {
        $nonces[] = $cipher->encrypt(['n' => $i], $dek, $aad)->nonce;
    }

    expect(count(array_unique($nonces)))->toBe(2000);
});
