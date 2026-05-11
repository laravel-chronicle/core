<?php

use Chronicle\Storage\ArrayDriver;
use Chronicle\Testing\ChronicleAssertions;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function () {
    ArrayDriver::flush();
});

it('assertNothingRecorded passes on empty storage', function () {
    $assertions = new ChronicleAssertions(new ArrayDriver());
    $assertions->assertNothingRecorded();
});

it('assertRecorded fails when nothing is recorded', function () {
    $assertions = new ChronicleAssertions(new ArrayDriver());
    expect(fn () => $assertions->assertRecorded())
        ->toThrow(AssertionFailedError::class);
});

it('assertRecordedCount counts all entries when no filter given', function () {
    $driver = new ArrayDriver();
    $driver->store(validEntryPayload());
    $driver->store(validEntryPayload());

    $assertions = new ChronicleAssertions($driver);
    $assertions->assertRecordedCount(2);
});

it('assertRecordedCount counts filtered entries', function () {
    $driver = new ArrayDriver();

    $a = validEntryPayload();
    $a['action'] = 'invoice.sent';
    $driver->store($a);

    $b = validEntryPayload();
    $b['action'] = 'invoice.created';
    $driver->store($b);

    $assertions = new ChronicleAssertions($driver);
    $assertions->assertRecordedCount(1, fn ($e) => $e['action'] === 'invoice.sent');
});

it('assertNotRecorded passes when filter matches nothing', function () {
    $driver = new ArrayDriver();
    $e = validEntryPayload();
    $e['action'] = 'invoice.sent';
    $driver->store($e);

    $assertions = new ChronicleAssertions($driver);
    $assertions->assertNotRecorded(fn ($e) => $e['action'] === 'invoice.deleted');
});

it('assertNotRecorded fails when filter matches an entry', function () {
    $driver = new ArrayDriver();
    $e = validEntryPayload();
    $e['action'] = 'invoice.sent';
    $driver->store($e);

    $assertions = new ChronicleAssertions($driver);
    expect(fn () => $assertions->assertNotRecorded(fn ($e) => $e['action'] === 'invoice.sent'))
        ->toThrow(AssertionFailedError::class);
});

it('entries() returns all recorded entries', function () {
    $driver = new ArrayDriver();
    $driver->store(validEntryPayload());
    $driver->store(validEntryPayload());

    $assertions = new ChronicleAssertions($driver);
    expect($assertions->entries())->toHaveCount(2);
});
