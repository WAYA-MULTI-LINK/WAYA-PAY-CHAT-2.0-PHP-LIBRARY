<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\Tests\Support\SequenceTransport;
use WayaPay\WayaPayException;

final class TransactionsTest extends TestCase
{
    public function testVerifyRequiresReference(): void
    {
        $client = Factory::ok('{}');
        $this->expectException(WayaPayException::class);
        $client->transactions->verify('');
    }

    public function testVerifySendsQueryAndDecodes(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"status":"SUCCESS","amount":5000}'));
        $client = Factory::client($cap);

        $out = $client->transactions->verify('PYT-99');

        $this->assertSame('SUCCESS', $out['status']);
        $this->assertSame('GET', $cap->last()['method']);
        $this->assertStringContainsString('/transaction/verify?', $cap->last()['url']);
        $this->assertStringContainsString('reference=PYT-99', $cap->last()['url']);
    }

    public function testVerifyAcceptsArrayInput(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"status":"SUCCESS"}'));
        $client = Factory::client($cap);

        $out = $client->transactions->verify(['reference' => 'PYT-1']);
        $this->assertSame('SUCCESS', $out['status']);
    }

    public function testHistoryBuildsQueryWithDefaults(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"items":[],"totalPages":0}'));
        $client = Factory::client($cap);

        $client->transactions->history(['status' => 'SUCCESS', 'size' => 50]);

        $url = $cap->last()['url'];
        $this->assertStringContainsString('size=50', $url);
        $this->assertStringContainsString('status=SUCCESS', $url);
    }

    public function testHistoryAllStreamsAcrossPages(): void
    {
        // Page 0 and page 1 each carry one item; totalPages = 2 stops the walk.
        $seq = new SequenceTransport([
            [200, Factory::okBody('{"items":[{"id":1}],"totalPages":2}')],
            [200, Factory::okBody('{"items":[{"id":2}],"totalPages":2}')],
        ]);
        $client = Factory::client($seq);

        $ids = [];
        foreach ($client->transactions->historyAll(['status' => 'SUCCESS']) as $txn) {
            $ids[] = $txn['id'];
        }

        $this->assertSame([1, 2], $ids);
        $this->assertSame(2, $seq->calls);
    }
}
