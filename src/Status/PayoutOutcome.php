<?php

declare(strict_types=1);

namespace WayaPay\Status;

/**
 * How a {@see PayoutStatus} should be acted on.
 */
enum PayoutOutcome
{
    /** Submitted; terminal outcome not yet recorded — keep reconciling. */
    case Reconciling;

    /** Completed successfully — funds delivered. */
    case Succeeded;

    /** Failed/reversed — the merchant wallet was re-credited. */
    case Reversed;
}
