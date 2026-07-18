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
 * Read contract for the governance repository.
 *
 * Provides read-only access to the compiled decisions and profile.
 */
interface GovernanceRepositoryInterface
{
    /**
     * Gets all compiled decisions.
     *
     * @return array<GovernanceDecision>
     */
    public function decisions(): array;

    /**
     * Gets the system's governance profile.
     */
    public function profile(): GovernanceProfile;
}
