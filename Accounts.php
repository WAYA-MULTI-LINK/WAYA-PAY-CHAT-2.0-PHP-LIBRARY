<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\WayaPay;

final class Accounts
{
    public function __construct(private readonly WayaPay $client)
    {
    }

    /**
     * POST /account-enquiry/verify-account
     * bankCode is required for OTHERS, optional for WAYABANK.
     *
     * @param  array{accountNumber:string,bankCode?:string,enquiryType?:string} $input
     * @return array<string,mixed>
     */
    public function verify(array $input): array
    {
        $accountNumber = $input['accountNumber'] ?? null;
        $bankCode = $input['bankCode'] ?? null;
        $enquiryType = $input['enquiryType'] ?? 'OTHERS';

        WayaPay::requireFields(['accountNumber' => $accountNumber], ['accountNumber'], 'account verification');
        if ($enquiryType !== 'WAYABANK') {
            WayaPay::requireFields(['bankCode' => $bankCode], ['bankCode'], 'account verification (external bank)');
        }

        return $this->client->request('POST', '/account-enquiry/verify-account', [
            'accountNumber' => $accountNumber,
            'bankCode' => $bankCode,
            'enquiryType' => $enquiryType,
        ]);
    }

    /**
     * POST /account-enquiry/create-dynamic-account
     * Auto fills mode (ONE_TIME) and referenceId when omitted.
     *
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createDynamic(array $input): array
    {
        $body = array_merge(['mode' => 'ONE_TIME'], $input);
        if (empty($body['referenceId'])) {
            $body['referenceId'] = WayaPay::generateReference('DYN');
        }
        WayaPay::requireFields(
            $body,
            ['accountName', 'customerId', 'referenceId', 'purpose', 'mode'],
            'dynamic account',
        );

        return $this->client->request('POST', '/account-enquiry/create-dynamic-account', $body);
    }
}
