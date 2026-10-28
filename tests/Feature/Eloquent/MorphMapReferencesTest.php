<?php

declare(strict_types=1);

use Chronicle\Encryption\KeyEncryptionManager;
use Chronicle\Encryption\SubjectKey;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Lifecycle\LegalHold;
use Chronicle\Tests\Fakes\FakeChronicleModel;
use Chronicle\Tests\Fakes\FakeModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->useEloquentDriver();

    Schema::create('fake_chronicle_models', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Relation::morphMap(['fake' => FakeChronicleModel::class]);
});

afterEach(function () {
    // The morph map is process-static; reset it so tests don't leak into each other.
    Relation::morphMap([], false);
    Relation::requireMorphMap(false);
    Schema::dropIfExists('fake_chronicle_models');
});

function useMorphMap(bool $enabled = true): void
{
    config(['chronicle.references.use_morph_map' => $enabled]);
}

function enableEncryption(): void
{
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.fields' => ['metadata', 'context', 'diff'],
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
}

/**
 * Assert the ledger holds rows under both the class name and the alias, so a
 * test of mixed ledgers cannot pass on a ledger that is not mixed.
 */
function expectMixedTypes(string $column): void
{
    expect(Entry::query()->where($column, '!=', 'system')->pluck($column)->unique()->sort()->values()->all())
        ->toBe([FakeChronicleModel::class, 'fake']);
}

/**
 * Record one entry about the model, with the model as its actor too.
 */
function recordAbout(FakeChronicleModel $model, string $action, array $metadata = []): void
{
    Chronicle::record()->actor($model)->action($action)->subject($model)->metadata($metadata)->commit();
}

it('stores the class name by default, even with a morph map', function () {
    FakeChronicleModel::create(['name' => 'Alice']);

    expect(Entry::firstOrFail()->subject_type)->toBe(FakeChronicleModel::class);
});

it('stores the morph alias for the subject and the actor when use_morph_map is on', function () {
    useMorphMap();
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    Entry::query()->delete();

    recordAbout($model, 'fake.checked');

    $entry = Entry::firstOrFail();

    expect($entry->subject_type)->toBe('fake')
        ->and($entry->actor_type)->toBe('fake');
});

it('stores the class name for an unmapped model under an enforced morph map', function () {
    useMorphMap();
    Relation::enforceMorphMap([], false);

    $model = new FakeModel(['name' => 'Bob']);
    $model->save();

    Chronicle::record()->actor(ref('system'))->action('fake.checked')->subject($model)->commit();

    expect(Entry::firstOrFail()->subject_type)->toBe(FakeModel::class);
});

it('finds entries stored under the class name and under the alias with forSubject()', function () {
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    useMorphMap();
    recordAbout($model, 'fake.checked');

    expectMixedTypes('subject_type');

    expect(Entry::query()->forSubject($model)->count())->toBe(2);
});

it('finds entries stored under the class name and under the alias with forActor()', function () {
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    recordAbout($model, 'fake.first');
    useMorphMap();
    recordAbout($model, 'fake.second');

    expectMixedTypes('actor_type');

    expect(Entry::query()->where('actor_type', '!=', 'system')->count())->toBe(2)
        ->and(Entry::query()->forActor($model)->count())->toBe(2);
});

it('keeps one subject key for a subject stored under the class name and the alias', function () {
    enableEncryption();
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    recordAbout($model, 'fake.first', ['note' => 'before']);
    useMorphMap();
    recordAbout($model, 'fake.second', ['note' => 'after']);

    expectMixedTypes('subject_type');

    $entries = Entry::query()->orderBy('sequence')->get();

    expect(SubjectKey::query()->count())->toBe(1)
        ->and($entries->map(fn (Entry $entry) => $entry->decryptedMetadata())->all())
        ->toBe([[], ['note' => 'before'], ['note' => 'after']]);
});

