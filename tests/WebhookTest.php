<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Status\WebhookStatus;
use WayaPay\Tests\Support\Factory;
use WayaPay\WayaPayException;
use WayaPay\WayaPayWebhookException;
use WayaPay\Webhook;

final class WebhookTest extends TestCase
{
    private const SECRET = 'WAYASECK_TEST_webhook_secret';

    private const BODY = '{"OrderId":"1779662251460508970","Amount":1500.00,"Description":"Order #4523",'
        . '"Fee":15.00,"Currency":"NGN","Status":"SUCCESSFUL","TranTime":"2026-06-07T14:30:12",'
        . '"TransactionDate":"2026-06-07 14:30:12","productName":"CARD","businessName":"Your Shop Ltd",'
        . '"customer":{"name":"John Doe","email":"john@example.com","phoneNumber":"08012345678","customerId":"CUS_abc"},'
        . '"merchantId":"MER_xyz","recurrentPayment":false}';

    private static function sign(string $timestamp, string $body, string $secret = self::SECRET): string
    {
        return base64_encode(hash_hmac('sha256', "$timestamp.$body", $secret, true));
    }

    private static function nowMs(): string
    {
        return (string) (int) (microtime(true) * 1000);
    }

    public function testConstructEventParsesMixedCasingOnValidSignature(): void
    {
        $ts = self::nowMs();
        $evt = Webhook::constructEvent(self::BODY, $ts, self::sign($ts, self::BODY), self::SECRET);

        // PascalCase wire fields
        $this->assertSame('1779662251460508970', $evt['orderId']);
        $this->assertSame(1500.00, $evt['amount']);
        $this->assertSame(15.00, $evt['fee']);
        $this->assertSame('SUCCESSFUL', $evt['status']);
        $this->assertSame('Order #4523', $evt['description']);

        // camelCase wire fields
        $this->assertSame('CARD', $evt['productName']);
        $this->assertSame('Your Shop Ltd', $evt['businessName']);
        $this->assertSame('MER_xyz', $evt['merchantId']);
        $this->assertFalse($evt['recurrentPayment']);

        // nested customer
        $this->assertNotNull($evt['customer']);
        $this->assertSame('john@example.com', $evt['customer']['email']);
        $this->assertSame('CUS_abc', $evt['customer']['customerId']);

        // omitted optional field -> null
        $this->assertNull($evt['branchCategory']);

        $this->assertSame(WebhookStatus::Successful, WebhookStatus::fromApi($evt['status']));
        $this->assertTrue(Webhook::shouldFulfil($evt));
    }

    public function testConstructEventThrowsOnWrongSignature(): void
    {
        $ts = self::nowMs();
        $sig = self::sign($ts, self::BODY, 'the-wrong-secret');

        $this->expectException(WayaPayWebhookException::class);
        $this->expectExceptionMessageMatches('/signature/i');
        Webhook::constructEvent(self::BODY, $ts, $sig, self::SECRET);
    }

    public function testConstructEventThrowsWhenBodyTamperedAfterSigning(): void
    {
        $ts = self::nowMs();
        $sig = self::sign($ts, self::BODY);
        $tampered = str_replace('1500.00', '9999.00', self::BODY);

        $this->expectException(WayaPayWebhookException::class);
        Webhook::constructEvent($tampered, $ts, $sig, self::SECRET);
    }

    public function testConstructEventThrowsOnStaleTimestamp(): void
    {
        $staleTs = (string) ((int) (microtime(true) * 1000) - 10 * 60 * 1000);
        $sig = self::sign($staleTs, self::BODY); // correctly signed, but old

        $this->expectException(WayaPayWebhookException::class);
        $this->expectExceptionMessageMatches('/tolerance/i');
        Webhook::constructEvent(self::BODY, $staleTs, $sig, self::SECRET);
    }

    public function testConstructEventAcceptsStaleTimestampWhenReplayDisabled(): void
    {
        $staleTs = (string) ((int) (microtime(true) * 1000) - 10 * 60 * 1000);
        $sig = self::sign($staleTs, self::BODY);

        $evt = Webhook::constructEvent(self::BODY, $staleTs, $sig, self::SECRET, -1);

        $this->assertSame('1779662251460508970', $evt['orderId']);
    }

