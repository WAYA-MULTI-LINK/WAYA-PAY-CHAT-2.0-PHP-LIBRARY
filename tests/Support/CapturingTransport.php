<?php

declare(strict_types=1);

namespace WayaPay\Tests\Support;

/**
 * A transport that returns a fixed [status, body] and records every call so a
 * test can assert on the method, URL, headers, and request payload it saw.
 */
final class CapturingTransport
{
    /** @var array<int,array{method:string,url:string,headers:array<int,string>,payload:?string}> */
    public array $calls = [];

    public function __construct(
        private readonly int $status = 200,
        private readonly string $body = '{"success":true,"code":"00","data":{}}',
    ) {
    }

    /**
     * @param  array<int,string> $headers
     * @return array{0:int,1:string}
     */
    public function __invoke(string $method, string $url, array $headers, ?string $payload): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'payload');
        return [$this->status, $this->body];
    }

    /** @return array{method:string,url:string,headers:array<int,string>,payload:?string} */
    public function last(): array
    {
        $last = end($this->calls);
        return $last === false ? ['method' => '', 'url' => '', 'headers' => [], 'payload' => null] : $last;
    }

    public function count(): int
    {
        return count($this->calls);
    }

    /** @return array<string,mixed> The last request body, JSON decoded. */
    public function lastBody(): array
    {
        $payload = $this->last()['payload'];
        return $payload ? (array) json_decode($payload, true) : [];
    }
}
