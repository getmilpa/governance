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
 * Adjudicates a LIST of runs against the current plan, producing the full
 * history (GOV-2 / ADR#16).
 *
 * Reuses the per-run wall from GOV-1 (`GovernanceRunProjector::observe()`) once for
 * each `GovernanceRunLookup` — it does not reimplement any stale/consistency check.
 * "Do not mix stale/invalid" is, literally, reusing the `STALE`/`INVALID` that
 * `observe()` already produces.
 */
final class GovernanceHistoryProjector
{
    public function __construct(private readonly GovernanceRunProjector $runProjector)
    {
    }

    /**
     * Adjudicates each lookup against the plan by reusing `observe()` (the GOV-1 wall).
     * Does not mutate the input plan. Does not throw. The history's `planHash` is that of
     * the observed plan.
     *
     * `observe()` on a FOUND lookup returns current/stale/invalid according to its
     * own checks; on an INVALID lookup (unreadable file) it returns invalid
     * propagating the `reason`, with `run` as null. Never NONE in this list — NONE
     * describes an empty directory, i.e. an empty list of lookups, covered
     * by a `RunHistory` with empty `entries`.
     *
     * @param list<GovernanceRunLookup> $lookups
     */
    public function project(GovernancePlan $plan, array $lookups): RunHistory
    {
        $entries = [];
        foreach ($lookups as $lookup) {
            $observation = $this->runProjector->observe($plan, $lookup);
            $entries[] = new RunHistoryEntry($observation->state, $observation->run, $observation->reason);
        }

        return new RunHistory($plan->planHash, $entries);
    }
}
