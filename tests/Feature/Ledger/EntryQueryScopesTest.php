<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;
use Chronicle\Tests\Fakes\FakeActor;
use Chronicle\Tests\Fakes\FakeSubject;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
    Schema::create('fake_actors', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    Schema::create('fake_subjects', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('fake_actors');
    Schema::dropIfExists('fake_subjects');
});

it('filters entries by actor', function () {
    $actor = FakeActor::create();

    Chronicle::record()
        ->actor($actor)
        ->action('test.actor')
        ->subject('ledger')
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('test.actor')
        ->subject('ledger')
        ->commit();

    $entries = Entry::forActor($actor)->get();

    expect($entries)->toHaveCount(1);
});

it('filters entries by subject', function () {
    $subject = FakeSubject::create();

    Chronicle::record()
        ->actor('system')
        ->action('test.subject')
        ->subject($subject)
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('test.subject')
        ->subject('ledger')
        ->commit();

    $entries = Entry::forSubject($subject)->get();

    expect($entries)->toHaveCount(1);
});

it('filters entries by action', function () {
    Chronicle::record()
        ->actor('system')
        ->action('order.created')
        ->subject('ledger')
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('order.updated')
        ->subject('ledger')
        ->commit();

    $entries = Entry::action('order.created')->get();

    expect($entries)->toHaveCount(1);
});

it('filters entries by correlation id', function () {
    Chronicle::transaction(function () {

        Chronicle::record()
            ->actor('system')
            ->action('a')
            ->subject('ledger')
            ->commit();

    });

    Chronicle::record()
        ->actor('system')
        ->action('b')
        ->subject('ledger')
        ->commit();

    $correlation = Entry::first()->correlation_id;

    $entries = Entry::correlation($correlation)->get();

    expect($entries)->toHaveCount(1);
});

it('filters workflow entries using hierarchical correlation', function () {
    Chronicle::transaction(function () {

        Chronicle::record()
            ->actor('system')
            ->action('root')
            ->subject('ledger')
            ->commit();

        Chronicle::transaction(function () {

            Chronicle::record()
                ->actor('system')
                ->action('child')
                ->subject('ledger')
                ->commit();

        });

    });

    $root = Entry::first()->correlation_id;

    $entries = Entry::workflow($root)->get();

    expect($entries->count())->toBeGreaterThan(1);
});

it('filters entries by tag', function () {
    Chronicle::record()
        ->actor('system')
        ->action('tagged')
        ->subject('ledger')
        ->tags(['security'])
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('untagged')
        ->subject('ledger')
        ->commit();

    $entries = Entry::withTag('security')->get();

    expect($entries)->toHaveCount(1);
});

it('filters entries by multiple tags', function () {
    Chronicle::record()
        ->actor('system')
        ->action('tagged')
        ->subject('ledger')
        ->tags(['orders', 'checkout'])
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('tagged')
        ->subject('ledger')
        ->tags(['orders'])
        ->commit();

    $entries = Entry::withTags(['orders', 'checkout'])->get();

    expect($entries)->toHaveCount(1);
});

it('filters entries within a time range', function () {
    Chronicle::record()
        ->actor('system')
        ->action('old')
        ->subject('ledger')
        ->commit();

    $entries = Entry::between(
        now()->subMinute(),
        now()->addMinute()
    )->get();

    expect($entries)->not->toBeEmpty();
});

it('orders entries using latestFirst scope', function () {
    Chronicle::record()
        ->actor('system')
        ->action('first')
        ->subject('ledger')
        ->commit();

    sleep(1);

    Chronicle::record()
        ->actor('system')
        ->action('second')
        ->subject('ledger')
        ->commit();

    $entries = Entry::latestFirst()->get();

    expect($entries->first()->action)->toBe('second');
});
