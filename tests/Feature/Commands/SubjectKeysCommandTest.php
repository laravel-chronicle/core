<?php

use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

it('lists active and erased subjects without key material', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('active'))
        ->metadata(['email' => 'x@y.test'])->commit();
    Chronicle::record()->actor(ref('a'))->action('a.two')->subject(ref('gone'))
        ->metadata(['email' => 'z@y.test'])->commit();
    app('chronicle')->eraseSubject('stdClass', 'gone');

    $this->artisan('chronicle:subject:keys')
        ->assertExitCode(0)
        ->expectsOutputToContain('active')
        ->expectsOutputToContain('erased');
});

it('emits json with state and entry counts and no wrapped_dek', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('active'))
        ->metadata(['email' => 'x@y.test'])->commit();

    // Use Artisan::call() (not $this->artisan()) so the JSON output is captured by Artisan::output().
    expect(Artisan::call('chronicle:subject:keys', ['--json' => true]))->toBe(0);

    // Capture output for structural assertions.
    $output = Artisan::output();
    $rows = json_decode($output, true);

    expect($rows)->toBeArray()
        ->and($rows[0])->toHaveKeys(['subject_type', 'subject_id', 'status', 'entry_count'])
        ->and($output)->not->toContain('wrapped_dek');
});

it('filters by status', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('active'))
        ->metadata(['email' => 'x@y.test'])->commit();
    Chronicle::record()->actor(ref('a'))->action('a.two')->subject(ref('gone'))
        ->metadata(['email' => 'z@y.test'])->commit();
    app('chronicle')->eraseSubject('stdClass', 'gone');

    // Use Artisan::call() (not $this->artisan()) so the JSON output is captured by Artisan::output().
    expect(Artisan::call('chronicle:subject:keys', ['--status' => 'erased', '--json' => true]))->toBe(0);
    $rows = json_decode(Artisan::output(), true);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['status'])->toBe('erased');
});
