<?php

declare(strict_types=1);

use Chronicle\Support\DefaultReferenceResolver;
use Chronicle\Support\Reference;
use Illuminate\Database\Eloquent\Model;

it('resolves eloquent models', function () {
    $resolver = new DefaultReferenceResolver;

    $model = new class
    {
        public int $id = 42;
    };

    $ref = $resolver->resolve($model);

    expect($ref->id)->toBe('42');
});

it('resolves an Eloquent model to a Reference', function () {
    $model = new class extends Model
    {
        public function getKey(): int
        {
            return 42;
        }

        public static function getClass(): string
        {
            return self::class;
        }
    };

    $ref = (new DefaultReferenceResolver)->resolve($model);

    expect($ref)->toBeInstanceOf(Reference::class)
        ->and($ref->id)->toBe('42');
});

it('throws InvalidArgumentException when passed a scalar value', function () {
    expect(fn () => (new DefaultReferenceResolver)->resolve('some-string'))
        ->toThrow(InvalidArgumentException::class, 'Chronicle: scalar values cannot be used as actor or subject references.');
});

it('throws InvalidArgumentException when passed an integer', function () {
    expect(fn () => (new DefaultReferenceResolver)->resolve(123))
        ->toThrow(InvalidArgumentException::class);
});

it('throws a clear InvalidArgumentException when resolving an unsaved Eloquent model', function () {
    $unsaved = new class extends Model {};

    expect(fn () => (new DefaultReferenceResolver)->resolve($unsaved))
        ->toThrow(InvalidArgumentException::class, 'no primary key');
});
