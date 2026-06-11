<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\WayaPayException;

final class PayoutsTest extends TestCase
{
    public function testListBanksReturnsBanks(): void
    {
        $client = Factory::client(new CapturingTransport(200, Factory::okBody(
            '[{"code":"044","name":"Access Bank","id":"044","status":true},
              {"code":"058","name":"GTBank","id":"058","status":true}]'
        )));

        $banks = $client->payouts->listBanks();

        $this->assertCount(2, $banks);
        $this->assertSame('044', $banks[0]['code']);
        $this->assertSame('Access Bank', $banks[0]['name']);
    }

    public function testListBanksHitsCorrectEndpoint(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('[]'));
        $client = Factory::client($cap);

        $client->payouts->listBanks();

        $this->assertSame('GET', $cap->last()['method']);
        $this->assertStringEndsWith('/get-bank-list', $cap->last()['url']);
    }

    public function testListBanksReturnsEmptyArrayWhenDataNull(): void
    {
        $client = Factory::client(new CapturingTransport(200, Factory::okBody('null')));
        $this->assertSame([], $client->payouts->listBanks());
    }

    public function testVerifyAccountRequiresBankCodeForOthers(): void
    {
        $client = Factory::ok('{}');

        $this->expectException(WayaPayException::class);
        $this->expectExceptionMessageMatches('/bankCode/');
        $client->payouts->verifyAccount(['accountNumber' => '0123456789', 'enquiryType' => 'OTHERS']);
    }

    public function testVerifyAccountAllowsWayaBankWithoutBankCode(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"accountName":"JOHN DOE"}'));
        $client = Factory::client($cap);

        $out = $client->payouts->verifyAccount(['accountNumber' => '0123456789', 'enquiryType' => 'WAYABANK']);

        $this->assertSame('JOHN DOE', $out['accountName']);
        $this->assertSame(1, $cap->count());
    }

    public function testVerifyAccountDecodesResolvedAccountAndPostsCorrectly(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody(
            '{"successful":true,"accountName":"JOHN DOE","bankName":"Access Bank","bankCode":"044"}'
        ));
        $client = Factory::client($cap);

        $out = $client->payouts->verifyAccount(Factory::verifyInput());

        $this->assertSame('JOHN DOE', $out['accountName']);
        $this->assertSame('POST', $cap->last()['method']);
        $this->assertStringEndsWith('/verify-account', $cap->last()['url']);
        $this->assertSame('0123456789', $cap->lastBody()['accountNumber']);
    }

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
