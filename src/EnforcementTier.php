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
 * The enforcement level of a governance rule in the plan.
 *
 * Defines how strictly a gate, rule, or required artifact is enforced.
 */
enum EnforcementTier: string
{
    case Enforced = 'enforced';
    case Advisory = 'advisory';
    case Convention = 'convention';
    case Deferred = 'deferred';
}
