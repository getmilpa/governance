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
use Milpa\Governance\GateOutcomeRecord;
use Milpa\Governance\GovernanceException;
use Milpa\Governance\GovernanceGate;
use Milpa\Governance\GovernancePlan;
use Milpa\Governance\GovernanceRun;
use Milpa\Governance\GovernanceStatus;
use Milpa\Governance\GovernanceStatusProjector;
use Milpa\Governance\InvocationIdentity;
use Milpa\Governance\ObservationState;
use Milpa\Governance\ProducerIdentity;
use Milpa\Governance\RunHistory;
use Milpa\Governance\RunHistoryEntry;
use PHPUnit\Framework\TestCase;

/**
 * `GovernanceStatusProjector::project()` (GOV-3): derives the per-gate status ONLY over
 * `RunHistory::current()`, with frozen temporal semantics. The only exception it throws is
 * the plan↔history mismatch (amendment 1). Reuses the GOV-2 output, does not re-adjudicate.
 */
final class GovernanceStatusProjectorTest extends TestCase
{
    private const string PLAN_HASH = 'plan-hash-xyz';

    public function test_history_of_another_plan_throws_learnable_error(): void
    {
        $plan = $this->plan();
        $history = new RunHistory('other-plan-hash', []);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage('MILPA_GOVERNANCE_HISTORY_PLAN_MISMATCH');

        (new GovernanceStatusProjector())->project($plan, $history);
    }

    public function test_empty_history_gives_all_gates_no_signal(): void
    {
        $plan = $this->plan();
        $history = new RunHistory(self::PLAN_HASH, []);

        $status = (new GovernanceStatusProjector())->project($plan, $history);

        self::assertCount(2, $status->gates);
        foreach ($status->gates as $g) {
            self::assertNull($g->condition, "gate {$g->gateId} must have 'no signal' with no evidence");
            self::assertNull($g->observedAt);
            self::assertNull($g->lastRunId);
            self::assertNull($g->lastPassedAt);
        }
    }

    public function test_gate_status_order_follows_the_plan_gate_order(): void
    {
        $plan = $this->plan();
        $status = (new GovernanceStatusProjector())->project($plan, new RunHistory(self::PLAN_HASH, []));

        self::assertSame('phpstan', $status->gates[0]->gateId);
        self::assertSame('spec-review', $status->gates[1]->gateId);
    }

