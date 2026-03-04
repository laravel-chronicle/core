<?php

use Chronicle\ChronicleManager;
use Chronicle\Contracts\EntryStore;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\EntryBuilder;

it('creates entry builder', function () {
    $store = mock(EntryStore::class);
    $resolver = mock(ReferenceResolver::class);

    $manager = new ChronicleManager($store, $resolver);

    expect($manager->entry())->toBeInstanceOf(EntryBuilder::class);
});
