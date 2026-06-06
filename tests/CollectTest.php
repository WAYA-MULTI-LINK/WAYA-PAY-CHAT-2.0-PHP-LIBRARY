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
        $client->collect->create(['paymentLinkName' => 'x']); // missing description, payableAmount, redirectLink
    }

    public function testCreateRequiresExpiryDateWhenLinkCanExpire(): void
    {
        $client = Factory::ok('{}');

        $this->expectException(WayaPayException::class);
        $this->expectExceptionMessageMatches('/expiryDate/');
        $client->collect->create(array_merge(Factory::collectInput(), ['linkCanExpire' => true]));
    }

    public function testCreateDefaultsLinkTypeAndCurrency(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"shortUrl":"https://pay.test/x"}'));
        $client = Factory::client($cap);

        $client->collect->create(Factory::collectInput());

        $body = $cap->lastBody();
        $this->assertSame('ONE_TIME_PAYMENT_LINK', $body['paymentLinkType']);
        $this->assertSame('NGN', $body['currency']);
    }

    public function testCreateDecodesLinkAndPostsCorrectly(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody(
            '{"shortUrl":"https://pay.test/abc","paymentLinkReference":"PLR-1"}'
        ));
        $client = Factory::client($cap);

        $out = $client->collect->create(Factory::collectInput());

        $this->assertSame('https://pay.test/abc', $out['shortUrl']);
        $this->assertSame('PLR-1', $out['paymentLinkReference']);
        $this->assertStringEndsWith('/payment-collect/initiate', $cap->last()['url']);
    }
}
