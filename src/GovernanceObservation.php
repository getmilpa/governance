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
 * The observation resulting from crossing a governance plan against the available run
 * evidence. Internal-use VO — does not carry `toArray()`.
 */
final readonly class GovernanceObservation
{
    /**
     * @param ObservationState $state  State of the observation
     * @param GovernancePlan   $plan   The observed plan
     * @param ?GovernanceRun   $run    The relevant run, if any
     * @param ?string          $reason Reason, useful when `state` is STALE or INVALID
     */
    public function __construct(
        public ObservationState $state,
        public GovernancePlan $plan,
        public ?GovernanceRun $run,
        public ?string $reason,
    ) {
    }
}
