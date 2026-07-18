<?php

/**
 * This file is part of Milpa Governance — the governance-as-contract system of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/governance
 */

declare(strict_types=1);

namespace Milpa\Governance;

/**
 * Verdict of a governance gate after a real run.
 *
 * Mirrors the shape of `Milpa\Enums\VerificationStatus` (tri-state passed/failed/waived,
 * plus pending for asynchronous verifications not yet resolved) — without importing it: this
 * leaf is php-only and does not depend on `milpa/core`.
 */
enum GateOutcome: string
{
    /** Requested but not yet resolved. */
    case PENDING = 'pending';
    /** Verified successfully. */
    case PASSED = 'passed';
    /** Rejected / does not meet the requirements. */
    case FAILED = 'failed';
    /** Waived with justification — counts as satisfied. */
    case WAIVED = 'waived';

    /**
     * Whether the verdict releases the gate. PASSED and WAIVED satisfy it; FAILED and PENDING do not.
     */
    public function isSatisfied(): bool
    {
        return $this === self::PASSED || $this === self::WAIVED;
    }

    /**
     * Whether the verdict is terminal (anything but PENDING).
     */
    public function isFinal(): bool
    {
        return $this !== self::PENDING;
    }
}
