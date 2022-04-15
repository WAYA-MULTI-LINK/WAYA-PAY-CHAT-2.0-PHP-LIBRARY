<?php

namespace Pils36\Wayapay\Tests\Exception;

use Pils36\Wayapay\Exception\WayapayException;

class WayapayExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $e = new WayapayException('message');
        $this->assertNotNull($e);
    }
}