<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Status\PayoutOutcome;
use WayaPay\Status\PayoutStatus;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\WayaPayException;

final class PayoutStatusTest extends TestCase
{
    public function testGetStatusRequiresReference(): void
    {
        $client = Factory::ok('{}');

        $this->expectException(WayaPayException::class);
        $this->expectExceptionMessageMatches('/reference/');
        $client->payouts->getStatus('   ');
    }

    public function testGetStatusSendsGetToEncodedPath(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"status":"SUCCESS"}'));
        $client = Factory::client($cap);

        $client->payouts->getStatus('REF/01');

        $this->assertSame('GET', $cap->last()['method']);
        $this->assertStringEndsWith('/payment-payout/status/REF%2F01', $cap->last()['url']);
    }

    public function testGetStatusDecodesBody(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody(
            '{"transactionReference":"PAYOUT-1","status":"SUCCESS","amount":"500.00","destinationAccountName":"JOHN DOE"}'
        ));
        $client = Factory::client($cap);

        $out = $client->payouts->getStatus('PAYOUT-1');

        $this->assertSame('PAYOUT-1', $out['transactionReference']);
        $this->assertSame('SUCCESS', $out['status']);
        $this->assertSame(PayoutStatus::Success, PayoutStatus::fromApi($out['status']));
        $this->assertSame(PayoutOutcome::Succeeded, PayoutStatus::fromApi($out['status'])->outcome());
        $this->assertTrue(PayoutStatus::fromApi($out['status'])->isTerminal());
    }

    public function testStatusInterpretationMapping(): void
    {
        $pending = PayoutStatus::fromApi('PENDING');
        $this->assertSame(PayoutOutcome::Reconciling, $pending->outcome());
        $this->assertFalse($pending->isTerminal());

        $success = PayoutStatus::fromApi('SUCCESS');
        $this->assertSame(PayoutOutcome::Succeeded, $success->outcome());
        $this->assertTrue($success->isTerminal());

        $reversed = PayoutStatus::fromApi('REVERSED');
        $this->assertSame(PayoutOutcome::Reversed, $reversed->outcome());
        $this->assertTrue($reversed->isTerminal());

        $unknown = PayoutStatus::fromApi('WHATEVER');
        $this->assertSame(PayoutStatus::Unknown, $unknown);
        $this->assertSame(PayoutOutcome::Reconciling, $unknown->outcome());
        $this->assertFalse($unknown->isTerminal());
    }
}
