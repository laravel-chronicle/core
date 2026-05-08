<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Query\LedgerQuery;
use Chronicle\Tests\Fakes\FakeChronicleModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;

// ─── helpers ─────────────────────────────────────────────────────────────────

function record(string $action, array $options = []): void
{
    $builder = Chronicle::record()
        ->action($action)
        ->subject(ref('ledger'));

    if (isset($options['actor'])) {
        $builder->actor($options['actor']);
    } else {
        $builder->actor('system');
    }

    if (isset($options['tags'])) {
        $builder->tags($options['tags']);
    }

    if (isset($options['correlation'])) {
        $builder->correlation($options['correlation']);
    }

    $builder->commit();
}

// ─── actor / subject ─────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('fake_chronicle_models', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('fake_chronicle_models');
});

it('forActor() returns only entries for that actor', function () {
    $actor = FakeChronicleModel::create(['name' => 'Alice']);

    Chronicle::record()->actor($actor)->action('actor.test')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('other.test')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->forActor($actor)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('actor.test');
});

it('forSubject() returns only entries for that subject', function () {
    $subject = FakeChronicleModel::create(['name' => 'Invoice']);
    Entry::query()->delete(); // HasChronicle records a created entry with $subject as subject — clear it

    Chronicle::record()->actor('system')->action('subject.test')->subject($subject)->commit();
    Chronicle::record()->actor('system')->action('other.test')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->forSubject($subject)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('subject.test');
});

// ─── action / actions ─────────────────────────────────────────────────────────

it('action() returns only entries matching the action', function () {
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('invoice.viewed')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->action('invoice.sent')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('invoice.sent');
});

it('actions() returns entries matching any of the given actions', function () {
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('invoice.viewed')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->actions(['invoice.sent', 'invoice.viewed'])->get();

    expect($results)->toHaveCount(2);
});

// ─── tags ─────────────────────────────────────────────────────────────────────

it('withTag() returns only entries carrying that tag', function () {
    Chronicle::record()->actor('system')->action('tagged.billing')->subject(ref('ledger'))->tags(['billing'])->commit();
    Chronicle::record()->actor('system')->action('entry.untagged')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->withTag('billing')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('tagged.billing');
});

