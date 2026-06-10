<?php

declare(strict_types=1);

namespace WayaPay\Status;

/**
 * Known values of a collection (deposit) transaction's `status` field.
 *
 * Parse the raw wire string with {@see CollectionStatus::fromApi()}, then interpret
 * with {@see CollectionStatus::outcome()} / {@see CollectionStatus::isTerminal()} to
 * decide whether to keep polling, fulfil, or reconcile.
 */
enum CollectionStatus
{
    /** Status string not recognised by this SDK version. Treat as non-terminal and reconcile. */
    case Unknown;

    // ----- In flight (non-terminal): keep polling; don't refund or retry -----
    case Initiated;
    case Pending;
    case Processing;
    case Approved;

    /** Customer underpaid into a virtual account. Non-terminal. */
    case Partial;

    // ----- Terminal: funds confirmed -----
    /** Funds confirmed — fulfil. Use refNo for idempotency. */
    case Successful;

    /** Previously-successful transaction refunded. */
    case Refunded;

    // ----- Terminal: customer not debited — no fulfilment -----
    case Failed;
    case Declined;
    case Rejected;
    case Abandoned;
    case Expired;
    case Cancelled;
    case CustomerError;
    case FraudError;

    // ----- Terminal: outcome unknown — reconcile, don't refund unilaterally -----
    case Timeout;
    case Error;
    case SystemError;
    case BankError;

    /**
     * Parse the raw `status` string. Returns {@see CollectionStatus::Unknown} for
     * unrecognised values. Trims and upper-cases before matching.
     */
    public static function fromApi(?string $status): self
    {
        return match (strtoupper(trim($status ?? ''))) {
            'INITIATED' => self::Initiated,
            'PENDING' => self::Pending,
            'PROCESSING' => self::Processing,
            'APPROVED' => self::Approved,
            'PARTIAL' => self::Partial,
            'SUCCESSFUL' => self::Successful,
            'REFUNDED' => self::Refunded,
            'FAILED' => self::Failed,
            'DECLINED' => self::Declined,
            'REJECTED' => self::Rejected,
            'ABANDONED' => self::Abandoned,
            'EXPIRED' => self::Expired,
            'CANCELLED' => self::Cancelled,
            'CUSTOMER_ERROR' => self::CustomerError,
            'FRAUD_ERROR' => self::FraudError,
            'TIMEOUT' => self::Timeout,
            'ERROR' => self::Error,
            'SYSTEM_ERROR' => self::SystemError,
            'BANK_ERROR' => self::BankError,
            default => self::Unknown,
        };
    }

    /**
     * Map the status to the action a merchant should take.
     * {@see CollectionStatus::Unknown} maps to {@see CollectionOutcome::Indeterminate} — reconcile rather than guess.
     */
    public function outcome(): CollectionOutcome
    {
        return match ($this) {
            self::Initiated,
            self::Pending,
            self::Processing,
            self::Approved,
            self::Partial => CollectionOutcome::InFlight,

            self::Successful => CollectionOutcome::Succeeded,
            self::Refunded => CollectionOutcome::Refunded,

            self::Failed,
            self::Declined,
            self::Rejected,
            self::Abandoned,
            self::Expired,
            self::Cancelled,
            self::CustomerError,
            self::FraudError => CollectionOutcome::NotDebited,

            // Timeout / Error / SystemError / BankError / Unknown
            default => CollectionOutcome::Indeterminate,
        };
    }

    /** True once the status will no longer change. Non-terminal statuses should be polled. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Initiated,
            self::Pending,
            self::Processing,
            self::Approved,
            self::Partial,
            self::Unknown => false,
            default => true,
        };
    }
}
