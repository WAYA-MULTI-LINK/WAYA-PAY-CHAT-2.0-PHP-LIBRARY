<?php

declare(strict_types=1);

namespace WayaPay;

/**
 * Single exception type for everything that goes wrong.
 * Branch on $type for category, $errorCode for the WayaPay envelope code.
 */
final class WayaPayException extends \Exception
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null, // WayaPay code, e.g. "07". null when not an API error.
        public readonly ?int $status = null,       // HTTP status, when known.
        public readonly mixed $raw = null,          // Raw decoded body or underlying error, for logging.
        public readonly string $type = 'api',       // 'api' | 'validation' | 'network' | 'timeout' | 'config'
    ) {
        parent::__construct($message);
    }
}
