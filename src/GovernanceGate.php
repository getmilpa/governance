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
 * A governance gate: a checkpoint that validates conformance with a rule.
 *
 * It can be bound to a specific point in the pipeline (CI/CD, check-in, review).
 */
final readonly class GovernanceGate
{
    /**
     * @param string          $id          Unique identifier of the gate
     * @param string          $description Description of what this gate validates
     * @param EnforcementTier $tier        Enforcement level
     * @param ?string         $boundTo     Point in the pipeline where it runs (e.g.: "ci:quality:phpstan")
     * @param ?GateOutcome    $outcome     Result of the last run (placeholder for forward-compat)
     */
    public function __construct(
        public string $id,
        public string $description,
        public EnforcementTier $tier,
        public ?string $boundTo = null,
        public ?GateOutcome $outcome = null,
    ) {
    }

    /**
     * Serializes the gate to an array for storage or transmission.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'tier' => $this->tier->value,
            'boundTo' => $this->boundTo,
            'outcome' => $this->outcome?->value,
        ];
    }
}
