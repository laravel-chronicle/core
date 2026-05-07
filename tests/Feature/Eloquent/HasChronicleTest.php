<?php

use Chronicle\Entry\Entry;
use Chronicle\Tests\Fakes\FakeChronicleModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('fake_chronicle_models', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('fake_chronicle_models');
});

it('records a created entry when a model is created', function () {
    FakeChronicleModel::create(['name' => 'Alice']);

    $entry = Entry::first();

    expect(Entry::count())->toBe(1)
        ->and($entry->action)->toBe('fake_chronicle_model.created')
        ->and($entry->subject_type)->toBe(FakeChronicleModel::class)
        ->and($entry->subject_id)->toBe('1')
        ->and($entry->actor_type)->toBe('system')
        ->and($entry->actor_id)->toBe('system');
});

it('records an updated entry with diff when a model is updated', function () {
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    Entry::query()->delete(); // clear the created entry

    $model->update(['name' => 'Bob']);

    $entry = Entry::first();

    expect(Entry::count())->toBe(1)
        ->and($entry->action)->toBe('fake_chronicle_model.updated')
        ->and($entry->subject_id)->toBe((string) $model->id)
        ->and($entry->diff)->toHaveKey('name')
        ->and($entry->diff['name']['old'])->toBe('Alice')
        ->and($entry->diff['name']['new'])->toBe('Bob');
});

it('does not record an updated entry when only timestamps change', function () {
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    Entry::query()->delete();

    $model->touch(); // updates updated_at only

    expect(Entry::count())->toBe(0);
});
