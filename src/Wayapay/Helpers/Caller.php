<?php
namespace Pils36\Wayapay\Helpers;

use \Closure;
use \Pils36\Wayapay\Contracts\RouteInterface;
use \Pils36\Wayapay\Http\RequestBuilder;

class Caller
{
    private $wayapayObj;

    public function __construct($wayapayObj)
    {
        $this->wayapayObj = $wayapayObj;
    }

    public function callEndpoint($interface, $payload = [ ], $sentargs = [ ])
    {
        $builder = new RequestBuilder($this->wayapayObj, $interface, $payload, $sentargs);
        return $builder->build()->send()->wrapUp();
    }
}