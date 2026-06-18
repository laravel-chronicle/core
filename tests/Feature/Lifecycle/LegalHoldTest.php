<?php

use Chronicle\Lifecycle\LegalHold;

beforeEach(fn () => $this->useEloquentDriver());

it('places, detects and releases a hold', function () {
    expect(LegalHold::isHeld('App\\User', '7'))->toBeFalse();

    LegalHold::place('App\\User', '7', 'litigation-123', 'legal@acme.test');
    expect(LegalHold::isHeld('App\\User', '7'))->toBeTrue();

    $released = LegalHold::release('App\\User', '7');
    expect($released)->toBe(1)
        ->and(LegalHold::isHeld('App\\User', '7'))->toBeFalse();
});

it('is idempotent on place (no duplicate active holds)', function () {
    LegalHold::place('App\\User', '7');
    LegalHold::place('App\\User', '7');

    expect(LegalHold::query()->whereNull('released_at')->count())->toBe(1);
});

it('places and releases via the command', function () {
    $this->artisan('chronicle:legal-hold', ['action' => 'place', 'type' => 'App\\User', 'id' => '7', '--reason' => 'case-9'])
        ->assertExitCode(0);
    expect(LegalHold::isHeld('App\\User', '7'))->toBeTrue();

    $this->artisan('chronicle:legal-hold', ['action' => 'release', 'type' => 'App\\User', 'id' => '7'])
        ->assertExitCode(0);
    expect(LegalHold::isHeld('App\\User', '7'))->toBeFalse();
});

it('rejects an unknown action', function () {
    $this->artisan('chronicle:legal-hold', ['action' => 'freeze', 'type' => 'App\\User', 'id' => '7'])
        ->assertExitCode(1);
});
