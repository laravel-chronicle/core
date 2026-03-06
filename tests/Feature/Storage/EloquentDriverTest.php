<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->useEloquentDriver();
});

class DriverTestUser
{
    public function __construct(public string $id) {}
}

class DriverTestInvoice
{
    public function __construct(public string $id) {}
}

describe('Eloquent Driver - persistence', function () {
    it('writes a row to chronicle_entries', function () {
        Chronicle::record()->actor('User')->action('order.placed')->subject('invoice')->commit();

        expect(DB::connection('testing')->table('chronicle_entries')->count())->toBe(1);
    });

    it('persists all morph columns correctly', function () {
        Chronicle::record()
            ->actor(new DriverTestUser('42'))
            ->action('invoice.approved')
            ->subject(new DriverTestInvoice('99'))
            ->commit();

        $row = DB::connection('testing')->table('chronicle_entries')->first();

        expect($row->actor_type)->toBe(DriverTestUser::class)
            ->and($row->actor_id)->toBe('42')
            ->and($row->action)->toBe('invoice.approved')
            ->and($row->subject_type)->toBe(DriverTestInvoice::class)
            ->and($row->subject_id)->toBe('99');
    });

    it('persists context as a JSON object', function () {
        Chronicle::record()
            ->actor('system')
            ->action('e')
            ->subject('system')
            ->context(['transport' => 'cli'])
            ->commit();

        $row = DB::connection('testing')->table('chronicle_entries')->first();
        $context = json_decode($row->context, true);

        expect($context)->toBeArray()->toHaveKey('transport', 'cli');
    });

    it('persists metadata as JSON', function () {
        Chronicle::record()
            ->actor('system')
            ->action('e')
            ->subject('system')
            ->metadata(['ip' => '127.0.0.1', 'source' => 'docs'])
            ->commit();

        $row = DB::connection('testing')->table('chronicle_entries')->first();
        $metadata = json_decode($row->metadata, true);

        expect($metadata)->toBeArray()
            ->toHaveKey('ip', '127.0.0.1')
            ->toHaveKey('source', 'docs');
    });

    it('stores a 26-character ULID in the id column', function () {
        Chronicle::record()->actor('system')->action('e')->subject('system')->commit();

        $row = DB::connection('testing')->table('chronicle_entries')->first();

        expect($row->id)->toBeString()->toHaveLength(26);
    });

    it('populates created_at and leaves updated_at absent', function () {
        Chronicle::record()->actor('system')->action('e')->subject('system')->commit();

        $row = DB::connection('testing')->table('chronicle_entries')->first();
        $columns = array_keys((array) $row);

        expect($row->created_at)->not->toBeNull()
            ->and(in_array('updated_at', $columns, true))->toBeFalse();
    });

    it('persists rows that are returned as Entry records', function () {
        Chronicle::record()->actor('system')->action('e')->subject('system')->commit();

        $entry = Entry::query()->first();

        expect($entry)->toBeInstanceOf(Entry::class)
            ->and($entry?->exists)->toBeTrue();
    });

    it('the stored Entry reflects persisted data', function () {
        Chronicle::record()
            ->actor('system')
            ->action('cron.ran')
            ->subject('scheduler')
            ->metadata(['channel' => 'scheduler'])
            ->commit();

        $entry = Entry::query()->first();

        expect($entry?->action)->toBe('cron.ran')
            ->and($entry?->actor_id)->toBe('system')
            ->and($entry?->metadata)->toBe(['channel' => 'scheduler']);
    });

    it('multiple commits produce multiple rows with unique IDs', function () {
        Chronicle::record()->actor('system')->action('a')->subject('job')->commit();
        Chronicle::record()->actor('system')->action('b')->subject('job')->commit();
        Chronicle::record()->actor('system')->action('c')->subject('job')->commit();

        $rows = DB::connection('testing')->table('chronicle_entries')->get();
        $ids = $rows->pluck('id')->unique();

        expect($rows)->toHaveCount(3)
            ->and($ids)->toHaveCount(3);
    });
});
