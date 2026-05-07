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
