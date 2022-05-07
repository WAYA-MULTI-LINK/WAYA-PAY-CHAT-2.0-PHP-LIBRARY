<?php

namespace Pils36\Wayapay\Routes;

use Pils36\Wayapay\Contracts\RouteInterface;

class Balance implements RouteInterface
{

    public static function root()
    {
        return '/balance';
    }

    public static function getList()
    {
        return [
            RouteInterface::METHOD_KEY => RouteInterface::GET_METHOD,
            RouteInterface::ENDPOINT_KEY => Balance::root(),
        ];
    }
}
