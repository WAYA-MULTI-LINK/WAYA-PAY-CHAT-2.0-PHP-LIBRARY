<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\WayaPayException;

final class CollectTest extends TestCase
{
    public function testCreateValidatesRequiredFields(): void
    {
        $client = Factory::ok('{}');

        $this->expectException(WayaPayException::class);
        $client->collect->create(['amount' => '1500.00']); // missing transactionId, firstName, lastName, email, phone
    }

    public function testCreateDefaultsCurrency(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"checkOutUrl":"https://pay.test/x"}'));
        $client = Factory::client($cap);

        $input = Factory::collectInput();
        unset($input['currency']);
        $client->collect->create($input);

        $body = $cap->lastBody();
        $this->assertSame('NGN', $body['currency']);
        $this->assertSame('TX-123456', $body['transactionId']);
        $this->assertSame('1500.00', $body['amount']);
    }

    public function testCreateDecodesCheckoutAndPostsCorrectly(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody(
            '{"checkOutUrl":"https://pay.test/checkout/123","transactionId":"TX-123","uniqueId":"UNIQ-1","amount":"1500.00","expiresAt":"2026-11-02T12:00:00Z"}'
        ));
        $client = Factory::client($cap);

        $out = $client->collect->create(Factory::collectInput());

        $this->assertSame('https://pay.test/checkout/123', $out['checkOutUrl']);
        $this->assertSame('TX-123', $out['transactionId']);
        $this->assertSame('UNIQ-1', $out['uniqueId']);
        $this->assertSame('2026-11-02T12:00:00Z', $out['expiresAt']);
        $this->assertStringEndsWith('/payment-collect/initiate', $cap->last()['url']);
    }

    public function testInitiateAliasAndHelper(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody(
            '{"checkOutUrl":"https://pay.test/checkout/123"}'
        ));
        $client = Factory::client($cap);

        $res1 = $client->collect->initiate(Factory::collectInput());
        $this->assertSame('https://pay.test/checkout/123', $res1['checkOutUrl']);

        $res2 = $client->initiatePayment(Factory::collectInput());
        $this->assertSame('https://pay.test/checkout/123', $res2['checkOutUrl']);
    }
}
