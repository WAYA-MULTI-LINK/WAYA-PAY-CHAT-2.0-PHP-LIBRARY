<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Status\CollectionOutcome;
use WayaPay\Status\CollectionStatus;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\WayaPayException;

final class CollectStatusTest extends TestCase
{
    public function testGetStatusRequiresRefNo(): void
    {
        $client = Factory::ok('{}');

        $this->expectException(WayaPayException::class);
        $this->expectExceptionMessageMatches('/refNo/');
        $client->collect->getStatus('   ');
    }

    public function testGetStatusSendsGetToEncodedPath(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"status":"SUCCESSFUL"}'));
        $client = Factory::client($cap);

        $client->collect->getStatus('REF/01');

        $this->assertSame('GET', $cap->last()['method']);
        $this->assertStringEndsWith('/payment-collect/status/REF%2F01', $cap->last()['url']);
    }

    public function testGetStatusDecodesSuccessfulBody(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody(
            '{"refNo":"1779","status":"SUCCESSFUL","amountPaid":"1500.00","currencyCode":"NGN"}'
        ));
        $client = Factory::client($cap);

        $out = $client->collect->getStatus('1779');

        $this->assertSame('1779', $out['refNo']);
        $this->assertSame('SUCCESSFUL', $out['status']);
        $this->assertSame(CollectionStatus::Successful, CollectionStatus::fromApi($out['status']));
        $this->assertSame(CollectionOutcome::Succeeded, CollectionStatus::fromApi($out['status'])->outcome());
        $this->assertTrue(CollectionStatus::fromApi($out['status'])->isTerminal());
    }

    public function testStatusInterpretationMapping(): void
    {
        $pending = CollectionStatus::fromApi('PENDING');
        $this->assertSame(CollectionOutcome::InFlight, $pending->outcome());
        $this->assertFalse($pending->isTerminal());

        $partial = CollectionStatus::fromApi('PARTIAL');
        $this->assertSame(CollectionOutcome::InFlight, $partial->outcome());
        $this->assertFalse($partial->isTerminal());

        $refunded = CollectionStatus::fromApi('REFUNDED');
        $this->assertSame(CollectionOutcome::Refunded, $refunded->outcome());
        $this->assertTrue($refunded->isTerminal());

        $declined = CollectionStatus::fromApi('DECLINED');
        $this->assertSame(CollectionOutcome::NotDebited, $declined->outcome());
        $this->assertTrue($declined->isTerminal());

        $bankError = CollectionStatus::fromApi('BANK_ERROR');
        $this->assertSame(CollectionOutcome::Indeterminate, $bankError->outcome());
        $this->assertTrue($bankError->isTerminal());

        $unknown = CollectionStatus::fromApi('WHATEVER');
        $this->assertSame(CollectionStatus::Unknown, $unknown);
        $this->assertSame(CollectionOutcome::Indeterminate, $unknown->outcome());
        $this->assertFalse($unknown->isTerminal());
    }
}
