<?php

namespace Pils36\Wayapay\Exception;

class WayapayException extends \Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}