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
     * POST /identity-verification/bvn/initiate
     * Step 1 of BVN verification: Submits an 11-digit BVN and dispatches an OTP
     * to the phone number registered against the BVN.
     *
     * Accepts a string or ['bvn' => '...']. Validated as 11 digits locally.
     *
     * @param  string|array{bvn:string} $input
     * @return array{reference:string,phoneNumber:string,expiresAt:string}
     */
    public function initiateBvn(string|array $input): array
    {
        $bvn = is_string($input) ? $input : ($input['bvn'] ?? '');
        if (!preg_match('/^\d{11}$/', (string) $bvn)) {
            throw new WayaPayException('bvn must be an 11 digit string', type: 'validation');
        }

        return $this->client->request('POST', '/identity-verification/bvn/initiate', ['bvn' => $bvn]);
    }

    /**
     * POST /identity-verification/bvn/complete
     * Step 2 of BVN verification: Submits the session reference from initiateBvn
     * and the OTP received by the user to retrieve the KYC record.
     *
     * @param  string|array{reference:string,otp:string} $referenceOrInput
     * @param  string|null $otp
     * @return array<string,mixed>
     */
    public function completeBvn(string|array $referenceOrInput, ?string $otp = null): array
    {
        if (is_array($referenceOrInput)) {
            $reference = (string) ($referenceOrInput['reference'] ?? '');
            $otp = (string) ($referenceOrInput['otp'] ?? '');
        } else {
            $reference = $referenceOrInput;
            $otp = (string) ($otp ?? '');
        }

        WayaPay::requireFields(
            ['reference' => $reference, 'otp' => $otp],
            ['reference', 'otp'],
            'BVN complete verification'
        );

        return $this->client->request('POST', '/identity-verification/bvn/complete', [
            'reference' => $reference,
            'otp'       => $otp,
        ]);
    }

    /**
     * POST /identity-verification/bvn
     * Accepts a string or ['bvn' => '...']. Validated as 11 digits locally.
     *
     * @deprecated WayaQuick BVN verification requires a two-step OTP verification flow.
     * Use initiateBvn() followed by completeBvn() instead.
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
