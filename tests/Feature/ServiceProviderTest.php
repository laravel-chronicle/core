<?php

use Chronicle\Contracts\EntryStore;
use Chronicle\Contracts\ReferenceResolver;

it('binds entry store', function () {
    expect(app(EntryStore::class))->not->toBeNull();
});

it('binds reference resolver', function () {
    expect(app(ReferenceResolver::class))->not->toBeNull();
});
