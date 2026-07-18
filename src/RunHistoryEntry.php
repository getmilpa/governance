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
 * An entry in the run history: the adjudicated state of an individual run
 * against the plan in effect at the time the history is read.
 *
 * `$state` is any `ObservationState`, but in practice a per-entry only takes
 * the values `current`, `stale`, or `invalid` — `never_observed` describes the total absence
 * of history (an empty `RunHistory`), it does not apply to an individual entry. This
 * constraint is not validated here; it is documented by contract.
 */
final readonly class RunHistoryEntry
{
    /**
     * @param ObservationState $state  Adjudicated state of the run (current | stale | invalid)
     * @param ?GovernanceRun   $run    The observed run; null only when the file was unreadable
     *                                 (INVALID lookup with no reconstructible run)
     * @param ?string          $reason Readable reason for the state, when applicable (stale/invalid)
     */
    public function __construct(
        public ObservationState $state,
        public ?GovernanceRun $run,
        public ?string $reason,
    ) {
    }

    /**
     * Serializes the entry to an array for storage or transmission.
     *
     * @return array{state: string, run: array<string,mixed>|null, reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'run' => $this->run?->toArray(),
            'reason' => $this->reason,
        ];
    }
}
