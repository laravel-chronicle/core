<?php

declare(strict_types=1);

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

it('redirects unauthenticated requests to login', function () {
    $this->get('/chronicle')->assertRedirect('/login');
});

it('allows authenticated users through to index', function () {
    $user = FakeUser::create(['name' => 'Admin']);

    $this->actingAs($user)
        ->get('/chronicle')
        ->assertOk();
});
