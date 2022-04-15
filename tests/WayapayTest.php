<?php
namespace Pils36\WayapayTests;

use Pils36\Wayapay;
use Pils36\WayapayHelpers\Router;
use Pils36\WayapayTest\Mock\CustomRoute;
use \Pils36\WayapayException\ValidationException;

class WayapayTest extends \PHPUnit_Framework_TestCase
{
    public function testInitializeWithInvalidSecretKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $r = new Wayapay('p');
    }

    public function testVersion()
    {
        $this->assertEquals("2.1.19", Wayapay::VERSION);
    }

    public function testDisableFileGetContentsFallback()
    {
        Wayapay::disableFileGetContentsFallback();
        $this->assertFalse(Wayapay::$fallback_to_file_get_contents);
    }

    public function testEnableFileGetContentsFallback()
    {
        Wayapay::enableFileGetContentsFallback();
        $this->assertTrue(Wayapay::$fallback_to_file_get_contents);
    }


    public function testSetUseGuzzle()
    {
        $r = new Wayapay('WAYAPUBK_');
        $r->useGuzzle();
        $this->assertTrue($r->use_guzzle);
    }

    public function testGetShouldBringRouter()
    {
        $r = new Wayapay('WAYAPUBK_');
        $this->assertInstanceOf(Router::class, $r->customer);
        $this->expectException(ValidationException::class);
        $this->assertNull($r->nonexistent);
    }

    public function testListInvalidResource()
    {
        $r = new Wayapay('WAYAPUBK_');
        $this->expectException(\InvalidArgumentException::class);
        $this->assertNull($r->nonexistents());
    }

    public function testFetchInvalidResource()
    {
        $r = new Wayapay('WAYAPUBK_');
        $this->expectException(ValidationException::class);
        $this->assertNull($r->nonexistent(1));
    }

    public function testFetchWithInvalidParams2()
    {
        $r = new Wayapay('WAYAPUBK_');
        $this->expectException(\InvalidArgumentException::class);
        $this->assertNull($r->customer());
    }

    public function testFetchWithInvalidParams3()
    {
        $r = new Wayapay('WAYAPUBK_');
        $this->expectException(\InvalidArgumentException::class);
        $this->assertNull($r->customers(1));
    }

    public function testUseRoutes()
    {
        $custom_routes = ['custom_route' => CustomRoute::class];

        $r = new Wayapay('WAYAPUBK_');
        $r->useRoutes($custom_routes);
        $this->assertTrue($r->custom_routes == $custom_routes);
    }

    public function testUseRoutesWithInvalidParams1()
    {
        $custom_routes = ['custom_route'];
        $r = new Wayapay('WAYAPUBK_');
        $this->expectException(\InvalidArgumentException::class);
        $r->useRoutes($custom_routes);
        $this->assertNull($r->custom_routes);
    }

    public function testUseRoutesWithInvalidParams2()
    {
        $custom_routes = ['custom_route' => Wayapay::class];
        $r = new Wayapay('WAYAPUBK_');
        $this->expectException(\InvalidArgumentException::class);
        $r->useRoutes($custom_routes);
        $this->assertNull($r->custom_routes);
    }

    public function testUseRoutesWithInvalidParams3()
    {
        $custom_routes = ['balance' => CustomRoute::class];
        $r = new Wayapay('WAYAPUBK_');
        $this->expectException(\InvalidArgumentException::class);
        $r->useRoutes($custom_routes);
        $this->assertNull($r->custom_routes);
    }
}