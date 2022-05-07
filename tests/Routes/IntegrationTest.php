<?php
namespace Pils36\Wayapay\Tests\Routes;

use Pils36\Wayapay\Contracts\RouteInterface;
use Pils36\Wayapay\Routes\Integration;

class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    public function testRoot()
    {
        $r = new Integration();
        $this->assertEquals('/integration', $r->root());
    }

    public function testEndpoints()
    {
        $r = new Integration();
        $this->assertEquals(
            '/integration/payment_session_timeout',
            $r->paymentSessionTimeout()[RouteInterface::ENDPOINT_KEY]
        );
        $this->assertEquals(
            '/integration/payment_session_timeout',
            $r->updatePaymentSessionTimeout()[RouteInterface::ENDPOINT_KEY]
        );
    }

    public function testMethods()
    {
        $r = new Integration();
        $this->assertEquals(RouteInterface::GET_METHOD, $r->paymentSessionTimeout()[RouteInterface::METHOD_KEY]);
        $this->assertEquals(RouteInterface::PUT_METHOD, $r->updatePaymentSessionTimeout()[RouteInterface::METHOD_KEY]);
    }
}
