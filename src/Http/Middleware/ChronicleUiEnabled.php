<?php

namespace Chronicle\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChronicleUiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('chronicle.ui.enabled', false)) {
            abort(404);
        }

        $response = $next($request);

        assert($response instanceof Response);

        return $response;
    }
}
