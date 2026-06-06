<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\WayaPayException;

final class PayoutsTest extends TestCase
{
    public function testInitiateValidatesRequiredFields(): void
    {
        $client = Factory::ok('{}');

        $this->expectException(WayaPayException::class);
        $client->payouts->initiate(['amount' => 5000]); // missing accountNumber, bankCode, etc.
    }

    public function testInitiateDefaultsCurrencyAndReference(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"status":"PROCESSING"}'));
        $client = Factory::client($cap);

        $input = Factory::payoutInput();
        unset($input['reference']);
        $client->payouts->initiate($input);

        $body = $cap->lastBody();
        $this->assertSame('NGN', $body['currency']);
        $this->assertStringStartsWith('PAYOUT-', $body['reference']);
    }

    public function testInitiateDecodesProcessingResult(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody(
            '{"payoutReference":"PYT-99","status":"PROCESSING","message":"accepted"}'
        ));
        $client = Factory::client($cap);

        $out = $client->payouts->initiate(Factory::payoutInput());

        $this->assertSame('PYT-99', $out['payoutReference']);
        $this->assertSame('PROCESSING', $out['status']);
        $this->assertSame('POST', $cap->last()['method']);
        $this->assertStringEndsWith('/payment-payout/initiate', $cap->last()['url']);
    }

    public function testInitiateSendsSuppliedFields(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"status":"PROCESSING"}'));
        $client = Factory::client($cap);

        $client->payouts->initiate(Factory::payoutInput());

        $body = $cap->lastBody();
        $this->assertSame(5000, $body['amount']);
        $this->assertSame('REF-001', $body['reference']);
        $this->assertSame('JOHN DOE', $body['accountName']);
    }
}
