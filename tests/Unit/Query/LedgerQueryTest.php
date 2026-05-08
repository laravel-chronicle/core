<?php

use Carbon\CarbonInterface;
use Chronicle\Entry\Entry;
use Chronicle\Query\LedgerQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

// Helper: a minimal Eloquent Model stand-in for actor/subject args
function fakeModel(): Model
{
    $m = Mockery::mock(Model::class);
    $m->shouldReceive('getKey')->andReturn(1);
    $m->shouldReceive('getMorphClass')->andReturn('App\\Models\\User');

    return $m;
}

function mockBuilder(): Builder
{
    /** @var Builder<Entry> $builder */
    $builder = Mockery::mock(Builder::class);

    return $builder;
}

it('forActor() delegates to the builder forActor scope', function () {
    $actor = fakeModel();
    $builder = mockBuilder();
    $builder->shouldReceive('forActor')->once()->with($actor)->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->forActor($actor);

    expect($result)->toBe($query);
});

it('forSubject() delegates to the builder forSubject scope', function () {
    $subject = fakeModel();
    $builder = mockBuilder();
    $builder->shouldReceive('forSubject')->once()->with($subject)->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->forSubject($subject);

    expect($result)->toBe($query);
});

it('action() delegates to the builder action scope', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('action')->once()->with('invoice.sent')->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->action('invoice.sent');

    expect($result)->toBe($query);
});

it('actions() calls whereIn on the builder', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('whereIn')
        ->once()
        ->with('action', ['invoice.sent', 'invoice.viewed'])
        ->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->actions(['invoice.sent', 'invoice.viewed']);

    expect($result)->toBe($query);
});

it('correlation() delegates to the builder correlation scope', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('correlation')->once()->with('corr-123')->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->correlation('corr-123');

    expect($result)->toBe($query);
});

it('workflow() delegates to the builder workflow scope', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('workflow')->once()->with('root-corr')->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->workflow('root-corr');

    expect($result)->toBe($query);
});

it('withTag() delegates to the builder withTag scope', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('withTag')->once()->with('billing')->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->withTag('billing');

    expect($result)->toBe($query);
});

it('withTags() delegates to the builder withTags scope', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('withTags')->once()->with(['billing', 'invoicing'])->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->withTags(['billing', 'invoicing']);

    expect($result)->toBe($query);
});

it('withAnyTag() calls where() once with a closure — not a flat chain of whereJsonContains', function () {
    $builder = mockBuilder();
    // Must be a single where() call with a closure — not multiple whereJsonContains() calls
    $builder->shouldReceive('where')->once()->with(Mockery::type('Closure'))->andReturnSelf();
    $builder->shouldNotReceive('whereJsonContains');

    $query = new LedgerQuery($builder);
    $result = $query->withAnyTag(['billing', 'shipping']);

    expect($result)->toBe($query);
});

it('since() calls where with >= and a CarbonInterface when given a CarbonInterface', function () {
    $date = Carbon::parse('2026-01-01');
    $builder = mockBuilder();
    $builder->shouldReceive('where')
        ->once()
        ->with('created_at', '>=', Mockery::type(CarbonInterface::class))
        ->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->since($date);

    expect($result)->toBe($query);
});

it('since() parses a valid date string to CarbonInterface', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('where')
        ->once()
        ->with('created_at', '>=', Mockery::type(CarbonInterface::class))
        ->andReturnSelf();

    $query = new LedgerQuery($builder);
    $query->since('2026-01-01');
});

it('since() throws InvalidArgumentException for an unparseable string', function () {
    $builder = mockBuilder();

    $query = new LedgerQuery($builder);

    expect(fn () => $query->since('not-a-date-xyz-garbage-99999'))->toThrow(
        InvalidArgumentException::class,
        'Chronicle LedgerQuery: invalid date string.'
    );
});

it('until() calls where with <= and a CarbonInterface', function () {
    $date = Carbon::parse('2026-12-31');
    $builder = mockBuilder();
    $builder->shouldReceive('where')
        ->once()
        ->with('created_at', '<=', Mockery::type(CarbonInterface::class))
        ->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->until($date);

    expect($result)->toBe($query);
});

it('until() throws InvalidArgumentException for an unparseable string', function () {
    $builder = mockBuilder();

    $query = new LedgerQuery($builder);

    expect(fn () => $query->until('not-a-date-xyz-garbage-99999'))->toThrow(
        InvalidArgumentException::class,
        'Chronicle LedgerQuery: invalid date string.'
    );
});

it('between() delegates to the builder between scope', function () {
    $start = Carbon::parse('2026-01-01');
    $end = Carbon::parse('2026-03-31');
    $builder = mockBuilder();
    $builder->shouldReceive('between')->once()->with($start, $end)->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->between($start, $end);

    expect($result)->toBe($query);
});

it('latest() calls orderByDesc(id) and returns static', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('orderByDesc')->once()->with('id')->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->latest();

    expect($result)->toBe($query);
});

it('oldest() calls orderBy(id) and returns static', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('orderBy')->once()->with('id')->andReturnSelf();

    $query = new LedgerQuery($builder);
    $result = $query->oldest();

    expect($result)->toBe($query);
});

it('get() applies default orderBy(id) when no ordering was called', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('orderBy')->once()->with('id')->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn(collect([]));

    $query = new LedgerQuery($builder);
    $query->get();
});

it('get() does not re-apply ordering when latest() was already called', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('orderByDesc')->once()->with('id')->andReturnSelf();
    $builder->shouldNotReceive('orderBy');
    $builder->shouldReceive('get')->once()->andReturn(collect([]));

    $query = new LedgerQuery($builder);
    $query->latest()->get();
});

it('get() does not re-apply ordering when oldest() was already called', function () {
    $builder = mockBuilder();
    // oldest() calls orderBy — verify it is called exactly once total
    $builder->shouldReceive('orderBy')->once()->with('id')->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn(collect([]));

    $query = new LedgerQuery($builder);
    $query->oldest()->get();
});

it('paginate() applies default orderBy(id) when no ordering was called', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('orderBy')->once()->with('id')->andReturnSelf();
    $builder->shouldReceive('cursorPaginate')
        ->once()
        ->withAnyArgs()
        ->andReturn(Mockery::mock(CursorPaginator::class));

    $query = new LedgerQuery($builder);
    $query->paginate();
});

it('count() delegates to the builder count()', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('count')->once()->andReturn(7);

    $query = new LedgerQuery($builder);

    expect($query->count())->toBe(7);
});

it('exists() delegates to the builder exists()', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('exists')->once()->andReturn(true);

    $query = new LedgerQuery($builder);

    expect($query->exists())->toBeTrue();
});

it('first() delegates to the builder first()', function () {
    $entry = Mockery::mock(Entry::class);
    $builder = mockBuilder();
    $builder->shouldReceive('first')->once()->andReturn($entry);

    $query = new LedgerQuery($builder);

    expect($query->first())->toBe($entry);
});

it('stream() calls cursor() on the builder', function () {
    $builder = mockBuilder();
    $builder->shouldReceive('cursor')
        ->once()
        ->andReturn(new LazyCollection([]));

    $query = new LedgerQuery($builder);
    $result = $query->stream();

    expect($result)->toBeInstanceOf(LazyCollection::class);
});
