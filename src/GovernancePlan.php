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
 * Governance plan: the honest, inspectable arbiter that validates the system's conformance.
 *
 * Compiles decisions, rules, gates, and artifacts into a verifiable plan.
 */
final readonly class GovernancePlan
{
    /**
     * @param string                  $schemaVersion  Schema version of the plan
     * @param string                  $profileName    Name of the profile this plan originates from
     * @param string                  $profileVersion Version of the profile
     * @param string                  $profileHash    Integrity hash of the profile
     * @param array<mixed>            $decisions      List of compiled decisions (free-form)
     * @param array<GovernanceRule>   $rules          List of derived GovernanceRule
     * @param array<GovernanceGate>   $gates          List of validating GovernanceGate
     * @param array<RequiredArtifact> $artifacts      List of required RequiredArtifact
     * @param array<mixed>            $actors         List of authorized actors
     * @param array<string>           $assumptions    List of assumptions that validate the plan
     * @param string                  $planHash       Deterministic integrity hash of the complete plan-as-law — binds a
     *                                                `GovernanceRun` (evidence) to this EXACT `GovernancePlan`
     */
    public function __construct(
        public string $schemaVersion,
        public string $profileName,
        public string $profileVersion,
        public string $profileHash,
        public array $decisions,
        public array $rules,
        public array $gates,
        public array $artifacts,
        public array $actors,
        public array $assumptions,
        public string $planHash,
    ) {
    }

    /**
     * Serializes the full plan to an array for storage or transmission.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => $this->schemaVersion,
            'profileName' => $this->profileName,
            'profileVersion' => $this->profileVersion,
            'profileHash' => $this->profileHash,
            'planHash' => $this->planHash,
            'decisions' => $this->decisions,
            'rules' => array_map(fn (GovernanceRule $r) => $r->toArray(), $this->rules),
            'gates' => array_map(fn (GovernanceGate $g) => $g->toArray(), $this->gates),
            'artifacts' => array_map(fn (RequiredArtifact $a) => $a->toArray(), $this->artifacts),
            'actors' => $this->actors,
            'assumptions' => $this->assumptions,
        ];
    }
}
