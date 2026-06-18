<?php

declare(strict_types=1);

use Chronicle\Http\Middleware\ChronicleUiEnabled;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('ChronicleUiEnabled aborts with 404 when disabled', function () {
    config(['chronicle.ui.enabled' => false]);

    $middleware = new ChronicleUiEnabled;
    $request = Request::create('/chronicle');

    expect(fn () => $middleware->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class);
});

it('ChronicleUiEnabled passes through when enabled', function () {
    config(['chronicle.ui.enabled' => true]);

    $middleware = new ChronicleUiEnabled;
    $request = Request::create('/chronicle');

    $response = $middleware->handle($request, fn () => response('ok'));
    expect($response->getContent())->toBe('ok');
});
