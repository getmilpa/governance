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
 * A rule derived from a decision that must be upheld in the system.
 *
 * Each rule is traceable to its originating decision.
 */
final readonly class GovernanceRule
{
    /**
     * @param string $id           Unique identifier of the rule
     * @param string $statement    The statement of the rule (what must be upheld)
     * @param string $fromDecision ID of the decision that originates it
     */
    public function __construct(
        public string $id,
        public string $statement,
        public string $fromDecision,
    ) {
    }

    /**
     * Serializes the rule to an array for storage or transmission.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'statement' => $this->statement,
            'fromDecision' => $this->fromDecision,
        ];
    }
}
