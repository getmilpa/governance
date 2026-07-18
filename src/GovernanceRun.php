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
 * A governance run: the real, observable evidence that a set of gates
 * was executed — who invoked it, what produced it, when, and with what verdicts.
 *
 * It is the observed fact that, compared against a GovernancePlan, gives life to the `outcome`
 * of each GovernanceGate (today always null in the compiled plan).
 */
final readonly class GovernanceRun
{
    /**
     * @param string                   $schemaVersion Schema version of the run
     * @param string                   $runId         Unique identifier of the run
     * @param string                   $profileHash   Integrity hash of the profile in effect at the time of the run
     * @param string                   $planHash      Integrity hash of the plan in effect at the time of the run
     * @param InvocationIdentity       $invokedBy     Who invoked the run
     * @param ProducerIdentity         $producer      What produced the evidence
     * @param string                   $source        Origin of the run (e.g.: "local", "ci")
     * @param \DateTimeImmutable       $startedAt     Start time
     * @param \DateTimeImmutable       $finishedAt    Finish time
     * @param array<string, mixed>     $assumptions   Assumptions in effect at the time of the run
     * @param array<GateOutcomeRecord> $outcomes      Verdicts of the evaluated gates
     */
    public function __construct(
        public string $schemaVersion,
        public string $runId,
        public string $profileHash,
        public string $planHash,
        public InvocationIdentity $invokedBy,
        public ProducerIdentity $producer,
        public string $source,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $finishedAt,
        public array $assumptions,
        public array $outcomes,
    ) {
    }

    /**
     * Serializes the run to an array for storage or transmission.
     *
     * Dates are serialized in ISO-8601 (ATOM format); verdicts, via their
     * own `toArray()`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => $this->schemaVersion,
            'runId' => $this->runId,
            'profileHash' => $this->profileHash,
            'planHash' => $this->planHash,
            'invokedBy' => $this->invokedBy->toArray(),
            'producer' => $this->producer->toArray(),
            'source' => $this->source,
            'startedAt' => $this->startedAt->format(\DateTimeInterface::ATOM),
            'finishedAt' => $this->finishedAt->format(\DateTimeInterface::ATOM),
            'assumptions' => $this->assumptions,
            'outcomes' => array_map(static fn (GateOutcomeRecord $r): array => $r->toArray(), $this->outcomes),
        ];
    }
}
