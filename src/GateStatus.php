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
 * The health status of a gate (GOV-3): the honest join between the law (id, tier) and
 * evidence derived only from `current` runs.
 *
 * `$condition` is `?GateOutcome`: `null` means "no signal" (no current run
 * observed this gate) and it is NEVER collapsed with `pending`. When `$condition === null`,
 * `$observedAt` and `$lastRunId` are also null. `$lastPassedAt` is independent of
 * `$condition`: a gate can be `failed` now and have passed several runs ago.
 */
final readonly class GateStatus
{
    /**
     * @param string              $gateId       Identifier of the gate in the plan
     * @param EnforcementTier     $tier         Enforcement level of the gate in the plan
     * @param ?GateOutcome        $condition    Last outcome observed in current evidence; null = no signal
     * @param ?\DateTimeImmutable $observedAt   `finishedAt` of the run that produced `$condition`
     * @param ?string             $lastRunId    `runId` of that same run
     * @param ?\DateTimeImmutable $lastPassedAt `finishedAt` of the last current PASSED (WAIVED does not count)
     */
    public function __construct(
        public string $gateId,
        public EnforcementTier $tier,
        public ?GateOutcome $condition,
        public ?\DateTimeImmutable $observedAt,
        public ?string $lastRunId,
        public ?\DateTimeImmutable $lastPassedAt,
    ) {
    }

    /**
     * Serializes the gate status. Dates are in ISO-8601/ATOM; `condition` as null is
     * preserved as null (distinct from `"pending"`).
     *
     * @return array{gate: string, tier: string, condition: ?string, observedAt: ?string, lastRunId: ?string, lastPassedAt: ?string}
     */
    public function toArray(): array
    {
        return [
            'gate' => $this->gateId,
            'tier' => $this->tier->value,
            'condition' => $this->condition?->value,
            'observedAt' => $this->observedAt?->format(\DateTimeInterface::ATOM),
            'lastRunId' => $this->lastRunId,
            'lastPassedAt' => $this->lastPassedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
