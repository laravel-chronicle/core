<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

it('actionPrefix() returns only entries with matching action prefix', function () {
    Chronicle::record()->actor('system')->action('invoice.created')->subject(ref('x'))->commit();
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('x'))->commit();
    Chronicle::record()->actor('system')->action('order.placed')->subject(ref('x'))->commit();

    $results = Chronicle::query()->actionPrefix('invoice.')->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('action')->all())->toContain('invoice.created', 'invoice.sent');
});

it('actionPrefix() returns empty when no entries match', function () {
    Chronicle::record()->actor('system')->action('order.placed')->subject(ref('x'))->commit();

    $results = Chronicle::query()->actionPrefix('invoice.')->get();

    expect($results)->toHaveCount(0);
});

it('actionPrefix() escapes LIKE special characters in the prefix', function () {
    Chronicle::record()->actor('system')->action('order.placed')->subject(ref('x'))->commit();

    // A literal % in the prefix must not match everything
    $results = Chronicle::query()->actionPrefix('%')->get();

    expect($results)->toHaveCount(0);
});

it('actionPrefix() is chainable with other filters', function () {
    Chronicle::record()->actor('system')->action('invoice.created')->subject(ref('x'))->commit();
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('x'))->commit();

    $results = Chronicle::query()
        ->actionPrefix('invoice.')
        ->action('invoice.sent')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('invoice.sent');
});

it('Entry::scopeActionPrefix() filters entries by prefix', function () {
    Chronicle::record()->actor('system')->action('invoice.created')->subject(ref('x'))->commit();
    Chronicle::record()->actor('system')->action('order.placed')->subject(ref('x'))->commit();

    $results = Entry::query()->actionPrefix('invoice.')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('invoice.created');
});
