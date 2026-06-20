<?php

declare(strict_types=1);

use Chronicle\Facades\Chronicle;
use Chronicle\Support\DefaultReferenceLookup;
use Chronicle\Tests\Fakes\FakeChronicleModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;

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

it('does not query the database when resolving or labelling without hydration', function () {
    $lookup = new DefaultReferenceLookup;

    DB::enableQueryLog();
    DB::flushQueryLog();

    $lookup->resolve(FakeChronicleModel::class, '1');
    $lookup->label(FakeChronicleModel::class, '1');

    expect(DB::getQueryLog())->toHaveCount(0);
});

it('hydrates the model and reads the configured label attribute when asked', function () {
    Schema::create('fake_chronicle_models', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    FakeChronicleModel::create(['name' => 'Alice']); // id 1

    $lookup = new DefaultReferenceLookup;

    expect($lookup->model(FakeChronicleModel::class, '1')?->getAttribute('name'))->toBe('Alice')
        ->and($lookup->label(FakeChronicleModel::class, '1', hydrate: true))->toBe('Alice')
        ->and(Chronicle::referenceModel(FakeChronicleModel::class, '1')?->getAttribute('name'))->toBe('Alice');

    Schema::dropIfExists('fake_chronicle_models');
});

it('falls back to the default label when hydration finds no row', function () {
    Schema::create('fake_chronicle_models', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    $lookup = new DefaultReferenceLookup;

    expect($lookup->label(FakeChronicleModel::class, '999', hydrate: true))
        ->toBe('Fake Chronicle Model #999')
        ->and($lookup->model(FakeChronicleModel::class, '999'))->toBeNull();

    Schema::dropIfExists('fake_chronicle_models');
});

it('returns null from model() for an unknown or non-model class', function () {
    $lookup = new DefaultReferenceLookup;

    expect($lookup->model('App\\Models\\Ghost', '1'))->toBeNull()
        ->and($lookup->model('system', 'system'))->toBeNull();
});
