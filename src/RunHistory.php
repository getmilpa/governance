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
 * The adjudicated history of governance runs for a plan: the list of observed
 * entries and the pure reducers that summarize it.
 *
 * Makes queryable what used to be ephemeral — until now only the most recent run was
 * read; `RunHistory` preserves the whole series.
 */
final readonly class RunHistory
{
    /**
     * @param string                $planHash Integrity hash of the plan this history corresponds to
     * @param list<RunHistoryEntry> $entries  Entries of the history, in the observed order
     */
    public function __construct(
        public string $planHash,
        public array $entries,
    ) {
    }

    /**
     * Counts entries by adjudicated state and by producer type.
     *
     * The per-producer count (`byProducer`) skips entries with no run (`run === null`):
     * unreadable entries have no producer to count.
     *
     * @return array{total: int, byState: array<string,int>, byProducer: array<string,int>}
     */
    public function summary(): array
    {
        $byState = ['current' => 0, 'stale' => 0, 'invalid' => 0, 'never_observed' => 0];
        $byProducer = [];
        foreach ($this->entries as $e) {
            $byState[$e->state->value]++;
            if ($e->run !== null) {
                $k = $e->run->producer->kind;
                $byProducer[$k] = ($byProducer[$k] ?? 0) + 1;
            }
        }

        return ['total' => count($this->entries), 'byState' => $byState, 'byProducer' => $byProducer];
    }

    /**
     * Filters the entries whose evidence is valid for the current plan.
     *
     * @return list<RunHistoryEntry> only entries with CURRENT state
     */
    public function current(): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (RunHistoryEntry $e): bool => $e->state === ObservationState::CURRENT,
        ));
    }

    /**
     * Serializes the history to an array for storage or transmission.
     *
     * @return array{planHash: string, entries: list<array<string,mixed>>, summary: array<string,mixed>}
     */
    public function toArray(): array
    {
        return [
            'planHash' => $this->planHash,
            'entries' => array_map(static fn (RunHistoryEntry $e): array => $e->toArray(), $this->entries),
            'summary' => $this->summary(),
        ];
    }
}
