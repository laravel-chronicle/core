<?php

use Chronicle\Eloquent\ModelDiffBuilder;
use Illuminate\Database\Eloquent\Model;

it('redacts hidden attributes from the diff', function () {
    $model = new class extends Model
    {
        protected $guarded = [];

        protected $hidden = ['password'];
    };

    $model->forceFill(['name' => 'old', 'password' => 'old-hash']);
    $model->syncOriginal();
    $model->forceFill(['name' => 'new', 'password' => 'new-hash']);

    $diff = ModelDiffBuilder::build($model, []);

    expect($diff)->toHaveKey('name')
        ->and($diff['name'])->toBe(['old' => 'old', 'new' => 'new'])
        ->and($diff)->toHaveKey('password')
        ->and($diff['password'])->toBe(['old' => '[redacted]', 'new' => '[redacted]']);
});
