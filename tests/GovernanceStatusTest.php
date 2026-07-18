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

use Milpa\Governance\EnforcementTier;
use Milpa\Governance\GateOutcome;
use Milpa\Governance\GateStatus;
use Milpa\Governance\GovernanceStatus;
use PHPUnit\Framework\TestCase;

/**
 * The health status VOs (GOV-3): `GateStatus` (one gate) and `GovernanceStatus` (the
 * report). `condition === null` is "no signal" and is NEVER collapsed with `pending`.
 */
final class GovernanceStatusTest extends TestCase
{
    public function test_gate_status_toarray_distinguishes_no_signal_from_pending(): void
    {
        $sinSenal = new GateStatus('spec-required', EnforcementTier::Convention, null, null, null, null);
        $pending = new GateStatus(
            'phpstan',
            EnforcementTier::Enforced,
            GateOutcome::PENDING,
            new \DateTimeImmutable('2026-07-17T10:01:00+00:00'),
            'run-a',
            null,
        );

        self::assertNull($sinSenal->toArray()['condition'], 'no signal → condition null');
        self::assertSame('pending', $pending->toArray()['condition'], 'pending is an observed condition, not null');
    }

    public function test_gate_status_toarray_shape_uses_atom_dates_and_null_where_absent(): void
    {
        $status = new GateStatus(
            'phpunit',
            EnforcementTier::Enforced,
            GateOutcome::FAILED,
            new \DateTimeImmutable('2026-07-17T10:02:00+00:00'),
            'run-b',
            new \DateTimeImmutable('2026-07-16T09:10:00+00:00'),
        );

        self::assertSame([
            'gate' => 'phpunit',
            'tier' => 'enforced',
            'condition' => 'failed',
            'observedAt' => '2026-07-17T10:02:00+00:00',
            'lastRunId' => 'run-b',
            'lastPassedAt' => '2026-07-16T09:10:00+00:00',
        ], $status->toArray());
    }

    public function test_summary_counts_each_gate_exactly_once(): void
    {
        $status = new GovernanceStatus('plan-hash-1', [
            new GateStatus('g1', EnforcementTier::Enforced, GateOutcome::PASSED, null, null, null),
            new GateStatus('g2', EnforcementTier::Enforced, GateOutcome::PASSED, null, null, null),
            new GateStatus('g3', EnforcementTier::Enforced, GateOutcome::FAILED, null, null, null),
            new GateStatus('g4', EnforcementTier::Convention, GateOutcome::WAIVED, null, null, null),
            new GateStatus('g5', EnforcementTier::Advisory, GateOutcome::PENDING, null, null, null),
            new GateStatus('g6', EnforcementTier::Convention, null, null, null, null),
        ]);

        self::assertSame([
            'total' => 6,
            'passed' => 2,
            'failed' => 1,
            'waived' => 1,
            'pending' => 1,
            'noSignal' => 1,
        ], $status->summary());

        // Invariant: the sum of the buckets is exactly the total (each gate counted once).
        $s = $status->summary();
        self::assertSame($s['total'], $s['passed'] + $s['failed'] + $s['waived'] + $s['pending'] + $s['noSignal']);
    }

    public function test_governance_status_toarray_carries_planhash_gates_and_summary(): void
    {
        $status = new GovernanceStatus('plan-hash-2', [
            new GateStatus('g1', EnforcementTier::Enforced, null, null, null, null),
        ]);

        $a = $status->toArray();
        self::assertSame('plan-hash-2', $a['planHash']);
        self::assertCount(1, $a['gates']);
        self::assertNull($a['gates'][0]['condition']);
        self::assertSame(1, $a['summary']['noSignal']);
        self::assertSame(1, $a['summary']['total']);
    }
}
