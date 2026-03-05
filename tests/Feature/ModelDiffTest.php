<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;
use Chronicle\Tests\Fakes\FakeUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('fake_users', function (Blueprint $table) {
        $table->id();

        $table->string('name');

        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('fake_users');
});

it('generates diff from eloquent model changes', function () {
    $user = FakeUser::create([
        'name' => 'Alice',
    ]);

    $user->name = 'Bob';

    Chronicle::record()
        ->actor('system')
        ->action('user.updated')
        ->subject($user)
        ->modelDiff($user)
        ->commit();

    $entry = Entry::first();

    expect($entry->diff['name']['old'])
        ->toBe('Alice')
        ->and($entry->diff['name']['new'])
        ->toBe('Bob');
});
