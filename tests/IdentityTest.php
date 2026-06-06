<?php

declare(strict_types=1);

namespace WayaPay\Tests;

use PHPUnit\Framework\TestCase;
use WayaPay\Tests\Support\CapturingTransport;
use WayaPay\Tests\Support\Factory;
use WayaPay\WayaPayException;

final class IdentityTest extends TestCase
{
    /**
     * @return array<string,array{0:mixed}>
     */
    public static function invalidBvns(): array
    {
        return [
            'empty' => [''],
            'too short' => ['123'],
            'non digit' => ['2250080903X'],
            'too long' => ['225008090377'],
        ];
    }

    /**
     * @dataProvider invalidBvns
     */
    public function testRejectsInvalidBvnBeforeNetwork(mixed $bvn): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{}'));
        $client = Factory::client($cap);

        try {
            $client->identity->verifyBvn($bvn);
            $this->fail('expected WayaPayException');
        } catch (WayaPayException $e) {
            $this->assertSame('validation', $e->type);
        }
        $this->assertSame(0, $cap->count(), 'invalid BVN must not hit the network');
    }

    public function testAcceptsStringAndArrayInput(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"bvn":"22500809037","firstName":"JOHN"}'));
        $client = Factory::client($cap);

        $a = $client->identity->verifyBvn('22500809037');
        $b = $client->identity->verifyBvn(['bvn' => '22500809037']);

        $this->assertSame('JOHN', $a['firstName']);
        $this->assertSame('JOHN', $b['firstName']);
    }

    public function testPostsToCorrectPathWithBvnBody(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"bvn":"22500809037"}'));
        $client = Factory::client($cap);

        $client->identity->verifyBvn('22500809037');

        $this->assertStringEndsWith('/identity-verification/bvn', $cap->last()['url']);
        $this->assertSame('22500809037', $cap->lastBody()['bvn']);
    }
}
