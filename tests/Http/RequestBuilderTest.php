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
        $p = new Wayapay('WAYASECK_');
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
        $p = new Wayapay('WAYASECK_');
        $rb = new RequestBuilder($p, null, [], ['reference'=>'some']);
        $endpoint = 'verify/{reference}';

        $rb->putArgsIntoEndpoint($endpoint);
        $this->assertEquals('verify/some', $endpoint);
    }

    public function testBuild()
    {
        $p = new Wayapay('WAYASECK_');
        $params = ['amount'=>'120.00', 'description'=>'some description', 'currency'=> 566, 'fee'=>1, 'customer'=>json_encode(['name'=>'Luke Vincent', 'email'=>'wakexow@mailinator.com', 'phoneNumber'=>'+1(194)8667447']), 'merchantId'=>'MER_qZaVZ1645265780823HOaZW', 'wayaPublicKey'=>'WAYAPUBK_TEST_0x3442f06c8fa6454e90c5b1a518758c70'];
        $rb = new RequestBuilder($p, Transaction::initialize(), $params);

        $r = $rb->build();




        $this->assertEquals('https://services.staging.wayapay.ng/payment-gateway/api/v1/request/transaction', $r->endpoint);
        $this->assertEquals('post', $r->method);
        $this->assertEquals(json_encode($params), $r->body);


        $params = ['perPage'=>10];
        $rb = new RequestBuilder($p, Transaction::getList(), $params);

        $r = $rb->build();


        $this->assertEquals('https://services.staging.wayapay.ng/payment-gateway/api/v1/request?perPage=10', $r->endpoint);
        $this->assertEquals('get', $r->method);
        $this->assertEmpty($r->body);

        $args = ['_tranId'=>'12345678'];
        $rb = new RequestBuilder($p, Transaction::verify(), [], $args);

        $r = $rb->build();


        $this->assertEquals('https://services.staging.wayapay.ng/payment-gateway/api/v1/reference/query/12345678', $r->endpoint);
        $this->assertEquals('get', $r->method);
        $this->assertEmpty($r->body);
    }
}