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
 * Derives the per-gate health status (GOV-3 / ADR#18) ONLY over the `current` evidence of a
 * `RunHistory` — the already-adjudicated output of GOV-2. It re-adjudicates nothing: it reuses
 * `current()` the same way `GovernanceHistoryProjector` reused `observe()`. Each layer adds
 * meaning; none falsifies the previous one.
 *
 * It is pure and total EXCEPT for one guard: if the received history belongs to another plan
 * (a different `planHash`), it throws — a status cannot be derived from the history of another law
 * (amendment 1). Frozen temporal semantics: "most recent" orders by `finishedAt` DESC and
 * then `runId` DESC; a current run that does not observe a gate does not erase the previous
 * observation; `lastPassedAt` considers only PASSED (WAIVED does not count).
 */
final class GovernanceStatusProjector
{
    /**
     * Derives the per-gate `GovernanceStatus` for `$plan`, reading only the `current`
     * evidence of `$history` (GOV-3 / ADR#18) — it re-adjudicates nothing, reusing
     * `RunHistory::current()` the same way `GovernanceHistoryProjector` reuses `observe()`.
     *
     * @throws GovernanceException if `$history->planHash !== $plan->planHash`
     */
    public function project(GovernancePlan $plan, RunHistory $history): GovernanceStatus
    {
        if ($history->planHash !== $plan->planHash) {
            throw GovernanceException::historyPlanMismatch($plan->planHash, $history->planHash);
        }

        $current = $history->current();

        $gates = [];
        foreach ($plan->gates as $gate) {
            $gates[] = $this->deriveGateStatus($gate, $current);
        }

        return new GovernanceStatus($plan->planHash, $gates);
    }

    /**
     * @param list<RunHistoryEntry> $current CURRENT entries (each with `run !== null`)
     */
    private function deriveGateStatus(GovernanceGate $gate, array $current): GateStatus
    {
        // Gathers (run, record) from ALL current runs that observed THIS gate.
        /** @var list<array{run: GovernanceRun, record: GateOutcomeRecord}> $observations */
        $observations = [];
        foreach ($current as $entry) {
            $run = $entry->run;
            if ($run === null) {
                continue; // current() guarantees run !== null; defensive guard for typing
            }
            foreach ($run->outcomes as $record) {
                if ($record->gate === $gate->id) {
                    $observations[] = ['run' => $run, 'record' => $record];
                }
            }
        }

        if ($observations === []) {
            return new GateStatus($gate->id, $gate->tier, null, null, null, null);
        }

        // Deterministic order: finishedAt DESC, then runId DESC.
        usort($observations, static function (array $a, array $b): int {
            $byTime = $b['run']->finishedAt <=> $a['run']->finishedAt;

            return $byTime !== 0 ? $byTime : $b['run']->runId <=> $a['run']->runId;
        });

        $latest = $observations[0];

        // lastPassedAt: the most recent PASSED (the list is already DESC → the first PASSED wins).
        $lastPassedAt = null;
        foreach ($observations as $o) {
            if ($o['record']->outcome === GateOutcome::PASSED) {
                $lastPassedAt = $o['run']->finishedAt;

                break;
            }
        }

        return new GateStatus(
            $gate->id,
            $gate->tier,
            $latest['record']->outcome,
            $latest['run']->finishedAt,
            $latest['run']->runId,
            $lastPassedAt,
        );
    }
}
