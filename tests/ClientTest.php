<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\Tests\Support\SequenceTransport;
use WayaPay\WayaPay;
use WayaPay\WayaPayException;

final class ClientTest extends TestCase
{
    public function testThrowsConfigErrorWhenMerchantIdMissing(): void
    {
        try {
            new WayaPay(['secretKey' => 's']);
            $this->fail('expected WayaPayException');
        } catch (WayaPayException $e) {
            $this->assertSame('config', $e->type);
            $this->assertStringContainsString('merchantId', $e->getMessage());
        }
    }

    public function testThrowsConfigErrorWhenSecretKeyMissing(): void
    {
        $this->expectException(WayaPayException::class);
        new WayaPay(['merchantId' => 'm']);
    }

    public function testWiresUpEveryResource(): void
    {
        $client = Factory::ok('{}');
        $this->assertNotNull($client->banks);
        $this->assertNotNull($client->accounts);
        $this->assertNotNull($client->identity);
        $this->assertNotNull($client->payouts);
        $this->assertNotNull($client->collect);
        $this->assertNotNull($client->transactions);
    }

    public function testEnvironmentSelectsStagingBaseUrl(): void
    {
        $client = new WayaPay(['merchantId' => 'm', 'secretKey' => 's', 'environment' => 'staging']);
        $this->assertStringContainsString('services.staging.wayapay.ng', $client->baseUrl);
    }

    public function testDefaultsToProductionBaseUrl(): void
    {
        $client = new WayaPay(['merchantId' => 'm', 'secretKey' => 's']);
        $this->assertSame('https://services.wayapay.ng/merchant-middleware/api/v2', $client->baseUrl);
    }

    public function testSendsAuthAndMerchantHeaders(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('[]'));
        $client = Factory::client($cap);

        $client->banks->list();

        $headers = $cap->last()['headers'];
        $this->assertContains('Authorization: Bearer WAYASECK_TEST_key', $headers);
        $this->assertContains('X-Merchant-Id: MER_TEST', $headers);
        $this->assertContains('accept: application/json', $headers);
    }

    public function testSetsContentTypeOnlyForWrites(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{}'));
        $client = Factory::client($cap);

        $client->banks->list(); // GET, no body
        $this->assertNotContains('Content-Type: application/json', $cap->last()['headers']);

        $client->identity->verifyBvn('22500809037'); // POST
        $this->assertContains('Content-Type: application/json', $cap->last()['headers']);
    }

    public function testReturnsEnvelopeData(): void
    {
        $client = Factory::client(new CapturingTransport(200, Factory::okBody('{"hello":"world"}')));
        $data = $client->request('GET', '/anything');
        $this->assertSame(['hello' => 'world'], $data);
    }

    public function testThrowsApiErrorWhenSuccessFalse(): void
    {
        $client = Factory::client(new CapturingTransport(400, Factory::errBody('57', 'IP not whitelisted')));

        try {
            $client->banks->list();
            $this->fail('expected WayaPayException');
        } catch (WayaPayException $e) {
            $this->assertSame('api', $e->type);
            $this->assertSame('57', $e->errorCode);
            $this->assertSame(400, $e->status);
            $this->assertStringContainsString('whitelisted', $e->getMessage());
        }
    }

    public function testThrowsApiErrorOnNonJsonBody(): void
    {
        $client = Factory::client(new CapturingTransport(502, '<html>502</html>'));

        try {
            $client->banks->list();
            $this->fail('expected WayaPayException');
        } catch (WayaPayException $e) {
            $this->assertSame('api', $e->type);
            $this->assertStringContainsString('Non JSON', $e->getMessage());
        }
    }

    public function testRetriesGetOnTransientStatus(): void
    {
        $seq = new SequenceTransport([
            [503, Factory::errBody('99', 'down')],
            [200, Factory::okBody('[]')],
        ]);
        $client = Factory::client($seq, ['maxRetries' => 2]);

        $client->banks->list();
        $this->assertSame(2, $seq->calls, 'should have retried once then succeeded');
    }

    public function testDoesNotRetryWrites(): void
    {
        $cap = new CapturingTransport(503, Factory::errBody('99', 'down'));
        $client = Factory::client($cap, ['maxRetries' => 5]);

        $this->expectException(WayaPayException::class);
        try {
            $client->payouts->initiate(Factory::payoutInput());
        } finally {
            $this->assertSame(1, $cap->count(), 'writes must not retry');
        }
    }

    public function testGenerateReference(): void
    {
        $this->assertStringStartsWith('PAYOUT-', WayaPay::generateReference('PAYOUT'));
        $this->assertStringStartsWith('WP-', WayaPay::generateReference());
        $this->assertNotSame(WayaPay::generateReference('X'), WayaPay::generateReference('X'));
    }

    public function testRequireFieldsThrowsValidationListingMissing(): void
    {
        try {
            WayaPay::requireFields(['a' => 1, 'b' => ''], ['a', 'b', 'c'], 'thing');
            $this->fail('expected WayaPayException');
        } catch (WayaPayException $e) {
            $this->assertSame('validation', $e->type);
            $this->assertStringContainsString('b', $e->getMessage());
            $this->assertStringContainsString('c', $e->getMessage());
        }
    }
}
