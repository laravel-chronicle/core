<?php

use Chronicle\Tests\Fakes\FakeUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

it('renders the entries index page', function () {
    seedUiEntries(3);
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get('/chronicle')
        ->assertOk()
        ->assertViewIs('chronicle::entries.index')
        ->assertSee('invoice.created')
        ->assertSee('invoice.sent');
});

it('filters by action', function () {
    seedUiEntries(5);
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get('/chronicle?action=invoice.sent')
        ->assertOk()
        ->assertSee('invoice.sent')
        ->assertDontSee('invoice.created');
});

it('filters by tag', function () {
    seedUiEntries(6);
    $user = FakeUser::create(['name' => 'Admin']);

    $response = $this->actingAs($user)
        ->get('/chronicle?tag=billing')
        ->assertOk();

    $entries = $response->viewData('entries');
    expect($entries->total())->toBeGreaterThanOrEqual(1);
    foreach ($entries as $entry) {
        expect($entry->tags)->toContain('billing');
    }
});

it('filters by date range', function () {
    seedUiEntries(3);
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get('/chronicle?from='.now()->subDay()->format('Y-m-d').'&to='.now()->format('Y-m-d'))
        ->assertOk();
});

it('sorts ascending when sort=asc', function () {
    seedUiEntries(3);
    $user = FakeUser::create(['name' => 'Admin']);

    $response = $this->actingAs($user)
        ->get('/chronicle?sort=asc')
        ->assertOk();

    $entries = $response->viewData('entries');
    $ids = $entries->pluck('id')->all();
    expect($ids)->toBe(collect($ids)->sort()->values()->all());
});

it('passes filters array to the view', function () {
    $user = FakeUser::create(['name' => 'Admin']);

    $response = $this->actingAs($user)
        ->get('/chronicle?action=invoice.sent&tag=billing')
        ->assertOk();

    $filters = $response->viewData('filters');
    expect($filters['action'])->toBe('invoice.sent')
        ->and($filters['tag'])->toBe('billing');
});

it('shows empty state when no entries match', function () {
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get('/chronicle?action=nonexistent.action')
        ->assertOk()
        ->assertSee('No entries found');
});
