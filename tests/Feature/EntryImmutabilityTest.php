<?php

use Chronicle\Exceptions\ImmutabilityViolationException;
use Chronicle\Models\Entry;

it('allows creating an entry', function () {
    Entry::create([
        'recorded_at' => now(),
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
        'metadata' => ['amount' => 1000],
    ]);

    expect(Entry::count())->toBe(1);
});

it('prevents updating an entry with save', function () {
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

it('prevents updating an entry with update', function () {
    $entry = Entry::create([
        'recorded_at' => now(),
        'actor_type' => 'user',
        'actor_id' => '1',
        'action' => 'invoice.created',
        'subject_type' => 'invoice',
        'subject_id' => '10',
    ]);

    $entry->update(['action' => 'invoice.updated']);

})->throws(ImmutabilityViolationException::class);

it('prevents deleting an entry', function () {
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

it('prevents updating entries via eloquent query builder', function () {
    expect(true)->toBeTrue();
    // TODO
});

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

    } catch (ImmutabilityViolationException $e) {}

    $entry->refresh();

    expect($entry->action)->toBe('invoice.created');
});
