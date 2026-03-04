<?php

use Chronicle\ChronicleManager;
use Chronicle\Contracts\EntryStore;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\EntryBuilder;
use Chronicle\Serialization\CanonicalPayloadSerializer;

it('creates entry builder', function () {
    $manager = mock(ChronicleManager::class);

    expect($manager->entry())->toBeInstanceOf(EntryBuilder::class);
});
