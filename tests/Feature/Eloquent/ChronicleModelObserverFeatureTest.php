<?php

use Chronicle\Eloquent\ChronicleModelObserver;
use Chronicle\Facades\Chronicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('fake_chronicle_models', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('fake_chronicle_models');
});

class ObservedModel extends Model
{
    protected $table = 'fake_chronicle_models';
    protected $guarded = [];
}

it('records created when observer is registered', function () {
    $fake = Chronicle::fake();
    ObservedModel::observe(ChronicleModelObserver::class);

    ObservedModel::create(['name' => 'Alice']);

    $fake->assertRecorded(fn ($e) => str_ends_with($e['action'], '.created'));
});

it('records updated with diff when observer is registered', function () {
    $fake = Chronicle::fake();
    ObservedModel::observe(ChronicleModelObserver::class);

    $model = ObservedModel::create(['name' => 'Alice']);
    $fake->assertRecordedCount(1);

    $model->update(['name' => 'Bob']);

    $fake->assertRecordedCount(2);
    $fake->assertRecorded(function (array $e): bool {
        return str_ends_with($e['action'], '.updated')
            && isset($e['diff']['name'])
            && $e['diff']['name']['old'] === 'Alice'
            && $e['diff']['name']['new'] === 'Bob';
    });
});

it('records deleted when observer is registered', function () {
    $fake = Chronicle::fake();
    ObservedModel::observe(ChronicleModelObserver::class);

    $model = ObservedModel::create(['name' => 'Alice']);
    $model->delete();

    $fake->assertRecorded(fn ($e) => str_ends_with($e['action'], '.deleted'));
});

it('Chronicle::observe() registers the base observer', function () {
    $fake = Chronicle::fake();
    Chronicle::observe(ObservedModel::class);

    ObservedModel::create(['name' => 'Test']);

    $fake->assertRecorded(fn ($e) => str_ends_with($e['action'], '.created'));
});

it('Chronicle::observe() accepts a custom observer class', function () {
    $customObserver = new class extends ChronicleModelObserver {
        protected function actionPrefix(Model $model): string
        {
            return 'custom_prefix';
        }
    };

    $fake = Chronicle::fake();
    ObservedModel::observe($customObserver);

    ObservedModel::create(['name' => 'Test']);

    $fake->assertRecorded(fn ($e) => $e['action'] === 'custom_prefix.created');
});

it('does not record touch()-only updates', function () {
    $fake = Chronicle::fake();
    ObservedModel::observe(ChronicleModelObserver::class);

    $model = ObservedModel::create(['name' => 'Alice']);
    $fake->assertRecordedCount(1);

    $model->touch();

    $fake->assertRecordedCount(1);
});