it('erases entries stored under the class name when erasing by the alias', function () {
    enableEncryption();
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    recordAbout($model, 'fake.first', ['email' => 'alice@example.com']);
    useMorphMap();
    recordAbout($model, 'fake.second', ['email' => 'alice@example.com']);

    expectMixedTypes('subject_type');

    app('chronicle')->eraseSubject('fake', (string) $model->id);

    $readable = Entry::query()
        ->whereIn('action', ['fake.first', 'fake.second'])
        ->get()
        ->filter(fn (Entry $entry) => ! array_key_exists('_erased', $entry->decryptedMetadata()));

    expect($readable)->toBeEmpty();
});

it('treats a hold placed under the class name as a hold on the alias', function () {
    $model = FakeChronicleModel::create(['name' => 'Alice']);

    LegalHold::place(FakeChronicleModel::class, (string) $model->id, 'litigation');

    expect(LegalHold::isHeld('fake', (string) $model->id))->toBeTrue();
});

it('does not prune entries stored under the alias of a subject held under the class name', function () {
    useMorphMap();
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    LegalHold::place(FakeChronicleModel::class, (string) $model->id, 'litigation');

    $this->artisan('chronicle:prune', ['--before' => now()->addDay()->toDateString()])->assertExitCode(0);

    expect(Entry::query()->where('subject_type', 'fake')->count())->toBe(1);
});

it('keeps existing keys under each name apart and erases both', function () {
    // A ledger written with a custom resolver that stored the alias before
    // this option existed can hold one key under each name for a subject.
    enableEncryption();
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    recordAbout($model, 'fake.first', ['note' => 'class name key']);

    $provider = app(KeyEncryptionManager::class)->provider();
    SubjectKey::create([
        'subject_type' => 'fake',
        'subject_id' => (string) $model->id,
        'wrapped_dek' => $provider->wrap(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)),
        'kek_id' => $provider->kekId(),
        'status' => 'active',
        'created_at' => now(),
    ]);

    useMorphMap();
    recordAbout($model, 'fake.second', ['note' => 'alias key']);

    // Read as a fresh process would, without the DEKs cached on write.
    app()->forgetInstance(SubjectKeyManager::class);

    $notes = fn () => Entry::query()->whereIn('action', ['fake.first', 'fake.second'])->orderBy('sequence')->get()
        ->map(fn (Entry $entry) => $entry->decryptedMetadata()['note'] ?? '_erased')->all();

    expect($notes())->toBe(['class name key', 'alias key']);

    app('chronicle')->eraseSubject(FakeChronicleModel::class, (string) $model->id);
    app()->forgetInstance(SubjectKeyManager::class);

    expect($notes())->toBe(['_erased', '_erased'])
        ->and(SubjectKey::query()->whereNotNull('wrapped_dek')->count())->toBe(0);
});

it('hands out the key stored under each name when a subject has one under both', function () {
    enableEncryption();
    $manager = app(SubjectKeyManager::class);
    $classNameKey = $manager->getOrCreate(FakeChronicleModel::class, '1');

    $aliasKey = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    $provider = app(KeyEncryptionManager::class)->provider();
    SubjectKey::create([
        'subject_type' => 'fake',
        'subject_id' => '1',
        'wrapped_dek' => $provider->wrap($aliasKey),
        'kek_id' => $provider->kekId(),
        'status' => 'active',
        'created_at' => now(),
    ]);

    expect($manager->getOrCreate('fake', '1'))->toBe($aliasKey)
        ->and($manager->getOrCreate(FakeChronicleModel::class, '1'))->toBe($classNameKey);
});

it('counts entries under both names for a subject key in chronicle:subject:keys', function () {
    enableEncryption();
    $model = FakeChronicleModel::create(['name' => 'Alice']);
    recordAbout($model, 'fake.first');
    useMorphMap();
    recordAbout($model, 'fake.second');

    expectMixedTypes('subject_type');

    Artisan::call('chronicle:subject:keys', ['--json' => true]);

    /** @var list<array{subject_type: string, entry_count: int}> $rows */
    $rows = json_decode(Artisan::output(), true);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['subject_type'])->toBe(FakeChronicleModel::class)
        ->and($rows[0]['entry_count'])->toBe(3);
});
