<?php

namespace Pils36\Wayapay\Routes;

use Pils36\Wayapay\Contracts\RouteInterface;

class Decision implements RouteInterface
{

    public static function root()
    {
        return '/decision';
    }

    public static function bin()
    {
        return [
            RouteInterface::METHOD_KEY => RouteInterface::GET_METHOD,
            RouteInterface::ENDPOINT_KEY => Decision::root() . '/bin/{bin}',
            RouteInterface::ARGS_KEY => ['bin'],
        ];
    }
}
