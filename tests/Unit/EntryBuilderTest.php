<?php

use Chronicle\EntryBuilder;
use Chronicle\Exceptions\MissingActionException;
use Chronicle\Exceptions\MissingActorException;
use Chronicle\Exceptions\MissingSubjectException;

it('builds a valid entry payload', function () {
    $builder = new EntryBuilder;

    $entry = $builder
        ->actor('user:1')
        ->action('invoice.created')
        ->subject('invoice:10')
        ->metadata(['amount' => 100])
        ->tags(['Billing'])
        ->build();

    expect($entry)->toHaveKey('actor_type')
        ->and($entry)->toHaveKey('subject_type')
        ->and($entry['tags'])->toBe(['billing']);
});

it('throws a MissingActionException when action is missing', function () {
    $builder = new EntryBuilder;

    $builder
        ->actor('user:1')
        ->subject('invoice:10')
        ->build();
})->throws(MissingActionException::class);

it('throws a MissingActorException when actor is missing', function () {
    $builder = new EntryBuilder;

    $builder
        ->action('invoice.created')
        ->subject('invoice:10')
        ->build();
})->throws(MissingActorException::class);

it('throws a MissingSubjectException when subject is missing', function () {
    $builder = new EntryBuilder;

    $builder
        ->actor('user:1')
        ->action('invoice.created')
        ->build();
})->throws(MissingSubjectException::class);