    public function test_condition_comes_from_the_most_recent_current_run_that_observed_the_gate(): void
    {
        $plan = $this->plan();
        $history = new RunHistory(self::PLAN_HASH, [
            $this->currentEntry('run-old', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::PASSED)]),
            $this->currentEntry('run-new', '2026-07-17T11:00:00+00:00', [$this->rec('phpstan', GateOutcome::FAILED)]),
        ]);

        $status = (new GovernanceStatusProjector())->project($plan, $history);
        $phpstan = $this->gate($status, 'phpstan');

        self::assertSame(GateOutcome::FAILED, $phpstan->condition, 'the most recent run by finishedAt wins');
        self::assertSame('run-new', $phpstan->lastRunId);
        self::assertEquals(new \DateTimeImmutable('2026-07-17T11:00:00+00:00'), $phpstan->observedAt);
    }

    public function test_a_later_current_run_that_does_not_observe_the_gate_preserves_the_prior_observation(): void
    {
        $plan = $this->plan();
        // Run 10 observed phpstan; Run 11 (more recent) did NOT observe it.
        $history = new RunHistory(self::PLAN_HASH, [
            $this->currentEntry('run-10', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::PASSED)]),
            $this->currentEntry('run-11', '2026-07-17T11:00:00+00:00', [$this->rec('spec-review', GateOutcome::PASSED)]),
        ]);

        $status = (new GovernanceStatusProjector())->project($plan, $history);
        $phpstan = $this->gate($status, 'phpstan');

        self::assertSame(GateOutcome::PASSED, $phpstan->condition, 'a run that does not observe the gate does not erase the previous observation');
        self::assertSame('run-10', $phpstan->lastRunId);
        self::assertEquals(new \DateTimeImmutable('2026-07-17T10:00:00+00:00'), $phpstan->observedAt);
    }

    public function test_ties_on_finished_at_are_broken_by_run_id_deterministically(): void
    {
        $plan = $this->plan();
        // Same finishedAt; the larger runId ('run-zzz' > 'run-aaa') wins the tiebreak.
        $history = new RunHistory(self::PLAN_HASH, [
            $this->currentEntry('run-aaa', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::PASSED)]),
            $this->currentEntry('run-zzz', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::FAILED)]),
        ]);

        $status = (new GovernanceStatusProjector())->project($plan, $history);

        self::assertSame(GateOutcome::FAILED, $this->gate($status, 'phpstan')->condition);
        self::assertSame('run-zzz', $this->gate($status, 'phpstan')->lastRunId);
    }

    public function test_waived_does_not_feed_last_passed_at(): void
    {
        $plan = $this->plan();
        $history = new RunHistory(self::PLAN_HASH, [
            $this->currentEntry('run-1', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::WAIVED)]),
        ]);

        $status = (new GovernanceStatusProjector())->project($plan, $history);
        $phpstan = $this->gate($status, 'phpstan');

        self::assertSame(GateOutcome::WAIVED, $phpstan->condition);
        self::assertNull($phpstan->lastPassedAt, 'WAIVED is not a pass — it does not feed lastPassedAt');
    }

    public function test_last_passed_at_is_the_most_recent_passed_even_when_failing_now(): void
    {
        $plan = $this->plan();
        $history = new RunHistory(self::PLAN_HASH, [
            $this->currentEntry('run-1', '2026-07-15T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::PASSED)]),
            $this->currentEntry('run-2', '2026-07-16T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::PASSED)]),
            $this->currentEntry('run-3', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::FAILED)]),
        ]);

        $status = (new GovernanceStatusProjector())->project($plan, $history);
        $phpstan = $this->gate($status, 'phpstan');

        self::assertSame(GateOutcome::FAILED, $phpstan->condition, 'current state = failed');
        self::assertEquals(new \DateTimeImmutable('2026-07-16T10:00:00+00:00'), $phpstan->lastPassedAt, 'last pass = run-2');
    }

    public function test_pending_is_an_observed_condition_not_no_signal(): void
    {
        $plan = $this->plan();
        $history = new RunHistory(self::PLAN_HASH, [
            $this->currentEntry('run-1', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::PENDING)]),
        ]);

        $status = (new GovernanceStatusProjector())->project($plan, $history);
        $phpstan = $this->gate($status, 'phpstan');

        self::assertSame(GateOutcome::PENDING, $phpstan->condition, 'pending is observed, NOT no signal');
        self::assertNotNull($phpstan->observedAt);
    }

    public function test_stale_and_invalid_entries_do_not_contribute(): void
    {
        $plan = $this->plan();
        // Only `current()` counts: a STALE entry with a PASSED phpstan must NOT taint the status.
        $history = new RunHistory(self::PLAN_HASH, [
            new RunHistoryEntry(ObservationState::STALE, $this->makeRun('run-stale', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::PASSED)]), 'old plan'),
            new RunHistoryEntry(ObservationState::INVALID, null, 'unreadable'),
        ]);

        $status = (new GovernanceStatusProjector())->project($plan, $history);

        self::assertNull($this->gate($status, 'phpstan')->condition, 'a stale run is not current evidence — the gate stays without signal');
    }

    public function test_projector_does_not_mutate_plan_or_history(): void
    {
        $plan = $this->plan();
        $entries = [
            $this->currentEntry('run-1', '2026-07-17T10:00:00+00:00', [$this->rec('phpstan', GateOutcome::PASSED)]),
        ];
        $history = new RunHistory(self::PLAN_HASH, $entries);

        (new GovernanceStatusProjector())->project($plan, $history);

        foreach ($plan->gates as $gate) {
            self::assertNull($gate->outcome, 'the input plan is not mutated');
        }
        self::assertSame($entries, $history->entries, 'the input history is not mutated');
    }

    // ---- helpers ----

    private function plan(): GovernancePlan
    {
        return new GovernancePlan(
            'milpa-governance-plan/v1',
            'test-profile',
            '1',
            'profile-hash-abc',
            [],
            [],
            [
                new GovernanceGate('phpstan', 'Static analysis', EnforcementTier::Enforced, 'ci:quality:phpstan'),
                new GovernanceGate('spec-review', 'Spec review', EnforcementTier::Convention, null),
            ],
            [],
            [],
            [],
            self::PLAN_HASH,
        );
    }

    /**
     * @param list<GateOutcomeRecord> $outcomes
     */
    private function currentEntry(string $runId, string $finishedAt, array $outcomes): RunHistoryEntry
    {
        return new RunHistoryEntry(ObservationState::CURRENT, $this->makeRun($runId, $finishedAt, $outcomes), null);
    }

    /**
     * Named `makeRun` (not `run`) to avoid colliding with the `final` method that
     * `PHPUnit\Framework\TestCase` already declares.
     *
     * @param list<GateOutcomeRecord> $outcomes
     */
    private function makeRun(string $runId, string $finishedAt, array $outcomes): GovernanceRun
    {
        return new GovernanceRun(
            'milpa-governance-run/v1',
            $runId,
            'profile-hash-abc',
            self::PLAN_HASH,
            new InvocationIdentity('human', 'rodrigo'),
            new ProducerIdentity('ci-check', 'scripts/ci-check.php', null),
            'local',
            new \DateTimeImmutable('2026-07-17T09:00:00+00:00'),
            new \DateTimeImmutable($finishedAt),
            [],
            $outcomes,
        );
    }

    private function rec(string $gate, GateOutcome $outcome): GateOutcomeRecord
    {
        return new GateOutcomeRecord($gate, $outcome, null, 'ci-check:step', null);
    }

    private function gate(GovernanceStatus $status, string $gateId): \Milpa\Governance\GateStatus
    {
        foreach ($status->gates as $g) {
            if ($g->gateId === $gateId) {
                return $g;
            }
        }
        self::fail("gate '{$gateId}' is not in the status");
    }
}
