<?php

declare(strict_types=1);

use Chronicle\Anchoring\Rfc3161TimestampAnchor;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

$fixtureDir = __DIR__.'/../../Fixtures/rfc3161';

beforeEach(function () use ($fixtureDir) {
    $opensslAvailable = (function (): bool {
        try {
            $p = new Process(['openssl', 'version']);
            $p->run();

            return $p->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    })();

    if (! $opensslAvailable
        || ! is_file("$fixtureDir/token.tsr")
        || ! is_file("$fixtureDir/tsa-ca.pem")
        || ! is_file("$fixtureDir/digest.bin")) {
        $this->markTestSkipped('RFC 3161 fixture or the openssl CLI is unavailable.');
    }
});

it('verifies a real TSA token against its fixed digest and rejects a corrupt one', function () use ($fixtureDir) {
    $token = (string) file_get_contents("$fixtureDir/token.tsr");
    $caPem = (string) file_get_contents("$fixtureDir/tsa-ca.pem");
    $digest = (string) file_get_contents("$fixtureDir/digest.bin");

    $anchor = new Rfc3161TimestampAnchor([
        'tsa_url' => 'https://tsa.example.test',
        'tsa_certificate' => "$fixtureDir/tsa-ca.pem",
    ]);

    expect($anchor->verifyTimestampToken($token, $digest, $caPem))->toBeTrue();

    // Flip a byte in the signed region -> openssl ts -verify fails.
    $corrupt = $token;
    $corrupt[(int) (strlen($corrupt) / 2)] = $corrupt[(int) (strlen($corrupt) / 2)] === "\x00" ? "\x01" : "\x00";

    expect($anchor->verifyTimestampToken($corrupt, $digest, $caPem))->toBeFalse();
});

it('builds and posts a timestamp request and stores the token as proof', function () use ($fixtureDir) {
    $token = (string) file_get_contents("$fixtureDir/token.tsr");

    Http::fake([
        'tsa.example.test/*' => Http::response($token, 200, ['Content-Type' => 'application/timestamp-reply']),
    ]);

    $anchor = new Rfc3161TimestampAnchor([
        'tsa_url' => 'https://tsa.example.test/tsa',
        'tsa_certificate' => "$fixtureDir/tsa-ca.pem",
    ]);

    $checkpoint = makeAnchorCheckpoint(); // helper in tests/Pest.php (Step 5 note)
    $receipt = $anchor->anchor($checkpoint);

    expect($receipt->provider)->toBe('rfc3161')
        ->and($receipt->proof)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->header('Content-Type')[0] === 'application/timestamp-query');
});