it('withTags() applies AND semantics — entry must carry all tags', function () {
    Chronicle::record()->actor('system')->action('both.tags')->subject(ref('ledger'))->tags(['billing', 'invoicing'])->commit();
    Chronicle::record()->actor('system')->action('one.tag')->subject(ref('ledger'))->tags(['billing'])->commit();
    Chronicle::record()->actor('system')->action('no.tags')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->withTags(['billing', 'invoicing'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('both.tags');
});

it('withAnyTag() applies OR semantics — entry carrying either tag is returned', function () {
    Chronicle::record()->actor('system')->action('billing.only')->subject(ref('ledger'))->tags(['billing'])->commit();
    Chronicle::record()->actor('system')->action('shipping.only')->subject(ref('ledger'))->tags(['shipping'])->commit();
    Chronicle::record()->actor('system')->action('no.tags')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->withAnyTag(['billing', 'shipping'])->get();

    expect($results)->toHaveCount(2);
});

it('withAnyTag() vs withTags() — OR returns more entries than AND', function () {
    Chronicle::record()->actor('system')->action('billing.both')->subject(ref('ledger'))->tags(['billing', 'invoicing'])->commit();
    Chronicle::record()->actor('system')->action('billing.only')->subject(ref('ledger'))->tags(['billing'])->commit();

    $andCount = Chronicle::query()->withTags(['billing', 'invoicing'])->count();
    $orCount = Chronicle::query()->withAnyTag(['billing', 'invoicing'])->count();

    expect($andCount)->toBe(1)
        ->and($orCount)->toBe(2);
});

// ─── date filters ─────────────────────────────────────────────────────────────

it('since() excludes entries before the given date', function () {
    Chronicle::record()->actor('system')->action('old.entry')->subject(ref('ledger'))->commit();

    Carbon::setTestNow(now()->addSeconds(2));
    $cutoff = now();
    Chronicle::record()->actor('system')->action('new.entry')->subject(ref('ledger'))->commit();
    Carbon::setTestNow();

    $results = Chronicle::query()->since($cutoff)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('new.entry');
});

it('until() excludes entries after the given date', function () {
    Chronicle::record()->actor('system')->action('old.entry')->subject(ref('ledger'))->commit();

    Carbon::setTestNow(now()->addSeconds(2));
    $cutoff = now()->subSecond();
    Chronicle::record()->actor('system')->action('new.entry')->subject(ref('ledger'))->commit();
    Carbon::setTestNow();

    $results = Chronicle::query()->until($cutoff)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('old.entry');
});

it('between() returns only entries within the range', function () {
    Chronicle::record()->actor('system')->action('a.entry')->subject(ref('ledger'))->commit();

    Carbon::setTestNow(now()->addSeconds(2));
    $start = now();
    Chronicle::record()->actor('system')->action('b.entry')->subject(ref('ledger'))->commit();
    $end = now();
    Carbon::setTestNow();

    Carbon::setTestNow(now()->addSeconds(4));
    Chronicle::record()->actor('system')->action('c.entry')->subject(ref('ledger'))->commit();
    Carbon::setTestNow();

    $results = Chronicle::query()->between($start, $end)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('b.entry');
});

it('since() accepts a date string', function () {
    Chronicle::record()->actor('system')->action('test.entry')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->since('1970-01-01')->get();

    expect($results)->toHaveCount(1);
});

// ─── ordering ─────────────────────────────────────────────────────────────────

it('latest() returns entries newest-first', function () {
    Chronicle::record()->actor('system')->action('first.entry')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('second.entry')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->latest()->get();

    expect($results->first()->action)->toBe('second.entry')
        ->and($results->last()->action)->toBe('first.entry');
});

it('oldest() returns entries oldest-first (ledger order)', function () {
    Chronicle::record()->actor('system')->action('first.entry')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('second.entry')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->oldest()->get();

    expect($results->first()->action)->toBe('first.entry')
        ->and($results->last()->action)->toBe('second.entry');
});

it('get() defaults to oldest-first when no ordering is specified', function () {
    Chronicle::record()->actor('system')->action('first.entry')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('second.entry')->subject(ref('ledger'))->commit();

    $results = Chronicle::query()->get();

    expect($results->first()->action)->toBe('first.entry');
});

// ─── terminal methods ─────────────────────────────────────────────────────────

it('count() returns the correct integer', function () {
    Chronicle::record()->actor('system')->action('test.one')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('test.two')->subject(ref('ledger'))->commit();

    expect(Chronicle::query()->count())->toBe(2);
});

it('exists() returns true when entries are present', function () {
    Chronicle::record()->actor('system')->action('test.entry')->subject(ref('ledger'))->commit();

    expect(Chronicle::query()->exists())->toBeTrue();
});

it('exists() returns false when no entries match', function () {
    expect(Chronicle::query()->action('nonexistent.action')->exists())->toBeFalse();
});

it('first() returns one entry or null', function () {
    expect(Chronicle::query()->first())->toBeNull();

    Chronicle::record()->actor('system')->action('test.entry')->subject(ref('ledger'))->commit();

    expect(Chronicle::query()->first())->toBeInstanceOf(Entry::class);
});

it('paginate() returns a CursorPaginator', function () {
    Chronicle::record()->actor('system')->action('test.entry')->subject(ref('ledger'))->commit();

    $paginator = Chronicle::query()->paginate(perPage: 10);

    expect($paginator)->toBeInstanceOf(CursorPaginator::class)
        ->and($paginator->count())->toBe(1);
});

it('stream() returns a LazyCollection', function () {
    Chronicle::record()->actor('system')->action('test.entry')->subject(ref('ledger'))->commit();

    $stream = Chronicle::query()->stream();

    expect($stream)->toBeInstanceOf(LazyCollection::class)
        ->and($stream->count())->toBe(1);
});

// ─── chaining ─────────────────────────────────────────────────────────────────

it('multiple filters chain correctly', function () {
    $actor = FakeChronicleModel::create(['name' => 'Alice']);
    $other = FakeChronicleModel::create(['name' => 'Bob']);

    Carbon::setTestNow(now()->subDay());
    Chronicle::record()->actor($actor)->action('invoice.sent')->subject(ref('ledger'))->tags(['billing'])->commit();
    Carbon::setTestNow();

    Chronicle::record()->actor($actor)->action('invoice.sent')->subject(ref('ledger'))->tags(['billing'])->commit();
    Chronicle::record()->actor($other)->action('invoice.sent')->subject(ref('ledger'))->tags(['billing'])->commit();
    Chronicle::record()->actor($actor)->action('order.placed')->subject(ref('ledger'))->tags(['billing'])->commit();

    $results = Chronicle::query()
        ->forActor($actor)
        ->action('invoice.sent')
        ->withTag('billing')
        ->since(now()->startOfDay())
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->action)->toBe('invoice.sent');
});

it('Chronicle::query() is accessible via the facade', function () {
    expect(Chronicle::query())->toBeInstanceOf(LedgerQuery::class);
});
