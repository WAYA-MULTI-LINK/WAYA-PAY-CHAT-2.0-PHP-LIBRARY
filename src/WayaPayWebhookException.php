<?php

declare(strict_types=1);

namespace WayaPay;

/**
 * Thrown when a webhook fails signature verification, replay checks, or cannot be parsed.
 *
 * Distinct from {@see WayaPayException} (the API/transport error type) because webhook
 * verification is a standalone, offline concern with no HTTP envelope behind it.
 */
final class WayaPayWebhookException extends \Exception
{
}
