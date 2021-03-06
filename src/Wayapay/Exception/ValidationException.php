<?php

namespace Pils36\Wayapay\Exception;

class ValidationException extends WayapayException
{
    public $errors;
    public function __construct($message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
}
