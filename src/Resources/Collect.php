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
     * Defaults a one time NGN link. If linkCanExpire is true, expiryDate is required.
     *
     * This call fails unless you have whitelisted your server IPs and configured
     * payment preferences on the merchant dashboard.
     *
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input): array
    {
        $body = array_merge(
            ['paymentLinkType' => 'ONE_TIME_PAYMENT_LINK', 'currency' => 'NGN'],
            $input,
        );
        WayaPay::requireFields(
            $body,
            ['paymentLinkType', 'paymentLinkName', 'description', 'payableAmount', 'currency', 'redirectLink'],
            'payment collect',
        );
        if (($body['linkCanExpire'] ?? false) === true) {
            WayaPay::requireFields($body, ['expiryDate'], 'payment collect (expiry)');
        }

        return $this->client->request('POST', '/payment-collect/initiate', $body);
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
