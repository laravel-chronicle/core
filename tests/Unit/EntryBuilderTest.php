<?php

use Chronicle\Contracts\ReferenceResolver;
use Chronicle\EntryBuilder;
use Chronicle\Exceptions\MissingActionException;
use Chronicle\Exceptions\MissingActorException;
use Chronicle\Exceptions\MissingSubjectException;
use Chronicle\Reference;

it('builds a valid entry payload', function () {
    $resolver = mock(ReferenceResolver::class);

    $resolver
        ->shouldReceive('resolve')
        ->twice()
        ->andReturn(
            new Reference('user', '1'),
            new Reference('invoice', '10')
        );

    $builder = new EntryBuilder($resolver);

    $entry = $builder
        ->actor('user:1')
        ->action('invoice.created')
        ->subject('invoice:10')
        ->metadata(['amount' => 100])
        ->tags(['billing'])
        ->build();

    expect($entry['actor_type'])->toBe('user')
        ->and($entry['actor_id'])->toBe('1')
        ->and($entry['subject_type'])->toBe('invoice')
        ->and($entry['subject_id'])->toBe('10')
        ->and($entry['tags'])->toBe(['billing']);
});

it('throws exception when actor is missing', function () {
    $resolver = mock(ReferenceResolver::class);

    $builder = new EntryBuilder($resolver);

    $builder
        ->action('invoice.created')
        ->subject('invoice:10')
        ->build();

})->throws(MissingActorException::class);

it('throws exception when subject is missing', function () {
    $resolver = mock(ReferenceResolver::class);

    $builder = new EntryBuilder($resolver);

    $builder
        ->actor('user:1')
        ->action('invoice.created')
        ->build();

})->throws(MissingSubjectException::class);

it('throws exception when action is missing', function () {
    $resolver = mock(ReferenceResolver::class);

    $builder = new EntryBuilder($resolver);

    $builder
        ->actor('user:1')
        ->subject('invoice:10')
        ->build();

})->throws(MissingActionException::class);
