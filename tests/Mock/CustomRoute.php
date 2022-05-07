<?php
namespace Pils36\Wayapay\Test\Mock;

use Pils36\Wayapay\Contracts\RouteInterface;

class CustomRoute implements RouteInterface
{

    public static function root()
    {
        return '/custom_route';
    }

    public static function test_route()
    {
        return [
            RouteInterface::METHOD_KEY => RouteInterface::GET_METHOD,
            RouteInterface::ENDPOINT_KEY => CustomRoute::root(),
        ];
    }
}
