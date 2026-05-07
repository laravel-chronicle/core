<?php

use Chronicle\Entry\Entry;
use Chronicle\Tests\Fakes\FakeChronicleModel;
use Chronicle\Tests\Fakes\FakeUser;
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

it('records a deleted entry when a model is deleted', function () {
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    Entry::query()->delete();

    $model->delete();

    $entry = Entry::first();

    expect(Entry::count())->toBe(1)
        ->and($entry->action)->toBe('fake_chronicle_model.deleted')
        ->and($entry->subject_type)->toBe(FakeChronicleModel::class)
        ->and($entry->subject_id)->toBe((string) $model->id);
});

it('excludes chronicleIgnore fields from the recorded diff', function () {
    $model = new class extends FakeChronicleModel
    {
        protected array $chronicleIgnore = ['password'];

        protected function chronicleActionPrefix(): string
        {
            return 'fake_chronicle_model';
        }
    };
    $model->setTable('fake_chronicle_models');
    $model->fill(['name' => 'Alice', 'password' => 'secret'])->save();
    Entry::query()->delete();

    $model->update(['name' => 'Bob', 'password' => 'newsecret']);

    $entry = Entry::first();

    expect($entry->diff)->toHaveKey('name')
        ->and($entry->diff)->not->toHaveKey('password');
});

it('still records an updated entry when only a chronicleIgnore field changes', function () {
    $model = new class extends FakeChronicleModel
    {
        protected array $chronicleIgnore = ['password'];

        protected function chronicleActionPrefix(): string
        {
            return 'fake_chronicle_model';
        }
    };
    $model->setTable('fake_chronicle_models');
    $model->fill(['name' => 'Alice', 'password' => 'secret'])->save();
    Entry::query()->delete();

    $model->update(['password' => 'newsecret']); // only ignored field changed

    $entry = Entry::first();

    // Entry is recorded (something changed), but diff is empty/null.
    expect(Entry::count())->toBe(1)
        ->and($entry->action)->toBe('fake_chronicle_model.updated')
        ->and($entry->diff)->toBeNull();
});

it('uses the authenticated user as actor when one is logged in', function () {
    Schema::create('fake_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $user = FakeUser::create(['name' => 'Actor User']);
    $this->actingAs($user);

    FakeChronicleModel::create(['name' => 'Subject']);

    $entry = Entry::first();

    expect($entry->actor_type)->toBe(FakeUser::class)
        ->and($entry->actor_id)->toBe((string) $user->id);

    Schema::dropIfExists('fake_users');
});

it('falls back to system actor when no user is authenticated', function () {
    FakeChronicleModel::create(['name' => 'Alice']);

    $entry = Entry::first();

    expect($entry->actor_type)->toBe('system')
        ->and($entry->actor_id)->toBe('system');
});

it('uses a custom chronicleActor() when overridden on the model', function () {
    $model = new class extends FakeChronicleModel
    {
        protected function chronicleActor(): stdClass
        {
            $actor = new stdClass;
            $actor->id = 'custom-actor-99';

            return $actor;
        }

        protected function chronicleActionPrefix(): string
        {
            return 'fake_chronicle_model';
        }
    };
    $model->setTable('fake_chronicle_models');
    $model->fill(['name' => 'Subject'])->save();

    $entry = Entry::first();

    expect($entry->actor_id)->toBe('custom-actor-99')
        ->and($entry->actor_type)->toBe(stdClass::class);
});

it('derives the action prefix from the class name by default', function () {
    FakeChronicleModel::create(['name' => 'Alice']);

    expect(Entry::first()->action)->toBe('fake_chronicle_model.created');
});

it('uses a custom action prefix when chronicleActionPrefix() is overridden', function () {
    $model = new class extends FakeChronicleModel {
        protected function chronicleActionPrefix(): string
        {
            return 'order';
        }
    };
    $model->setTable('fake_chronicle_models');
    $model->fill(['name' => 'Alice'])->save();

    expect(Entry::first()->action)->toBe('order.created');
});
