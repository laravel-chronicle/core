<?php

use Chronicle\Eloquent\ChronicleModelObserver;
use Illuminate\Database\Eloquent\Model;

it('ignoredFields() always excludes created_at and updated_at', function () {
    $observer = new ChronicleModelObserver;
    $model = new class extends Model {};

    $ignored = (new ReflectionMethod($observer, 'ignoredFields'))
        ->invoke($observer, $model);

    expect($ignored)->toContain('created_at')
        ->and($ignored)->toContain('updated_at');
});

it('subclass can extend ignoredFields via $ignoredFields property', function () {
    $observer = new class extends ChronicleModelObserver
    {
        protected array $ignoredFields = ['internal_notes', 'cache_bust'];
    };
    $model = new class extends Model {};

    $ignored = (new ReflectionMethod($observer, 'ignoredFields'))
        ->invoke($observer, $model);

    expect($ignored)->toContain('created_at')
        ->and($ignored)->toContain('updated_at')
        ->and($ignored)->toContain('internal_notes')
        ->and($ignored)->toContain('cache_bust');
});

it('HasChronicle uses declared properties, not property_exists()', function () {
    $source = file_get_contents(__DIR__.'/../../../src/Eloquent/HasChronicle.php');
    expect($source)->not->toContain('property_exists(');
});
