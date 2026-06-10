<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\WayaPay;
use WayaPay\WayaPayException;

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

    /**
     * GET /payment-payout/status/{reference}
     * Returns the latest status of a payout by the reference you sent at initiation. Scoped to the
     * authenticated merchant — a reference belonging to another merchant (or a different environment)
     * returns 404. Interpret the `status` field with {@see \WayaPay\Status\PayoutStatus::fromApi()}.
     *
     * The returned array carries these wire fields:
     *   transactionReference, status, amount, destinationAccountNumber, destinationAccountName,
     *   destinationBankName, narration, createdAt
     *
     * @return array<string,mixed>
     */
    public function getStatus(string $reference): array
    {
        if (trim($reference) === '') {
            throw new WayaPayException('reference is required', type: 'validation');
        }

        return $this->client->request('GET', '/payment-payout/status/' . rawurlencode($reference));
    }
}
