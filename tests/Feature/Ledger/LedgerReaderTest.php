<?php

use Chronicle\Contracts\LedgerReader as LedgerReaderContract;
use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;
use Chronicle\Tests\Fakes\FakeActor;
use Chronicle\Tests\Fakes\FakeSubject;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

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

it('resolves ledger reader from manager and contract', function () {
    $readerFromManager = app('chronicle')->reader();
    $readerFromContract = app(LedgerReaderContract::class);

    expect($readerFromManager)->toBe($readerFromContract);
});

it('paginates entries in id order', function () {
    for ($i = 0; $i < 3; $i++) {
        Chronicle::record()
            ->actor('system')
            ->action('paginate.'.$i)
            ->subject('ledger')
            ->commit();
    }

    $reader = app(LedgerReaderContract::class);
    $page1 = $reader->paginate(perPage: 2);

    expect($page1)->toBeInstanceOf(CursorPaginator::class)
        ->and($page1->items())->toHaveCount(2);

    $ids = array_map(fn (Entry $entry): string => $entry->id, $page1->items());
    expect($ids[0] < $ids[1])->toBeTrue();
});

it('streams entries lazily in id order', function () {
    for ($i = 0; $i < 3; $i++) {
        Chronicle::record()
            ->actor('system')
            ->action('stream.'.$i)
            ->subject('ledger')
            ->commit();
    }

    $reader = app(LedgerReaderContract::class);
    $stream = $reader->stream();

    expect($stream)->toBeInstanceOf(LazyCollection::class);

    $ids = $stream->map(fn (Entry $entry): string => $entry->id)->values()->all();
    expect($ids)->toHaveCount(3)
        ->and($ids[0] < $ids[1])->toBeTrue()
        ->and($ids[1] < $ids[2])->toBeTrue();
});

it('filters by actor via reader', function () {
    $actor = FakeActor::create();

    Chronicle::record()->actor($actor)->action('actor.older')->subject('ledger')->commit();
    Chronicle::record()->actor($actor)->action('actor.newer')->subject('ledger')->commit();
    Chronicle::record()->actor('system')->action('actor.other')->subject('ledger')->commit();

    Entry::where('action', 'actor.older')->update(['created_at' => Carbon::now('UTC')->subMinutes(2)]);
    Entry::where('action', 'actor.newer')->update(['created_at' => Carbon::now('UTC')->subMinute()]);

    $entries = app(LedgerReaderContract::class)->forActor($actor);

    expect($entries)->toHaveCount(2)
        ->and($entries->first()->action)->toBe('actor.newer')
        ->and($entries->last()->action)->toBe('actor.older');
});

it('filters by subject via reader', function () {
    $subject = FakeSubject::create();

    Chronicle::record()->actor('system')->action('subject.hit')->subject($subject)->commit();
    Chronicle::record()->actor('system')->action('subject.miss')->subject('ledger')->commit();

    $entries = app(LedgerReaderContract::class)->forSubject($subject);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->action)->toBe('subject.hit');
});

it('filters by action via reader', function () {
    Chronicle::record()->actor('system')->action('orders.created')->subject('ledger')->commit();
    Chronicle::record()->actor('system')->action('orders.created')->subject('ledger')->commit();
    Chronicle::record()->actor('system')->action('orders.updated')->subject('ledger')->commit();

    $entries = app(LedgerReaderContract::class)->action('orders.created');

    expect($entries)->toHaveCount(2)
        ->and($entries->every(fn (Entry $entry): bool => $entry->action === 'orders.created'))->toBeTrue();
});

it('filters by correlation via reader', function () {
    $tx = Chronicle::transaction();

    $tx->entry()->actor('system')->action('corr.one')->subject('ledger')->commit();
    $tx->entry()->actor('system')->action('corr.two')->subject('ledger')->commit();

    Chronicle::record()->actor('system')->action('corr.other')->subject('ledger')->correlation((string) Str::uuid())->commit();

    $entries = app(LedgerReaderContract::class)->correlation($tx->id());

    expect($entries)->toHaveCount(2)
        ->and($entries->every(fn (Entry $entry): bool => $entry->correlation_id === $tx->id()))->toBeTrue();
});
