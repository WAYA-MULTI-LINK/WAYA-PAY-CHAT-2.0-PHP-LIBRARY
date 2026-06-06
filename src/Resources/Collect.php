<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\WayaPay;

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
}
