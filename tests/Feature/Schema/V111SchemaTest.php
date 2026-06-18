<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

beforeEach(fn () => $this->useEloquentDriver());

it('adds range columns to the checkpoints table', function () {
    $table = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');

    expect(Schema::hasColumn($table, 'head_id'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'entry_count'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'previous_checkpoint_id'))->toBeTrue();
});

it('creates the checkpoint_anchors table with its columns', function () {
    $table = config('chronicle.tables.checkpoint_anchors', 'chronicle_checkpoint_anchors');

    expect(Schema::hasTable($table))->toBeTrue()
        ->and(Schema::hasColumns($table, [
            'id', 'checkpoint_id', 'provider', 'reference',
            'proof', 'status', 'anchored_at', 'created_at',
        ]))->toBeTrue();
});

it('creates the verification_runs table with its columns', function () {
    $table = config('chronicle.tables.verification_runs', 'chronicle_verification_runs');

    expect(Schema::hasTable($table))->toBeTrue()
        ->and(Schema::hasColumns($table, [
            'id', 'mode', 'last_checkpoint_id', 'verified_count',
            'status', 'created_at', 'updated_at',
        ]))->toBeTrue();
});
