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
            $client->identity->initiateBvn($bvn);
            $this->fail('expected WayaPayException');
        } catch (WayaPayException $e) {
            $this->assertSame('validation', $e->type);
        }
        $this->assertSame(0, $cap->count(), 'invalid BVN must not hit the network');
    }

    public function testInitiateBvnSuccess(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"reference":"ref-123","phoneNumber":"234****0000","expiresAt":"2026-10-13T14:32:10.482"}'));
        $client = Factory::client($cap);

        $a = $client->identity->initiateBvn('22222222222');
        $this->assertSame('ref-123', $a['reference']);
        $this->assertSame('234****0000', $a['phoneNumber']);
        $this->assertStringEndsWith('/identity-verification/bvn/initiate', $cap->last()['url']);
        $this->assertSame('22222222222', $cap->lastBody()['bvn']);

        $b = $client->initiateBvn(['bvn' => '22222222222']);
        $this->assertSame('ref-123', $b['reference']);
    }

    public function testCompleteBvnValidation(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{}'));
        $client = Factory::client($cap);

        try {
            $client->identity->completeBvn('', '123456');
            $this->fail('expected exception for empty reference');
        } catch (WayaPayException $e) {
            $this->assertSame('validation', $e->type);
        }

        try {
            $client->identity->completeBvn('ref-123', '');
            $this->fail('expected exception for empty otp');
        } catch (WayaPayException $e) {
            $this->assertSame('validation', $e->type);
        }
        $this->assertSame(0, $cap->count());
    }

    public function testCompleteBvnSuccess(): void
    {
        $cap = new CapturingTransport(200, Factory::okBody('{"bvn":"22222222222","firstName":"TEST","lastName":"USER","watchListed":"SANDBOX"}'));
        $client = Factory::client($cap);

        $res = $client->identity->completeBvn('ref-123', '123456');
        $this->assertSame('TEST', $res['firstName']);
        $this->assertSame('SANDBOX', $res['watchListed']);
        $this->assertStringEndsWith('/identity-verification/bvn/complete', $cap->last()['url']);
        $this->assertSame('ref-123', $cap->lastBody()['reference']);
        $this->assertSame('123456', $cap->lastBody()['otp']);

        $res2 = $client->completeBvn(['reference' => 'ref-123', 'otp' => '123456']);
        $this->assertSame('USER', $res2['lastName']);
    }

    public function testAcceptsStringAndArrayInputLegacy(): void
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
