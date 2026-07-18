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
 * Result of looking up the most recent governance run for a plan.
 */
enum RunLookupState: string
{
    /** No run is registered. */
    case NONE = 'none';
    /** A valid run was found. */
    case FOUND = 'found';
    /** Evidence exists, but it is invalid or unreadable. */
    case INVALID = 'invalid';
}
