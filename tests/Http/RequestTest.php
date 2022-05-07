<?php
namespace Pils36\Wayapay\Tests\Http;

use Pils36\Wayapay\Http\Request;
use Pils36\Wayapay\Http\Response;
use Pils36\Wayapay;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $r = new Request();
        $this->assertNotNull($r);
    }

    public function testAllApiRequestsMustHaveJsonHeader()
    {
        $p = new Wayapay('WAYASECK_');
        $r = new Request($p);
        $this->assertEquals('application/json', $r->headers['Content-Type']);
        $rNonApi = new Request();
        $this->assertFalse(array_key_exists('Content-Type', $rNonApi->headers));
    }

    public function testGetResponse()
    {
        $rq = new Request();
        $rp = $rq->getResponse();
        $this->assertNotNull($rp);
        $this->assertInstanceOf(Response::class, $rp);
    }

    public function testFlattenedHeadersAndThatOnlyContentTypeAddedByDefaultWhenWayapayObjectPresent()
    {
        $p = new Wayapay('WAYASECK_');
        $rq = new Request($p);
        $hs = $rq->flattenedHeaders();
        $this->assertEquals(1, count($hs));
        $this->assertNotNull($hs[0]);
    }
}