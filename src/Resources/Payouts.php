<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\WayaPay;

final class Payouts
{
    public function __construct(private readonly WayaPay $client)
    {
    }

    /**
     * POST /payment-payout/initiate
     * Defaults currency NGN, auto generates reference when omitted.
     * PROCESSING means accepted, not settled. Verify with the reference afterwards.
     *
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function initiate(array $input): array
    {
        $body = array_merge(['currency' => 'NGN'], $input);
        if (empty($body['reference'])) {
            $body['reference'] = WayaPay::generateReference('PAYOUT');
        }
        WayaPay::requireFields(
            $body,
            ['amount', 'currency', 'accountNumber', 'bankCode', 'accountName', 'reference', 'narration'],
            'payout',
        );

        return $this->client->request('POST', '/payment-payout/initiate', $body);
    }
}
