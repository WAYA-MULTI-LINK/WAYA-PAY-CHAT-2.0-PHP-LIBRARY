<?php
namespace Pils36\Wayapay\Tests\Helpers;

use Pils36\Wayapay\Helpers\Caller;
use Pils36\Wayapay;
use Pils36\Wayapay\Contracts\RouteInterface;

class CallerTest extends \PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $p = new Wayapay('WAYAPUBK_');
        $c = new Caller($p);
        $this->assertNotNull($c);
    }
}