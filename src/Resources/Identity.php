<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\WayaPay;
use WayaPay\WayaPayException;

final class Identity
{
    public function __construct(private readonly WayaPay $client)
    {
    }

    /**
     * POST /identity-verification/bvn
     * Accepts a string or ['bvn' => '...']. Validated as 11 digits locally.
     *
     * BVN data is sensitive personal information. Store, transmit, and log it
     * only as your data protection obligations allow.
     *
     * @param  string|array{bvn:string} $input
     * @return array<string,mixed>
     */
    public function verifyBvn(string|array $input): array
    {
        $bvn = is_string($input) ? $input : ($input['bvn'] ?? '');
        if (!preg_match('/^\d{11}$/', (string) $bvn)) {
            throw new WayaPayException('bvn must be an 11 digit string', type: 'validation');
        }

        return $this->client->request('POST', '/identity-verification/bvn', ['bvn' => $bvn]);
    }
}
