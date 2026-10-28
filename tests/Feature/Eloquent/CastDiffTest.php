<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Tests\Fakes\FakeCastModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('fake_cast_models', function (Blueprint $table) {
        $table->id();
        $table->boolean('active')->default(false);
        $table->text('settings')->nullable();
        $table->dateTime('published_at')->nullable();
        $table->string('status')->nullable();
        $table->text('secret')->nullable();
        $table->dateTime('normalized_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('fake_cast_models');
});

/**
 * Create a model, reload it from the database (the path from #235), apply the
 * update and return the diff of the entry it recorded.
 *
 * @param  array<string, mixed>  $before
 * @param  array<string, mixed>  $after
 * @return array<string, array{old: mixed, new: mixed}>
 */
function diffOfUpdate(array $before, array $after): array
{
    $model = FakeCastModel::create($before);
    Entry::query()->delete();

    FakeCastModel::findOrFail($model->id)->update($after);

    expect(Entry::count())->toBe(1);

    /** @var array<string, array{old: mixed, new: mixed}> */
    return Entry::firstOrFail()->diff;
}

it('records a datetime cast change as stored date strings on both sides', function () {
    $diff = diffOfUpdate(['published_at' => '2026-02-01 12:00:00'], ['published_at' => '2026-02-02 12:00:00']);

    expect($diff['published_at'])->toBe(['old' => '2026-02-01 12:00:00', 'new' => '2026-02-02 12:00:00']);
});

it('records a backed enum cast change as its backing values', function () {
    $diff = diffOfUpdate(['status' => 'draft'], ['status' => 'live']);

    expect($diff['status'])->toBe(['old' => 'draft', 'new' => 'live']);
});

it('records an array cast change as stored JSON on both sides', function () {
    $diff = diffOfUpdate(['settings' => ['a' => 1]], ['settings' => ['a' => 2]]);

    expect($diff['settings'])->toBe(['old' => '{"a":1}', 'new' => '{"a":2}']);
});

it('records a change through a custom cast whose set() returns an object', function () {
    $diff = diffOfUpdate(['normalized_at' => '2026-02-01 12:00:00'], ['normalized_at' => '2026-02-02 12:00:00']);

    expect($diff['normalized_at'])->toBe(['old' => '2026-02-01 12:00:00', 'new' => '2026-02-02 12:00:00']);
});

it('keeps a boolean cast change as booleans on both sides', function () {
    $diff = diffOfUpdate(['active' => false], ['active' => true]);

    expect($diff['active'])->toBe(['old' => false, 'new' => true]);
});

it('redacts an encrypted cast change and never writes the plaintext', function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);

    $diff = diffOfUpdate(['secret' => 'old-secret'], ['secret' => 'new-secret']);

    $stored = (string) json_encode(DB::table(config('chronicle.tables.entries', 'chronicle_entries'))->get());

    expect($diff['secret'])->toBe(['old' => '[redacted]', 'new' => '[redacted]'])
        ->and($stored)->not->toContain('old-secret')
        ->and($stored)->not->toContain('new-secret');
});
