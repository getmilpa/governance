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
 * Observation state of a governance plan against the available evidence.
 */
enum ObservationState: string
{
    /** No run was ever recorded for this plan. */
    case NEVER_OBSERVED = 'never_observed';
    /** The most recent run corresponds to the current plan. */
    case CURRENT = 'current';
    /** A run exists, but it corresponds to a previous plan/profile. */
    case STALE = 'stale';
    /** The available evidence is invalid or unreadable. */
    case INVALID = 'invalid';
}
