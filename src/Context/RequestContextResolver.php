<?php

namespace Chronicle\Context;

use Chronicle\Entry\PendingEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestContextResolver extends AbstractContextResolver
{
    /** @var string[] */
    private const SENSITIVE_PARAMS = ['password', 'secret', 'token', 'api_token', 'key', 'access_token'];

    private const USER_AGENT_MAX_LENGTH = 512;

    public function __construct(
        private readonly Request $request,
    ) {
        //
    }

    public function contextKey(): string
    {
        return 'request';
    }

    public function resolve(PendingEntry $entry): ?array
    {
        if ($this->isRunningInConsole()) {
            return null;
        }

        $requestId = $this->request->header('X-Request-ID');

        if ($requestId === null) {
            $requestId = $this->request->attributes->get('_chronicle_request_id');

            if ($requestId === null) {
                $requestId = (string) Str::uuid();
                $this->request->attributes->set('_chronicle_request_id', $requestId);
            }
        }

        $userAgent = $this->request->userAgent();

        if (is_string($userAgent) && strlen($userAgent) > self::USER_AGENT_MAX_LENGTH) {
            $userAgent = substr($userAgent, 0, self::USER_AGENT_MAX_LENGTH);
        }

        return [
            'ip_address' => $this->request->ip(),
            'user_agent' => $userAgent,
            'url' => $this->sanitizeUrl($this->request->fullUrl()),
            'method' => $this->request->method(),
            'request_id' => $requestId,
        ];
    }

    protected function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }

    protected function sanitizeUrl(string $url): string
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['query'])) {
            return $url;
        }

        parse_str($parsed['query'], $params);

        foreach (self::SENSITIVE_PARAMS as $sensitive) {
            if (array_key_exists($sensitive, $params)) {
                $params[$sensitive] = '[redacted]';
            }
        }

        $parsed['query'] = http_build_query($params);

        return $this->rebuildUrl($parsed);
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    protected function rebuildUrl(array $parts): string
    {
        $url = '';

        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'].'://';
        }
        if (isset($parts['host'])) {
            $url .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }
        if (isset($parts['path'])) {
            $url .= $parts['path'];
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $url .= '?'.$parts['query'];
        }
        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }
}
