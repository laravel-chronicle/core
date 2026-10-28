<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Tests\Fakes\Prepare\Currency;
use Chronicle\Tests\Fakes\Prepare\StoreCurrencyAsMinorAmount;

beforeEach(fn () => $this->useEloquentDriver());

it('records a value object a prepare extension converts before validation', function () {
    Chronicle::extendEntry(StoreCurrencyAsMinorAmount::class);

    Chronicle::record()->actor(ref('a'))->action('invoice.updated')->subject(ref('invoice-1'))
        ->change('total', Currency::fromMinorAmount(500), Currency::fromMinorAmount(750))
        ->commit();

    expect(Entry::firstOrFail()->diff)->toBe(['total' => ['old' => 500, 'new' => 750]]);
});
