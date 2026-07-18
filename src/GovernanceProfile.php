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
 * Governance profile: the static declaration of which decisions and gates govern the system.
 *
 * It is the source of truth that the Governance Plan is built from.
 */
final readonly class GovernanceProfile
{
    /**
     * @param string                  $schemaVersion Schema version of this profile
     * @param string                  $name          Name of the profile (e.g.: "milpa-framework")
     * @param string                  $version       Semantic version of the profile
     * @param array<string>           $decisionIds   List of decision IDs that make up this profile
     * @param array<GovernanceGate>   $gates         List of GovernanceGate that validate conformance
     * @param array<RequiredArtifact> $artifacts     List of RequiredArtifact that must exist
     * @param array<mixed>            $actors        List of authorized actors (free-form)
     */
    public function __construct(
        public string $schemaVersion,
        public string $name,
        public string $version,
        public array $decisionIds,
        public array $gates,
        public array $artifacts,
        public array $actors,
    ) {
    }
}
