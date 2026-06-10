<?php

declare(strict_types=1);

namespace WayaPay\Status;

/**
 * How a {@see CollectionStatus} should be acted on.
 */
enum CollectionOutcome
{
    /** In flight — keep polling; don't refund or retry. */
    case InFlight;

    /** Funds confirmed — fulfil the order. */
    case Succeeded;

    /** Previously-successful transaction was refunded. */
    case Refunded;

    /** Customer not debited — do not fulfil. */
    case NotDebited;

    /** Outcome unknown — reconcile; don't refund unilaterally. */
    case Indeterminate;
}
