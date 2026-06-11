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
     * GET /get-bank-list
     *
     * @return array<int,array<string,mixed>> List of banks with code, name, id, status.
     */
    public function listBanks(): array
    {
        return $this->client->request('GET', '/get-bank-list') ?? [];
    }

    /**
     * POST /verify-account
     * bankCode is required for OTHERS, optional for WAYABANK.
     * Always verify a destination before you pay it.
     *
     * @param  array{accountNumber:string,bankCode?:string,enquiryType?:string} $input
     * @return array<string,mixed>
     */
    public function verifyAccount(array $input): array
    {
        $accountNumber = $input['accountNumber'] ?? null;
        $bankCode = $input['bankCode'] ?? null;
        $enquiryType = $input['enquiryType'] ?? 'OTHERS';

        WayaPay::requireFields(['accountNumber' => $accountNumber], ['accountNumber'], 'account verification');
        if ($enquiryType !== 'WAYABANK') {
            WayaPay::requireFields(['bankCode' => $bankCode], ['bankCode'], 'account verification (external bank)');
        }

        return $this->client->request('POST', '/verify-account', [
            'accountNumber' => $accountNumber,
            'bankCode' => $bankCode,
            'enquiryType' => $enquiryType,
        ]);
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
