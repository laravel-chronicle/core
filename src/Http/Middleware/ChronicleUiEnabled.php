<?php

declare(strict_types=1);

namespace Chronicle\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that gates the Chronicle UI routes behind the chronicle.ui.enabled flag.
 */
final class ChronicleUiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Config::boolean('chronicle.ui.enabled', false)) {
            abort(404);
        }

        $response = $next($request);

        assert($response instanceof Response);

        return $response;
    }
}
