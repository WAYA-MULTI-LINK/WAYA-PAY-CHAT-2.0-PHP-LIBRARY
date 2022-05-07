<?php

namespace Pils36\Wayapay\Routes;

use Pils36\Wayapay\Contracts\RouteInterface;

class Settlement implements RouteInterface
{

    public static function root()
    {
        return '/settlement';
    }

    public static function getList()
    {
        return [
            RouteInterface::METHOD_KEY => RouteInterface::GET_METHOD,
            RouteInterface::ENDPOINT_KEY => Settlement::root(),
        ];
    }
}
