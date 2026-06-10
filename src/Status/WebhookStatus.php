<?php

declare(strict_types=1);

namespace WayaPay\Status;

/**
 * Known values of a webhook event's `status` field.
 *
 * Parse the raw wire string with {@see WebhookStatus::fromApi()}. Only {@see WebhookStatus::Successful}
 * is safe to fulfil on — see {@see WebhookStatus::shouldFulfil()}.
 */
enum WebhookStatus
{
    /** Status string not recognised by this SDK version. Don't fulfil; reconcile. */
    case Unknown;

    /** Customer paid the full amount (or more). Funds queued for settlement — fulfil the order. */
    case Successful;

    /** Paid into a virtual account but less than expected. Hold fulfilment; a top-up sends a later SUCCESSFUL. */
    case Partial;

    /** Declined, abandoned, or upstream-rejected. Funds never moved — no fulfilment. */
    case Failed;

    /**
     * Parse the raw `status` string. Returns {@see WebhookStatus::Unknown} for unrecognised
     * values. Trims and upper-cases before matching.
     */
    public static function fromApi(?string $status): self
    {
        return match (strtoupper(trim($status ?? ''))) {
            'SUCCESSFUL' => self::Successful,
            'PARTIAL' => self::Partial,
            'FAILED' => self::Failed,
            default => self::Unknown,
        };
    }

    /** True only when the customer paid in full — safe to fulfil (after an idempotency check on orderId). */
    public function shouldFulfil(): bool
    {
        return $this === self::Successful;
    }
}
