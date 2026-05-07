<?php

use Chronicle\ChronicleManager;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Entry\EntryBuilder;
use Chronicle\Exceptions\MissingActionException;
use Chronicle\Exceptions\MissingActorException;
use Chronicle\Exceptions\MissingSubjectException;
use Chronicle\Support\Reference;

it('builds a valid entry payload', function () {
    $resolver = mock(ReferenceResolver::class);

    $resolver
        ->shouldReceive('resolve')
        ->twice()
        ->andReturn(
            new Reference('user', '1'),
            new Reference('invoice', '10')
        );

    $manager = mock(ChronicleManager::class);
    $manager
        ->shouldReceive('currentCorrelation')
        ->andReturn(null)
        ->byDefault();

    $builder = new EntryBuilder($resolver, $manager);

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
        ->and($entry['metadata'])->toBe(['amount' => 100]);
});

it('throws exception when actor is missing', function () {
    $resolver = mock(ReferenceResolver::class);

    $manager = mock(ChronicleManager::class);
    $manager
        ->shouldReceive('currentCorrelation')
        ->andReturn(null)
        ->byDefault();

    $builder = new EntryBuilder($resolver, $manager);

    $builder
        ->action('invoice.created')
        ->subject('invoice:10')
        ->build();

})->throws(MissingActorException::class);

it('throws exception when subject is missing', function () {
    $resolver = mock(ReferenceResolver::class);

    $manager = mock(ChronicleManager::class);
    $manager
        ->shouldReceive('currentCorrelation')
        ->andReturn(null)
        ->byDefault();

    $builder = new EntryBuilder($resolver, $manager);

    $builder
        ->actor('user:1')
        ->action('invoice.created')
        ->build();

})->throws(MissingSubjectException::class);

it('throws exception when action is missing', function () {
    $resolver = mock(ReferenceResolver::class);

    $manager = mock(ChronicleManager::class);
    $manager
        ->shouldReceive('currentCorrelation')
        ->andReturn(null)
        ->byDefault();

    $builder = new EntryBuilder($resolver, $manager);

    $builder
        ->actor('user:1')
        ->subject('invoice:10')
        ->build();

})->throws(MissingActionException::class);

it('accepts falsy-but-valid actor and subject values', function () {
    $resolver = mock(ReferenceResolver::class);

    $resolver
        ->shouldReceive('resolve')
        ->twice()
        ->andReturn(
            new Reference('int', '0'),
            new Reference('string', '0')
        );

    $manager = mock(ChronicleManager::class);
    $manager
        ->shouldReceive('currentCorrelation')
        ->andReturn(null)
        ->byDefault();

    $builder = new EntryBuilder($resolver, $manager);

    $entry = $builder
        ->actor(0)
        ->action('invoice.created')
        ->subject('0')
        ->build();

    expect($entry['actor_id'])->toBe('0')
        ->and($entry['subject_id'])->toBe('0');
});

it('accepts action set to string zero', function () {
    $resolver = mock(ReferenceResolver::class);

    $resolver
        ->shouldReceive('resolve')
        ->twice()
        ->andReturn(
            new Reference('user', '1'),
            new Reference('invoice', '10')
        );

    $manager = mock(ChronicleManager::class);
    $manager
        ->shouldReceive('currentCorrelation')
        ->andReturn(null)
        ->byDefault();

    $builder = new EntryBuilder($resolver, $manager);

    $entry = $builder
        ->actor('user:1')
        ->action('0')
        ->subject('invoice:10')
        ->build();

    expect($entry['action'])->toBe('0');
});

it('normalizes the system actor without using the resolver', function () {
    $resolver = mock(ReferenceResolver::class);

    $resolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn(new Reference('invoice', '10'));

    $manager = mock(ChronicleManager::class);
    $manager
        ->shouldReceive('currentCorrelation')
        ->andReturn(null)
        ->byDefault();

    $builder = new EntryBuilder($resolver, $manager);

    $entry = $builder
        ->actor('system')
        ->action('invoice.created')
        ->subject('invoice:10')
        ->build();

    expect($entry['actor_type'])->toBe('system')
        ->and($entry['actor_id'])->toBe('system');
});

it('throws exception when action is blank whitespace', function () {
    $resolver = mock(ReferenceResolver::class);

    $manager = mock(ChronicleManager::class);
    $manager
        ->shouldReceive('currentCorrelation')
        ->andReturn(null)
        ->byDefault();

    $builder = new EntryBuilder($resolver, $manager);

    $builder
        ->actor('user:1')
        ->action('   ')
        ->subject('invoice:10')
        ->build();

})->throws(MissingActionException::class);

it('keeps diff keys in alphabetical order when using change() in non-alphabetical order', function () {
    $resolver = mock(Chronicle\Contracts\ReferenceResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn(
        new Chronicle\Support\Reference('order', '5'),
    );
    $manager = mock(Chronicle\ChronicleManager::class);
    $manager->shouldReceive('currentCorrelation')->andReturn(null)->byDefault();

    $builder = new Chronicle\Entry\EntryBuilder($resolver, $manager);

    $payload = $builder
        ->actor('system')
        ->action('order.updated')
        ->subject('order:5')
        ->change('status', 'pending', 'confirmed')   // s > a
        ->change('amount', 100, 200)                  // a comes first alphabetically
        ->build();

    expect(array_keys($payload['diff']))->toBe(['amount', 'status']);
});

it('throws InvalidArgumentException when diff() receives a malformed change entry', function () {
    $resolver = mock(Chronicle\Contracts\ReferenceResolver::class);
    $manager = mock(Chronicle\ChronicleManager::class);

    $builder = new Chronicle\Entry\EntryBuilder($resolver, $manager);

    expect(fn () => $builder->diff(['field' => 'not-an-array']))
        ->toThrow(InvalidArgumentException::class, 'Chronicle EntryBuilder: diff entry for key "field" must be an array with "old" and "new" keys.');
});
