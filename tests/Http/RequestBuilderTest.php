<?php
namespace Pils36\Wayapay\Tests\Http;

use Pils36\Wayapay\Http\RequestBuilder;
use Pils36\Wayapay;
use Pils36\Wayapay\Contracts\RouteInterface;
use Pils36\Wayapay\Routes\Customer;
use Pils36\Wayapay\Routes\Transaction;

class RequestBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testMoveArgsToSentargs()
    {
        $p = new Wayapay('WAYAPUBK_');
        $interface = ['args'=>['id']];
        $payload = ['id'=>1,'reference'=>'something'];
        $sentargs = [];
        $rb = new RequestBuilder($p, $interface, $payload, $sentargs);

        $rb->moveArgsToSentargs();
        $this->assertEquals(1, $rb->sentargs['id']);
        $this->assertEquals(1, count($rb->payload));
    }

    public function testPutArgsIntoEndpoint()
    {
        $p = new Wayapay('WAYAPUBK_');
        $rb = new RequestBuilder($p, null, [], ['reference'=>'some']);
        $endpoint = 'verify/{reference}';

        $rb->putArgsIntoEndpoint($endpoint);
        $this->assertEquals('verify/some', $endpoint);
    }

    public function testBuild()
    {
        $p = new Wayapay('WAYAPUBK_');
        $params = ['email'=>'some@ema.il'];
        $rb = new RequestBuilder($p, Transaction::fetch(), $params);

        $r = $rb->build();
        $this->assertEquals('https://services.staging.wayapay.ng/payment-gateway/api/v1/request/transaction', $r->endpoint);
        $this->assertEquals('WAYAPUBK_', $r->headers['wayaPublicKey']);
        $this->assertEquals('post', $r->method);
        $this->assertEquals(json_encode($params), $r->body);

        $params = ['perPage'=>10];
        $rb = new RequestBuilder($p, Transaction::getList(), $params);

        $r = $rb->build();
        $this->assertEquals('https://services.staging.wayapay.ng/payment-gateway/api/v1/request?perPage=10', $r->endpoint);
        $this->assertEquals('WAYAPUBK_', $r->headers['wayaPublicKey']);
        $this->assertEquals('get', $r->method);
        $this->assertEmpty($r->body);

        $args = ['reference'=>'some-reference'];
        $rb = new RequestBuilder($p, Transaction::verify(), [], $args);

        $r = $rb->build();
        $this->assertEquals('https://api.Wayapay.co/transaction/verify/some-reference', $r->endpoint);
        $this->assertEquals('WAYAPUBK_', $r->headers['wayaPublicKey']);
        $this->assertEquals('get', $r->method);
        $this->assertEmpty($r->body);
    }
}