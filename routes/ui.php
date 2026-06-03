<?php

use Chronicle\Http\Controllers\ChronicleUiController;
use Chronicle\Http\Middleware\ChronicleUiEnabled;
use Illuminate\Support\Facades\Route;

/** @var list<string> $uiMiddleware */
$uiMiddleware = config('chronicle.ui.middleware', ['web', 'auth']);

Route::middleware([ChronicleUiEnabled::class, ...$uiMiddleware])
    ->prefix(config('chronicle.ui.prefix', 'chronicle'))
    ->name('chronicle.')
    ->group(function (): void {
        Route::get('/', [ChronicleUiController::class, 'index'])
            ->name('entries.index');

        Route::get('/entries/{id}', [ChronicleUiController::class, 'show'])
            ->name('entries.show');

        Route::get('/stats', [ChronicleUiController::class, 'stats'])
            ->name('stats');
    });