    /**
     * @return array<string,array{0:?string}>
     */
    public static function malformedSignatureProvider(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'not-base64' => ['not-base64-!!!'],
        ];
    }

    /**
     * @dataProvider malformedSignatureProvider
     */
    public function testVerifySignatureReturnsFalseOnMissingOrMalformed(?string $signature): void
    {
        $this->assertFalse(Webhook::verifySignature(self::BODY, self::nowMs(), $signature, self::SECRET));
    }

    public function testVerifySignatureReturnsTrueOnMatch(): void
    {
        $ts = self::nowMs();
        $this->assertTrue(Webhook::verifySignature(self::BODY, $ts, self::sign($ts, self::BODY), self::SECRET));
    }

    public function testVerifySignatureReturnsFalseOnMissingTimestamp(): void
    {
        $this->assertFalse(Webhook::verifySignature(self::BODY, null, 'sig', self::SECRET));
        $this->assertFalse(Webhook::verifySignature(self::BODY, '', 'sig', self::SECRET));
    }

    /**
     * @return array<string,array{0:string,1:WebhookStatus}>
     */
    public static function statusProvider(): array
    {
        return [
            'successful' => ['SUCCESSFUL', WebhookStatus::Successful],
            'partial' => ['PARTIAL', WebhookStatus::Partial],
            'failed' => ['FAILED', WebhookStatus::Failed],
            'unknown' => ['WHATEVER', WebhookStatus::Unknown],
        ];
    }

    /**
     * @dataProvider statusProvider
     */
    public function testWebhookStatusMapsKnownValues(string $raw, WebhookStatus $expected): void
    {
        $this->assertSame($expected, WebhookStatus::fromApi($raw));
    }

    public function testConstructEventThrowsOnInvalidJsonWithValidSignature(): void
    {
        $notJson = 'this is not json';
        $ts = self::nowMs();
        $sig = self::sign($ts, $notJson);

        $this->expectException(WayaPayWebhookException::class);
        Webhook::constructEvent($notJson, $ts, $sig, self::SECRET);
    }

    // ----- $client->webhooks resource (mirror WebhooksServiceTests.cs) -----

    private const SHORT_BODY = '{"OrderId":"1779662251460508970","Amount":1500.00,"Status":"SUCCESSFUL",'
        . '"productName":"CARD","merchantId":"MER_xyz","recurrentPayment":false}';

    public function testClientWebhooksUsesConfiguredSecret(): void
    {
        $client = Factory::ok('{}', ['webhookSecret' => self::SECRET]);
        $ts = self::nowMs();

        $evt = $client->webhooks->constructEvent(self::SHORT_BODY, $ts, self::sign($ts, self::SHORT_BODY));

        $this->assertSame('1779662251460508970', $evt['orderId']);
        $this->assertTrue(Webhook::shouldFulfil($evt));
    }

    public function testClientWebhooksThrowsOnWrongConfiguredSecret(): void
    {
        $client = Factory::ok('{}', ['webhookSecret' => 'a-different-secret']);
        $ts = self::nowMs();

        $this->expectException(WayaPayWebhookException::class);
        $client->webhooks->constructEvent(self::SHORT_BODY, $ts, self::sign($ts, self::SHORT_BODY));
    }

    public function testClientWebhooksThrowsWhenNoSecretConfigured(): void
    {
        $client = Factory::ok('{}');
        $ts = self::nowMs();

        $this->expectException(WayaPayException::class);
        $this->expectExceptionMessageMatches('/webhookSecret/i');
        $client->webhooks->constructEvent(self::SHORT_BODY, $ts, self::sign($ts, self::SHORT_BODY));
    }

    public function testClientWebhooksExplicitSecretOverridesConfigured(): void
    {
        $client = Factory::ok('{}'); // no configured secret
        $ts = self::nowMs();

        $evt = $client->webhooks->constructEventWith(self::SHORT_BODY, $ts, self::sign($ts, self::SHORT_BODY), self::SECRET);

        $this->assertSame('1779662251460508970', $evt['orderId']);
    }

    public function testClientWebhooksVerifySignatureUsesConfiguredSecret(): void
    {
        $client = Factory::ok('{}', ['webhookSecret' => self::SECRET]);
        $ts = self::nowMs();

        $this->assertTrue($client->webhooks->verifySignature(self::SHORT_BODY, $ts, self::sign($ts, self::SHORT_BODY)));
        $this->assertFalse($client->webhooks->verifySignature(self::SHORT_BODY, $ts, self::sign($ts, self::SHORT_BODY, 'wrong')));
    }
}
