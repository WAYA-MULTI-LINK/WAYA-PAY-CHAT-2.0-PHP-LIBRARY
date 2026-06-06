<?php

declare(strict_types=1);

namespace WayaPay;

/**
 * Single exception type for everything that goes wrong.
 *
 * Branch on $type for the category and $errorCode for the WayaPay envelope code.
 * It is $errorCode rather than $code because PHP's base Exception already owns
 * getCode(), and that one is an int.
 */
final class WayaPayException extends \Exception
{
    /**
     * @param string      $message   Human readable description.
     * @param string|null $errorCode WayaPay code, e.g. "07". Null when not an API error.
     * @param int|null    $status    HTTP status, when known.
     * @param mixed       $raw       Raw decoded body or underlying error, for logging.
     * @param string      $type      'api' | 'validation' | 'network' | 'timeout' | 'config'
     */
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $status = null,
        public readonly mixed $raw = null,
        public readonly string $type = 'api',
    ) {
        parent::__construct($message);
    }
}
