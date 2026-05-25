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

it('renders the stats page', function () {
    seedUiEntries(5);
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get('/chronicle/stats')
        ->assertOk()
        ->assertViewIs('chronicle::stats.index')
        ->assertSee('Total Entries')
        ->assertSee('Top Actions');
});

it('passes correct total to view', function () {
    seedUiEntries(7);
    $user = FakeUser::create(['name' => 'Admin']);

    $response = $this->actingAs($user)
        ->get('/chronicle/stats')
        ->assertOk();

    expect($response->viewData('total'))->toBe(7);
});

it('passes 30-day activity array with 30 entries', function () {
    $user = FakeUser::create(['name' => 'Admin']);

    $response = $this->actingAs($user)
        ->get('/chronicle/stats')
        ->assertOk();

    expect($response->viewData('activityByDay'))->toHaveCount(30);
});

it('top actions link back to filtered index', function () {
    seedUiEntries(4);
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get('/chronicle/stats')
        ->assertOk()
        ->assertSee(route('chronicle.entries.index', ['action' => 'invoice.created']));
});
