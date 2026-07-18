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
 * An artifact required by the governance plan.
 *
 * E.g.: specification, decision document, auditable configuration.
 */
final readonly class RequiredArtifact
{
    /**
     * @param string          $id   Identifier of the artifact (e.g.: "spec", "adr-log")
     * @param EnforcementTier $tier Enforcement level of its existence
     */
    public function __construct(
        public string $id,
        public EnforcementTier $tier,
    ) {
    }

    /**
     * Serializes the artifact to an array for storage or transmission.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tier' => $this->tier->value,
        ];
    }
}
