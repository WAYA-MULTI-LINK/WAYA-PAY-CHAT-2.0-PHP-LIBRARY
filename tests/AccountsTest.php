<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\WayaPayException;

final class AccountsTest extends TestCase
{
    public function testVerifyRequiresBankCodeForOthers(): void
    {
        $client = Factory::ok('{}');

        $this->expectException(WayaPayException::class);
        $this->expectExceptionMessageMatches('/bankCode/');
        $client->accounts->verify(['accountNumber' => '0123456789', 'enquiryType' => 'OTHERS']);
    }

    public function testVerifyAllowsWayaBankWithoutBankCode(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"accountName":"JOHN DOE"}'));
        $client = Factory::client($cap);

        $out = $client->accounts->verify(['accountNumber' => '0123456789', 'enquiryType' => 'WAYABANK']);

        $this->assertSame('JOHN DOE', $out['accountName']);
        $this->assertSame(1, $cap->count());
    }

    public function testVerifyDecodesResolvedAccountAndPostsCorrectly(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody(
            '{"successful":true,"accountName":"JOHN DOE","bankName":"Access Bank","bankCode":"044"}'
        ));
        $client = Factory::client($cap);

        $out = $client->accounts->verify(Factory::verifyInput());

        $this->assertSame('JOHN DOE', $out['accountName']);
        $this->assertSame('POST', $cap->last()['method']);
        $this->assertStringEndsWith('/account-enquiry/verify-account', $cap->last()['url']);
        $this->assertSame('0123456789', $cap->lastBody()['accountNumber']);
    }

    public function testCreateDynamicValidatesRequiredFields(): void
    {
        $client = Factory::ok('{}');

        $this->expectException(WayaPayException::class);
        $client->accounts->createDynamic(['customerId' => 'C1']); // missing accountName, purpose
    }

    public function testCreateDynamicDefaultsModeAndReference(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"virtualAccountNumber":"9900112233"}'));
        $client = Factory::client($cap);

        $out = $client->accounts->createDynamic([
            'accountName' => 'ACME LTD',
            'customerId' => 'CUST-1',
            'purpose' => 'order',
        ]);

        $this->assertSame('9900112233', $out['virtualAccountNumber']);
        $body = $cap->lastBody();
        $this->assertSame('ONE_TIME', $body['mode']);
        $this->assertStringStartsWith('DYN-', $body['referenceId']);
    }
}
