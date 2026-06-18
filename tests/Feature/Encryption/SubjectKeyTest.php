<?php

declare(strict_types=1);

use Chronicle\Encryption\SubjectKey;
use Illuminate\Database\QueryException;

it('persists a subject key and reports active status', function () {
    $key = SubjectKey::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => '42',
        'wrapped_dek' => 'd3JhcHBlZA==',
        'kek_id' => 'local',
        'status' => 'active',
        'created_at' => now(),
    ]);

    expect($key->isErased())->toBeFalse()
        ->and($key->isActive())->toBeTrue()
        ->and($key->erased_at)->toBeNull();
});

it('reports erased status once tombstoned', function () {
    $key = SubjectKey::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => '7',
        'wrapped_dek' => null,
        'kek_id' => 'local',
        'status' => 'erased',
        'created_at' => now(),
        'erased_at' => now(),
    ]);

    expect($key->isErased())->toBeTrue()
        ->and($key->isActive())->toBeFalse();
});

it('enforces uniqueness on (subject_type, subject_id)', function () {
    SubjectKey::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => '1',
        'wrapped_dek' => 'eA==',
        'kek_id' => 'local',
        'status' => 'active',
        'created_at' => now(),
    ]);

    SubjectKey::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => '1',
        'wrapped_dek' => 'eQ==',
        'kek_id' => 'local',
        'status' => 'active',
        'created_at' => now(),
    ]);
})->throws(QueryException::class);
