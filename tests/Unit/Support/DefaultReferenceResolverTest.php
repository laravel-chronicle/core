<?php

use Chronicle\Support\DefaultReferenceResolver;

it('resolves scalar references', function () {
    $resolver = new DefaultReferenceResolver;

    $ref = $resolver->resolve('system');

    expect($ref->type)->toBe('string');
});

it('resolves eloquent models', function () {
    $resolver = new DefaultReferenceResolver;

    $model = new class
    {
        public int $id = 42;
    };

    $ref = $resolver->resolve($model);

    expect($ref->id)->toBe('42');
});
