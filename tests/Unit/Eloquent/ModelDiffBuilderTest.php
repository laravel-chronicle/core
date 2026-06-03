<?php

use Chronicle\Eloquent\ModelDiffBuilder;
use Illuminate\Database\Eloquent\Model;

it('returns empty diff when all dirty fields are ignored', function () {
    $model = new class extends Model
    {
        protected $fillable = ['name'];
    };
    $model->fill(['name' => 'old']);
    $model->syncOriginal();
    $model->name = 'new';

    $diff = ModelDiffBuilder::build($model, ['name']);

    expect($diff)->toBeEmpty();
});

it('returns old/new pairs for changed non-ignored fields', function () {
    $model = new class extends Model
    {
        protected $fillable = ['name', 'email'];
    };
    $model->fill(['name' => 'Alice', 'email' => 'alice@example.com']);
    $model->syncOriginal();
    $model->name = 'Bob';

    $diff = ModelDiffBuilder::build($model, []);

    expect($diff)->toHaveKey('name')
        ->and($diff['name']['old'])->toBe('Alice')
        ->and($diff['name']['new'])->toBe('Bob');
});
