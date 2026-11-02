<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\WayaPay;
use WayaPay\WayaPayException;

final class Collect
{
    public function __construct(private readonly WayaPay $client)
    {
    }

    /**
     * POST /payment-collect/initiate
     * Initiates payment collection and returns hosted checkout details (including checkOutUrl).
     *
     * Required fields in $input:
     *   - transactionId: string (Merchant unique reference)
     *   - amount: string|numeric (Quoted string with up to 2 decimal places, e.g. "1500.00")
     *   - firstName: string
     *   - lastName: string
     *   - email: string
     *   - phone: string
     *
     * Optional fields:
     *   - currency: string (defaults to 'NGN')
     *   - description: string
     *   - meta: array
     *
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input): array
    {
        $body = array_merge(
            ['currency' => 'NGN'],
            $input,
        );
        WayaPay::requireFields(
            $body,
            ['transactionId', 'amount', 'firstName', 'lastName', 'email', 'phone'],
            'payment collect',
        );

        if (is_numeric($body['amount'])) {
            $body['amount'] = (string) $body['amount'];
        }

        return $this->client->request('POST', '/payment-collect/initiate', $body);
    }

    /**
     * Alias for {@see create()}.
     *
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function initiate(array $input): array
    {
        return $this->create($input);
    }

    /**
     * GET /payment-collect/status/{refNo}
     * Returns the current state of a deposit by its refNo (the gateway transactionId / webhook
     * OrderId). Use for reconciliation alongside the deposit webhook — the webhook is the primary
     * signal; this is the pull/safety-net path. Interpret the `status` field with
     * {@see \WayaPay\Status\CollectionStatus::fromApi()}.
     *
     * The returned array carries these wire fields:
     *   refNo, tranId, merchantId, amount, customerEmail, amountPaid, fee, currencyCode, status,
     *   settlementStatus, channel, processedBy, description, environment, tranDate
     *
     * @return array<string,mixed>
     */
    public function getStatus(string $refNo): array
    {
        if (trim($refNo) === '') {
            throw new WayaPayException('refNo is required', type: 'validation');
        }

        return $this->client->request('GET', '/payment-collect/status/' . rawurlencode($refNo));
    }
}
