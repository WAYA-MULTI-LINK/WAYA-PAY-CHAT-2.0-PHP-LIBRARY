<?php

declare(strict_types=1);

namespace WayaPay\Tests\Live;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WayaPay\WayaPay;

/**
 * Live integration tests. These hit the real WayaPay API and are excluded from
 * the default run (the "live" group is excluded in phpunit.xml.dist).
 *
 * Run them with real credentials:
 *
 *   WAYA_MERCHANT_ID=MER_... WAYA_SECRET_KEY=WAYASECK_TEST_... \
 *     vendor/bin/phpunit --group live
 *
 * They run against the production API; use test credentials.
 */
#[Group('live')]
final class LiveTest extends TestCase
{
    private function client(): WayaPay
    {
        $merchant = getenv('WAYA_MERCHANT_ID') ?: '';
        $secret = getenv('WAYA_SECRET_KEY') ?: '';
        if ($merchant === '' || $secret === '') {
            $this->markTestSkipped('set WAYA_MERCHANT_ID and WAYA_SECRET_KEY to run live tests');
        }

        return new WayaPay([
            'merchantId' => $merchant,
            'secretKey' => $secret,
        ]);
    }

    public function testBanksList(): void
    {
        $banks = $this->client()->banks->list();
        $this->assertNotEmpty($banks);
        $this->assertArrayHasKey('code', $banks[0]);
    }

    public function testVerifyAccount(): void
    {
        $out = $this->client()->accounts->verify([
            'accountNumber' => getenv('WAYA_TEST_ACCOUNT') ?: '0123456789',
            'bankCode' => getenv('WAYA_TEST_BANK_CODE') ?: '044',
        ]);
        $this->assertArrayHasKey('accountName', $out);
    }
}
