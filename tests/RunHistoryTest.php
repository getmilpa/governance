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

namespace Milpa\Governance\Tests;

use PHPUnit\Framework\TestCase;
use Milpa\Governance\{RunHistory, RunHistoryEntry, ObservationState, GovernanceRun, InvocationIdentity, ProducerIdentity};

final class RunHistoryTest extends TestCase
{
    private function makeRun(string $id, string $producerKind): GovernanceRun
    {
        return new GovernanceRun(
            'milpa-governance-run/v1',
            $id,
            'ph',
            'plh',
            new InvocationIdentity('human', 'rod'),
            new ProducerIdentity($producerKind, 'x', null),
            'local',
            new \DateTimeImmutable('2026-07-17T10:00:00+00:00'),
            new \DateTimeImmutable('2026-07-17T10:01:00+00:00'),
            [],
            [],
        );
    }

    public function test_summary_counts_by_state_and_producer(): void
    {
        $history = new RunHistory('plh', [
            new RunHistoryEntry(ObservationState::CURRENT, $this->makeRun('r1', 'ci-check'), null),
            new RunHistoryEntry(ObservationState::CURRENT, $this->makeRun('r2', 'ci-check'), null),
            new RunHistoryEntry(ObservationState::STALE, $this->makeRun('r3', 'gitlab-ci'), 'old plan'),
            new RunHistoryEntry(ObservationState::INVALID, null, 'unreadable file: r4.json'),
        ]);
        $s = $history->summary();
        self::assertSame(2, $s['byState']['current']);
        self::assertSame(1, $s['byState']['stale']);
        self::assertSame(1, $s['byState']['invalid']);
        self::assertSame(4, $s['total']);

        // byProducer: groups by actual kind (not just a total) and skips the entry
        // with a null run — the unreadable INVALID one has no producer to count.
        self::assertSame(2, $s['byProducer']['ci-check'], 'the 2 runs with producer ci-check are grouped together');
        self::assertSame(1, $s['byProducer']['gitlab-ci'], 'the run with a different producer is counted separately');
        self::assertCount(2, $s['byProducer'], 'byProducer only has the 2 observed producer keys, with no noise from the entry with a null run');
    }

    public function test_current_returns_only_current_entries(): void
    {
        // STALE first, CURRENT second: CURRENT sits at index 1 of the original
        // array, so current() must truly reindex to expose it at [0].
        $history = new RunHistory('plh', [
            new RunHistoryEntry(ObservationState::STALE, $this->makeRun('r1', 'ci-check'), 'x'),
            new RunHistoryEntry(ObservationState::CURRENT, $this->makeRun('r2', 'ci-check'), null),
        ]);
        $current = $history->current();
        self::assertCount(1, $current);
        self::assertArrayHasKey(0, $current, 'current() truly reindexes the resulting list from 0');
        self::assertSame(ObservationState::CURRENT, $current[0]->state);
        self::assertSame('r2', $current[0]->run?->runId, 'the entry kept is the CURRENT one (r2), not the filtered STALE one');
    }

    public function test_toarray_shape(): void
    {
        $entry = new RunHistoryEntry(ObservationState::INVALID, null, 'unreadable: r.json');
        $a = $entry->toArray();
        self::assertSame('invalid', $a['state']);
        self::assertNull($a['run']);
        self::assertSame('unreadable: r.json', $a['reason']);

        $history = new RunHistory('plh', [$entry]);
        $h = $history->toArray();
        self::assertSame('plh', $h['planHash']);
        self::assertCount(1, $h['entries']);
        self::assertArrayHasKey('summary', $h);
    }
}
