<?php

declare(strict_types=1);

namespace WayaPay\Tests\Support;

/**
 * A transport that returns a queued sequence of [status, body] responses, one
 * per call. Useful for exercising the GET retry path. The last response repeats
 * once the queue is drained.
 */
final class SequenceTransport
{
    public int $calls = 0;

    /** @param array<int,array{0:int,1:string}> $responses */
    public function __construct(private readonly array $responses)
    {
    }

    /**
     * @param  array<int,string> $headers
     * @return array{0:int,1:string}
     */
    public function __invoke(string $method, string $url, array $headers, ?string $payload): array
    {
        $i = min($this->calls, count($this->responses) - 1);
        $this->calls++;
        return $this->responses[$i];
    }
}
