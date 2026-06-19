<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Exceptions\InvalidEntryModelException;
use Chronicle\Facades\Chronicle;
use Chronicle\Tests\Fakes\CustomEntry;

it('resolves the default entry model when unconfigured', function () {
    expect(Chronicle::entryModel())->toBe(Entry::class);
});

it('resolves a configured subclass', function () {
    config(['chronicle.models.entry' => CustomEntry::class]);

    expect(Chronicle::entryModel())->toBe(CustomEntry::class);
});

it('throws when the configured model does not extend Entry', function () {
    config(['chronicle.models.entry' => stdClass::class]);

    Chronicle::entryModel();
})->throws(InvalidEntryModelException::class);

it('throws when the configured model class does not exist', function () {
    config(['chronicle.models.entry' => 'App\\Nope\\MissingEntry']);

    Chronicle::entryModel();
})->throws(InvalidEntryModelException::class);

it('builds a query bound to the configured model', function () {
    config(['chronicle.models.entry' => CustomEntry::class]);

    expect(Chronicle::newEntryQuery()->getModel())->toBeInstanceOf(CustomEntry::class);
});
