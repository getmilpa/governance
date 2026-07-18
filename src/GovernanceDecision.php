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
 * An immutable architecture decision that governs the system's behavior.
 *
 * Each decision has a unique ID, a lifecycle status, and what it governs.
 */
final readonly class GovernanceDecision
{
    /**
     * @param string         $id          Unique identifier of the decision (e.g.: "ADR-0004")
     * @param string         $title       Descriptive title of the decision
     * @param DecisionStatus $status      Current status in the lifecycle
     * @param ?string        $bornInSlice Development slice where it was born (e.g.: "ADR#14")
     * @param ?string        $supersedes  ID of the decision it replaces, if any
     * @param array<string>  $governs     List of identifiers for what this decision governs
     * @param array<mixed>   $policies    List of derived policies (free-form)
     * @param string         $contentHash Hash of the full content to validate integrity
     */
    public function __construct(
        public string $id,
        public string $title,
        public DecisionStatus $status,
        public ?string $bornInSlice,
        public ?string $supersedes,
        public array $governs,
        public array $policies,
        public string $contentHash,
    ) {
    }
}
