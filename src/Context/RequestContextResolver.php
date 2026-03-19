<?php

namespace Chronicle\Context;

use Chronicle\Entry\PendingEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestContextResolver extends AbstractContextResolver
{
    public function contextKey(): string
    {
        return 'request';
    }

    public function resolve(PendingEntry $entry): ?array
    {
        if ($this->isRunningInConsole()) {
            return null;
        }

        /** @var Request $request */
        $request = app('request');

        $requestId = $request->header('X-Request-ID');

        if ($requestId === null) {
            $requestId = $request->attributes->get('_chronicle_request_id');

            if ($requestId === null) {
                $requestId = (string) Str::uuid();
                $request->attributes->set('_chronicle_request_id', $requestId);
            }
        }

        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'request_id' => $requestId,
        ];
    }

    protected function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }
}
