<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\WayaPay;

final class Banks
{
    public function __construct(private readonly WayaPay $client)
    {
    }

    /**
     * GET /account-enquiry/get-bank-list
     *
     * @return array<int,array<string,mixed>> List of banks with code, name, id, status.
     */
    public function list(): array
    {
        return $this->client->request('GET', '/account-enquiry/get-bank-list') ?? [];
    }
}
