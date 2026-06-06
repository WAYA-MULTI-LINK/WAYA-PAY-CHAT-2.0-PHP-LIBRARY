<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\WayaPay;

final class Transactions
{
    public function __construct(private readonly WayaPay $client)
    {
    }

    /**
     * GET /transaction/verify?reference=
     * Accepts a string or ['reference' => '...']. Trust status over assumptions.
     *
     * @param  string|array{reference:string} $input
     * @return array<string,mixed>
     */
    public function verify(string|array $input): array
    {
        $reference = is_string($input) ? $input : ($input['reference'] ?? null);
        WayaPay::requireFields(['reference' => $reference], ['reference'], 'transaction verify');

        return $this->client->request('GET', '/transaction/verify', null, ['reference' => $reference]);
    }

    /**
     * GET /transaction/history. One page.
     *
     * @param  array{page?:int,size?:int,status?:string,from?:string,to?:string} $filter
     * @return array<string,mixed>
     */
    public function history(array $filter = []): array
    {
        return $this->client->request('GET', '/transaction/history', null, [
            'page' => $filter['page'] ?? 0,
            'size' => $filter['size'] ?? 20,
            'status' => $filter['status'] ?? null,
            'from' => $filter['from'] ?? null,
            'to' => $filter['to'] ?? null,
        ]);
    }

    /**
     * Walk every page of history as one lazy stream. Built for reconciliation.
     *
     *   foreach ($client->transactions->historyAll(['status' => 'SUCCESS']) as $txn) { ... }
     *
     * @param  array{page?:int,size?:int,status?:string,from?:string,to?:string} $filter
     * @return \Generator<int,array<string,mixed>>
     */
    public function historyAll(array $filter = []): \Generator
    {
        $size = $filter['size'] ?? 20;
        $page = $filter['page'] ?? 0;

        while (true) {
            $data = $this->history(array_merge($filter, ['page' => $page, 'size' => $size]));
            foreach (($data['items'] ?? []) as $item) {
                yield $item;
            }
            $page++;
            $totalPages = $data['totalPages'] ?? 0;
            if (!$totalPages || $page >= $totalPages) {
                break;
            }
        }
    }
}
