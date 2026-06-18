<?php

declare(strict_types=1);

use Chronicle\Context\QueueJobContext;
use Illuminate\Contracts\Queue\Job;

it('returns null when no job has been set', function () {
    $context = new QueueJobContext;

    expect($context->current())->toBeNull();
});

it('returns the job after set is called', function () {
    $context = new QueueJobContext;
    $job = mock(Job::class);

    $context->set($job);

    expect($context->current())->toBe($job);
});

it('returns null after clear is called', function () {
    $context = new QueueJobContext;
    $job = mock(Job::class);

    $context->set($job);
    $context->clear();

    expect($context->current())->toBeNull();
});

it('replaces the job when set is called twice', function () {
    $context = new QueueJobContext;
    $first = mock(Job::class);
    $second = mock(Job::class);

    $context->set($first);
    $context->set($second);

    expect($context->current())->toBe($second);
});
