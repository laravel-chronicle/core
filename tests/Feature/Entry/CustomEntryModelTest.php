<?php

declare(strict_types=1);

use Chronicle\Facades\Chronicle;
use Chronicle\Tests\Fakes\CustomEntry;

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
