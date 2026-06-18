<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Tests\Fakes\FakeUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Schema::create('fake_users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('fake_users');
});

it('renders the entry detail page', function () {
    seedUiEntries(1);
    $entry = Entry::first();
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get("/chronicle/entries/$entry->id")
        ->assertOk()
        ->assertViewIs('chronicle::entries.show')
        ->assertSee($entry->id)
        ->assertSee($entry->action)
        ->assertSee($entry->payload_hash);
});

it('redirects to index when entry is not found', function () {
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get('/chronicle/entries/01KSHVDA9QJNC1Y3KGYMGT75HC')
        ->assertRedirect('/chronicle');
});

it('shows diff section when entry has a diff', function () {
    $id = Str::ulid()->toString();
    DB::table(config('chronicle.tables.entries'))->insert([
        'id' => $id,
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '1',
        'action' => 'invoice.updated',
        'subject_type' => 'App\\Models\\Invoice',
        'subject_id' => '1',
        'payload' => json_encode([], JSON_THROW_ON_ERROR),
        'payload_hash' => hash('sha256', '{}'),
        'chain_hash' => hash('sha256', 'x'),
        'metadata' => json_encode([], JSON_THROW_ON_ERROR),
        'context' => json_encode([], JSON_THROW_ON_ERROR),
        'tags' => json_encode([], JSON_THROW_ON_ERROR),
        'diff' => json_encode(['amount' => ['old' => 100, 'new' => 200]], JSON_THROW_ON_ERROR),
        'correlation_id' => null,
        'sequence' => 1,
        'checkpoint_id' => null,
        'created_at' => now()->toDateTimeString(),
    ]);

    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get("/chronicle/entries/$id")
        ->assertOk()
        ->assertSee('Changes')
        ->assertSee('amount')
        ->assertSee('100')
        ->assertSee('200');
});

it('returns 404 for a non-ULID id in show()', function () {
    $response = $this->get(route('chronicle.entries.show', ['id' => '<script>alert(1)</script>']));
    $response->assertStatus(404);
});
