<?php
namespace Pils36\Wayapay\Tests\Routes;

use Pils36\Wayapay\Contracts\RouteInterface;
use Pils36\Wayapay\Routes\Balance;

class BalanceTest extends \PHPUnit_Framework_TestCase
{
    public function testRoot()
    {
        $r = new Balance();
        $this->assertEquals('/balance', $r->root());
    }

    public function testEndpoints()
    {
        $r = new Balance();
        $this->assertEquals('/balance', $r->getList()[RouteInterface::ENDPOINT_KEY]);
    }

    public function testMethods()
    {
        $r = new Balance();
        $this->assertEquals(RouteInterface::GET_METHOD, $r->getList()[RouteInterface::METHOD_KEY]);
    }
}
