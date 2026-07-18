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
 * The result of looking up the most recent governance run. Internal-use VO —
 * does not carry `toArray()`.
 */
final readonly class GovernanceRunLookup
{
    /**
     * @param RunLookupState $state  State of the lookup
     * @param ?GovernanceRun $run    The run found, if `state` is FOUND
     * @param ?string        $reason Reason, useful when `state` is INVALID
     */
    public function __construct(
        public RunLookupState $state,
        public ?GovernanceRun $run,
        public ?string $reason,
    ) {
    }
}
