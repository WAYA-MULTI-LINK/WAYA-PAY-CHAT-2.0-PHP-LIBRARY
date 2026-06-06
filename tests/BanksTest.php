<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;

final class BanksTest extends TestCase
{
    public function testListReturnsBanks(): void
    {
        $client = Factory::client(new CapturingTransport(200, Factory::okBody(
            '[{"code":"044","name":"Access Bank","id":"044","status":true},
              {"code":"058","name":"GTBank","id":"058","status":true}]'
        )));

        $banks = $client->banks->list();

        $this->assertCount(2, $banks);
        $this->assertSame('044', $banks[0]['code']);
        $this->assertSame('Access Bank', $banks[0]['name']);
    }

    public function testListHitsCorrectEndpoint(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('[]'));
        $client = Factory::client($cap);

        $client->banks->list();

        $this->assertSame('GET', $cap->last()['method']);
        $this->assertStringEndsWith('/account-enquiry/get-bank-list', $cap->last()['url']);
    }

    public function testListReturnsEmptyArrayWhenDataNull(): void
    {
        $client = Factory::client(new CapturingTransport(200, Factory::okBody('null')));
        $this->assertSame([], $client->banks->list());
    }
}
