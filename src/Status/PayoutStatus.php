<?php

declare(strict_types=1);

namespace WayaPay\Status;

/**
 * Known values of a payout (disbursement) transaction's `status` field.
 *
 * Parse the raw wire string with {@see PayoutStatus::fromApi()}, then interpret with
 * {@see PayoutStatus::outcome()} / {@see PayoutStatus::isTerminal()} to decide whether
 * to keep reconciling, treat as delivered, or treat as failed.
 */
enum PayoutStatus
{
    /** Status string not recognised by this SDK version. Treat as non-terminal and reconcile. */
    case Unknown;

    /** Submitted; terminal outcome not yet recorded (reconciling). Non-terminal. */
    case Pending;

    /** Completed successfully. */
    case Success;

    /** Failed/reversed — the merchant wallet was re-credited. */
    case Reversed;

    /**
     * Parse the raw `status` string. Returns {@see PayoutStatus::Unknown} for unrecognised
     * values. Trims and upper-cases before matching.
     */
    public static function fromApi(?string $status): self
    {
        return match (strtoupper(trim($status ?? ''))) {
            'PENDING' => self::Pending,
            'SUCCESS' => self::Success,
            'REVERSED' => self::Reversed,
            default => self::Unknown,
        };
    }

    /**
     * Map the status to the action a merchant should take.
     * {@see PayoutStatus::Unknown} maps to {@see PayoutOutcome::Reconciling} — reconcile rather than guess.
     */
    public function outcome(): PayoutOutcome
    {
        return match ($this) {
            self::Success => PayoutOutcome::Succeeded,
            self::Reversed => PayoutOutcome::Reversed,
            // Pending / Unknown
            default => PayoutOutcome::Reconciling,
        };
    }

    /** True once the status will no longer change. Non-terminal statuses should be reconciled. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Pending, self::Unknown => false,
            default => true,
        };
    }
}
