<?php

declare(strict_types=1);

use Chronicle\Facades\Chronicle;
use Chronicle\Support\DefaultReferenceLookup;
use Chronicle\Tests\Fakes\FakeChronicleModel;
use Illuminate\Database\Eloquent\Relations\Relation;

afterEach(function () {
    // morphMap is process-static; reset so tests don't leak into each other.
    Relation::morphMap([], false);
});

it('resolves a stored FQCN to its class with no morph map', function () {
    $lookup = new DefaultReferenceLookup;

    $resolved = $lookup->resolve(FakeChronicleModel::class, '7');

    expect($resolved->class)->toBe(FakeChronicleModel::class)
        ->and($resolved->exists())->toBeTrue()
        ->and($resolved->id)->toBe('7')
        ->and($resolved->label)->toBe('Fake Chronicle Model #7');
});

it('resolves a morph alias to its class', function () {
    Relation::morphMap(['fake' => FakeChronicleModel::class]);
    $lookup = new DefaultReferenceLookup;

    $resolved = $lookup->resolve('fake', '3');

    expect($resolved->class)->toBe(FakeChronicleModel::class)
        ->and($resolved->exists())->toBeTrue();
});

it('still resolves the FQCN when a morph map is configured', function () {
    Relation::morphMap(['fake' => FakeChronicleModel::class]);
    $lookup = new DefaultReferenceLookup;

    expect($lookup->resolve(FakeChronicleModel::class, '1')->class)
        ->toBe(FakeChronicleModel::class);
});

it('falls back to a humanised basename and id for an unknown class', function () {
    $lookup = new DefaultReferenceLookup;

    $resolved = $lookup->resolve('App\\Models\\Ghost', '42');

    expect($resolved->class)->toBeNull()
        ->and($resolved->exists())->toBeFalse()
        ->and($resolved->label)->toBe('Ghost #42');
});

it('labels the system actor as System', function () {
    $lookup = new DefaultReferenceLookup;

    expect($lookup->resolve('system', 'system')->label)->toBe('System')
        ->and($lookup->label('system', 'system'))->toBe('System');
});

it('exposes the resolver through the Chronicle facade', function () {
    expect(Chronicle::resolveReference(FakeChronicleModel::class, '5')->label)
        ->toBe('Fake Chronicle Model #5')
        ->and(Chronicle::referenceLabel('App\\Models\\Ghost', '9'))
        ->toBe('Ghost #9');
});
