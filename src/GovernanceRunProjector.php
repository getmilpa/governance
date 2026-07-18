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
 * The GOV-1 wall: overlays the evidence (a `GovernanceRun`) onto the law (a
 * `GovernancePlan`) and decides whether that evidence is honest.
 *
 * "Evidence may repeat the law to show what it observed; it may never redefine it."
 *
 * Pure, no I/O: `observe()` never throws — every failure path is expressed as
 * `ObservationState` + `reason`, so a report (T6) can show stale/invalid in a
 * learnable way without blowing up. Zero mutation of the input `GovernancePlan`: the VOs are
 * readonly, so a stamped `GovernanceGate` is always a NEW instance.
 */
final class GovernanceRunProjector
{
    /**
     * Crosses `$plan` (the law) against `$lookup` (the most recent evidence lookup) and
     * produces the resulting observation.
     *
     * Wall order:
     * 1. `lookup` NONE → NEVER_OBSERVED, the plan comes back with all its gates as null.
     * 2. `lookup` INVALID → INVALID, propagates `lookup->reason` as-is.
     * 3. `lookup` FOUND, but the run observed a different plan (two locks: `profileHash` and
     *    `planHash`) → STALE, gates as null (nothing is projected from a misaligned run).
     * 4. `lookup` FOUND, but the run is internally inconsistent (empty runId, inverted
     *    dates, duplicate/unknown gate, PASSED without producerRef, WAIVED without
     *    justification, boundTo that redefines the law) → INVALID with the reason naming
     *    the offender.
     * 5. Everything else → CURRENT: stamps each outcome onto its gate by id; a plan gate
     *    with no corresponding record stays PENDING — never a fabricated passed.
     */
    public function observe(GovernancePlan $plan, GovernanceRunLookup $lookup): GovernanceObservation
    {
        if ($lookup->state === RunLookupState::NONE) {
            return new GovernanceObservation(ObservationState::NEVER_OBSERVED, $plan, null, null);
        }

        if ($lookup->state === RunLookupState::INVALID) {
            return new GovernanceObservation(ObservationState::INVALID, $plan, null, $lookup->reason);
        }

        /** @var GovernanceRun $run FOUND guarantees run != null */
        $run = $lookup->run;

        if ($run->profileHash !== $plan->profileHash || $run->planHash !== $plan->planHash) {
            return new GovernanceObservation(
                ObservationState::STALE,
                $plan,
                $run,
                'the run observed a different plan (profileHash/planHash do not match) — rerun against the current plan',
            );
        }

        if ($run->runId === '') {
            return $this->invalid($plan, $run, 'empty runId');
        }

        if ($run->finishedAt < $run->startedAt) {
            return $this->invalid($plan, $run, 'finishedAt before startedAt');
        }

        $gateIndex = [];
        foreach ($plan->gates as $gate) {
            $gateIndex[$gate->id] = $gate;
        }

        $byGate = [];
        foreach ($run->outcomes as $record) {
            if (isset($byGate[$record->gate])) {
                return $this->invalid($plan, $run, "duplicate gate in the run: '{$record->gate}'");
            }

            if (!isset($gateIndex[$record->gate])) {
                return $this->invalid($plan, $run, "unknown gate (not in the plan): '{$record->gate}'");
            }

            if ($record->outcome === GateOutcome::PASSED && ($record->producerRef === null || $record->producerRef === '')) {
                return $this->invalid($plan, $run, "a PASSED outcome without producerRef is not demonstrable: '{$record->gate}'");
            }

            if ($record->outcome === GateOutcome::WAIVED && ($record->justification === null || $record->justification === '')) {
                return $this->invalid($plan, $run, "a WAIVED without justification does not explain who or why: '{$record->gate}'");
            }

            if ($record->boundTo !== null && $record->boundTo !== $gateIndex[$record->gate]->boundTo) {
                return $this->invalid($plan, $run, "the run's boundTo ('{$record->boundTo}') redefines the law of gate '{$record->gate}'");
            }

            $byGate[$record->gate] = $record->outcome;
        }

        $stampedGates = array_map(
            static function (GovernanceGate $gate) use ($byGate): GovernanceGate {
                $outcome = $byGate[$gate->id] ?? GateOutcome::PENDING;

                return new GovernanceGate($gate->id, $gate->description, $gate->tier, $gate->boundTo, $outcome);
            },
            $plan->gates,
        );

        $projected = new GovernancePlan(
            $plan->schemaVersion,
            $plan->profileName,
            $plan->profileVersion,
            $plan->profileHash,
            $plan->decisions,
            $plan->rules,
            $stampedGates,
            $plan->artifacts,
            $plan->actors,
            $plan->assumptions,
            $plan->planHash,
        );

        return new GovernanceObservation(ObservationState::CURRENT, $projected, $run, null);
    }

    private function invalid(GovernancePlan $plan, ?GovernanceRun $run, string $reason): GovernanceObservation
    {
        return new GovernanceObservation(ObservationState::INVALID, $plan, $run, $reason);
    }
}
