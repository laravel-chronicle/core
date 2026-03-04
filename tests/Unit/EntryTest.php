<?php

use Chronicle\Exceptions\ImmutabilityViolationException;
use Chronicle\Models\Entry;

it('can create a chronicle entry', function () {

    Entry::create([
        'recorded_at' => now(),
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
    ]);

    expect(Entry::count())->toBe(1);

});
it('prevents updating entries', function () {

    $entry = Entry::create([
        'recorded_at' => now(),
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
    ]);

    $entry->action = 'invoice.updated';

    $entry->save();

})->throws(ImmutabilityViolationException::class);

it('prevents deleting entries', function () {
    $entry = Entry::create([
        'recorded_at' => now(),
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
    ]);

    $entry->delete();

})->throws(ImmutabilityViolationException::class);

it('prevents force deleting an entry', function () {
    $entry = Entry::create([
        'recorded_at' => now(),
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
    ]);

    $entry->forceDelete();

})->throws(ImmutabilityViolationException::class);

it('ensures database row is unchanged after failed update attempt', function () {
    $entry = Entry::create([
        'recorded_at' => now(),
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
    ]);

    try {

        $entry->action = 'invoice.updated';
        $entry->save();

    } catch (ImmutabilityViolationException $e) {
    }

    $entry->refresh();

    expect($entry->action)->toBe('invoice.created');
});
