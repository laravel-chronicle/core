<?php

use Chronicle\Exceptions\ImmutabilityViolationException;
use Chronicle\Models\Entry;

it('can create a chronicle entry', function () {

    Entry::create([
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
        'created_at' => now(),
    ]);

    expect(Entry::count())->toBe(1);

});
it('prevents updating entries', function () {

    $entry = Entry::create([
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
        'created_at' => now(),
    ]);

    $entry->action = 'invoice.updated';

    $entry->save();

})->throws(ImmutabilityViolationException::class);

it('prevents deleting entries', function () {
    $entry = Entry::create([
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
        'created_at' => now(),
    ]);

    $entry->delete();

})->throws(ImmutabilityViolationException::class);

it('prevents force deleting an entry', function () {
    $entry = Entry::create([
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
        'created_at' => now(),
    ]);

    $entry->forceDelete();

})->throws(ImmutabilityViolationException::class);

it('ensures database row is unchanged after failed update attempt', function () {
    $entry = Entry::create([
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
        'created_at' => now(),
    ]);

    try {

        $entry->action = 'invoice.updated';
        $entry->save();

    } catch (ImmutabilityViolationException $e) {
    }

    $entry->refresh();

    expect($entry->action)->toBe('invoice.created');
});

it('uses configured chronicle connection at runtime', function () {
    config()->set('chronicle.connection', 'testing');

    $entry = new Entry;

    expect($entry->getConnectionName())->toBe('testing');
});
