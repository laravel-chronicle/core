<?php

declare(strict_types=1);

use Chronicle\Events\EntryRecorded;
use Chronicle\Facades\Chronicle;
use Chronicle\Tests\Fakes\CustomEntry;
use Chronicle\Verification\IntegrityVerifier;

beforeEach(function () {
    config(['chronicle.models.entry' => CustomEntry::class]);
    $this->useEloquentDriver();
});

it('returns the configured subclass from the ledger reader', function () {
    Chronicle::record()->actor(ref('a'))->action('reader.read')->subject(ref('s'))->commit();

    $rows = Chronicle::reader()->action('reader.read');

    expect($rows->first())->toBeInstanceOf(CustomEntry::class);
});

it('returns the configured subclass from the fluent query', function () {
    Chronicle::record()->actor(ref('a'))->action('query.read')->subject(ref('s'))->commit();

    expect(Chronicle::query()->action('query.read')->first())->toBeInstanceOf(CustomEntry::class);
});

it('verifies a clean ledger recorded under the configured subclass', function () {
    Chronicle::record()->actor(ref('a'))->action('verify.one')->subject(ref('s'))->commit();
    Chronicle::record()->actor(ref('a'))->action('verify.two')->subject(ref('s'))->commit();

    $result = app(IntegrityVerifier::class)->verify();

    expect($result->isValid())->toBeTrue();
});

it('detects tampering inside a ledger recorded under the configured subclass', function () {
    Chronicle::record()->actor(ref('a'))->action('verify.tamper')->subject(ref('s'))->commit();

    $entry = Chronicle::newEntryQuery()->first();
    DB::connection(config('chronicle.connection'))
        ->table((new CustomEntry)->getTable())
        ->where('id', $entry->id)
        ->update(['action' => 'verify.tampered']);

    $result = app(IntegrityVerifier::class)->verify();

    expect($result->isValid())->toBeFalse();
});

it('persists and emits the configured subclass on record', function () {
    Event::fake([EntryRecorded::class]);

    Chronicle::record()->actor(ref('a'))->action('store.read')->subject(ref('s'))->commit();

    Event::assertDispatched(
        EntryRecorded::class,
        fn (EntryRecorded $event) => $event->entry instanceof CustomEntry,
    );
});
