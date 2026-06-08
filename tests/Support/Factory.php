<?php

declare(strict_types=1);

namespace WayaPay\Tests\Support;

use WayaPay\WayaPay;

final class Factory
{
    /**
     * Build a client backed by the given transport. Retries are off by default
     * so tests are deterministic; opt back in with ['maxRetries' => n].
     *
     * @param array<string,mixed> $extra
     */
    public static function client(callable $transport, array $extra = []): WayaPay
    {
        return new WayaPay(array_merge([
            'merchantId' => 'MER_TEST',
            'secretKey' => 'WAYASECK_TEST_key',
            'maxRetries' => 0,
            'transport' => $transport,
        ], $extra));
    }

    /** A client whose every request resolves to a success envelope wrapping $dataJson. */
    public static function ok(string $dataJson, array $extra = []): WayaPay
    {
        return self::client(
            new CapturingTransport(200, '{"success":true,"code":"00","data":' . $dataJson . '}'),
            $extra,
        );
    }

    public static function okBody(string $dataJson): string
    {
        return '{"success":true,"code":"00","data":' . $dataJson . '}';
    }

    public static function errBody(string $code, string $message): string
    {
        return '{"success":false,"code":"' . $code . '","message":"' . $message . '"}';
    }

    /** @return array{accountNumber:string,bankCode:string,enquiryType:string} */
    public static function verifyInput(): array
    {
        return ['accountNumber' => '0123456789', 'bankCode' => '044', 'enquiryType' => 'OTHERS'];
    }

    /** @return array<string,mixed> */
    public static function payoutInput(): array
    {
        return [
            'amount' => 5000,
            'accountNumber' => '0123456789',
            'bankCode' => '044',
            'accountName' => 'JOHN DOE',
            'reference' => 'REF-001',
            'narration' => 'Test payout',
        ];
    }

    /** @return array<string,mixed> */
    public static function collectInput(): array
    {
        return [
            'paymentLinkName' => 'Order #1234',
            'description' => 'Order #1234 - 2 items',
            'payableAmount' => 1500,
            'redirectLink' => 'https://merchant.example.com/callback',
        ];
    }
}
