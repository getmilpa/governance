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
 * The plan's health status report (GOV-3): one `GateStatus` per gate, in the plan's
 * contractual order, plus the exact count per condition.
 *
 * The system MEASURES, it does not diagnose: `summary()` counts each gate exactly once and does NOT
 * collapse to a single verdict. "No signal" (null condition) is a separate number, never
 * interpreted as health or as failure (ADR#18).
 */
final readonly class GovernanceStatus
{
    /**
     * @param string           $planHash Hash of the plan this status was derived from
     * @param list<GateStatus> $gates    Status per gate, in the plan's contractual order
     */
    public function __construct(
        public string $planHash,
        public array $gates,
    ) {
    }

    /**
     * Exact count per condition. Each gate falls into exactly one bucket, and
     * `total === count($gates)`.
     *
     * @return array{total: int, passed: int, failed: int, waived: int, pending: int, noSignal: int}
     */
    public function summary(): array
    {
        $passed = $failed = $waived = $pending = $noSignal = 0;
        foreach ($this->gates as $g) {
            match ($g->condition) {
                GateOutcome::PASSED => $passed++,
                GateOutcome::FAILED => $failed++,
                GateOutcome::WAIVED => $waived++,
                GateOutcome::PENDING => $pending++,
                null => $noSignal++,
            };
        }

        return [
            'total' => count($this->gates),
            'passed' => $passed,
            'failed' => $failed,
            'waived' => $waived,
            'pending' => $pending,
            'noSignal' => $noSignal,
        ];
    }

    /**
     * Serializes the full report.
     *
     * @return array{planHash: string, gates: list<array<string,mixed>>, summary: array<string,int>}
     */
    public function toArray(): array
    {
        return [
            'planHash' => $this->planHash,
            'gates' => array_map(static fn (GateStatus $g): array => $g->toArray(), $this->gates),
            'summary' => $this->summary(),
        ];
    }
}
