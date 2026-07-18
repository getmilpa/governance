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
use Milpa\Governance\GovernanceGate;
use Milpa\Governance\GovernancePlan;
use Milpa\Governance\GovernanceRun;
use Milpa\Governance\GovernanceRunLookup;
use Milpa\Governance\GovernanceRunProjector;
use Milpa\Governance\InvocationIdentity;
use Milpa\Governance\ObservationState;
use Milpa\Governance\ProducerIdentity;
use Milpa\Governance\RunLookupState;
use PHPUnit\Framework\TestCase;

/**
 * `GovernanceRunProjector::observe()` — the GOV-1 wall: overlays the evidence (a
 * `GovernanceRun`) onto the law (a `GovernancePlan`) and decides whether that evidence is honest.
 *
 * "Evidence may repeat the law to show what it observed; it may never redefine it."
 */
final class GovernanceRunProjectorTest extends TestCase
{
    private const string PLAN_HASH = 'plan-hash-xyz';
    private const string PROFILE_HASH = 'profile-hash-abc';

    public function test_none_lookup_is_never_observed_with_null_outcomes(): void
    {
        $plan = $this->plan();
        $lookup = new GovernanceRunLookup(RunLookupState::NONE, null, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::NEVER_OBSERVED, $observation->state);
        self::assertNull($observation->run);
        self::assertNull($observation->reason);
        foreach ($observation->plan->gates as $gate) {
            self::assertNull($gate->outcome);
        }
    }

    public function test_invalid_lookup_propagates(): void
    {
        $plan = $this->plan();
        $lookup = new GovernanceRunLookup(RunLookupState::INVALID, null, 'x');

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::INVALID, $observation->state);
        self::assertSame('x', $observation->reason);
    }

    public function test_stale_by_plan_hash(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(planHash: 'other-plan-hash', profileHash: self::PROFILE_HASH);
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::STALE, $observation->state);
        self::assertNotNull($observation->reason);
        foreach ($observation->plan->gates as $gate) {
            self::assertNull($gate->outcome);
        }
    }

    public function test_stale_by_profile_hash(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(planHash: self::PLAN_HASH, profileHash: 'other-profile-hash');
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::STALE, $observation->state);
        self::assertNotNull($observation->reason);
    }

    public function test_current_stamps_outcomes(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(outcomes: [
            new GateOutcomeRecord('phpstan', GateOutcome::PASSED, 'ci:quality:phpstan', 'ci-check:step-3'),
        ]);
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::CURRENT, $observation->state);
        self::assertNull($observation->reason);
        self::assertSame($run, $observation->run);

        $byId = [];
        foreach ($observation->plan->gates as $gate) {
            $byId[$gate->id] = $gate;
        }
        self::assertSame(GateOutcome::PASSED, $byId['phpstan']->outcome);
        self::assertSame(GateOutcome::PENDING, $byId['spec-review']->outcome, 'a gate with no record stays PENDING, never a fabricated passed');
    }

    public function test_input_plan_is_not_mutated(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(outcomes: [
            new GateOutcomeRecord('phpstan', GateOutcome::PASSED, 'ci:quality:phpstan', 'ci-check:step-3'),
        ]);
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        (new GovernanceRunProjector())->observe($plan, $lookup);

        foreach ($plan->gates as $gate) {
            self::assertNull($gate->outcome, 'the INPUT plan is never mutated');
        }
    }

    public function test_invalid_duplicate_gate(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(outcomes: [
            new GateOutcomeRecord('phpstan', GateOutcome::PASSED, 'ci:quality:phpstan', 'ci-check:step-3'),
            new GateOutcomeRecord('phpstan', GateOutcome::PASSED, 'ci:quality:phpstan', 'ci-check:step-4'),
        ]);
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::INVALID, $observation->state);
        self::assertStringContainsString('phpstan', (string) $observation->reason);
    }

    public function test_invalid_unknown_gate(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(outcomes: [
            new GateOutcomeRecord('ghost', GateOutcome::PASSED, null, 'ci-check:step-3'),
        ]);
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::INVALID, $observation->state);
        self::assertStringContainsString('ghost', (string) $observation->reason);
    }

    public function test_invalid_finished_before_started(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(
            startedAt: new \DateTimeImmutable('2026-07-16T10:01:00+00:00'),
            finishedAt: new \DateTimeImmutable('2026-07-16T10:00:00+00:00'),
        );
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::INVALID, $observation->state);
    }

    public function test_invalid_empty_run_id(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(runId: '');
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::INVALID, $observation->state);
    }

    public function test_invalid_passed_without_producer_ref(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(outcomes: [
            new GateOutcomeRecord('phpstan', GateOutcome::PASSED, 'ci:quality:phpstan', null),
        ]);
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::INVALID, $observation->state);
        self::assertStringContainsString('phpstan', (string) $observation->reason);
    }

    public function test_invalid_waived_without_justification(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(outcomes: [
            new GateOutcomeRecord('phpstan', GateOutcome::WAIVED, 'ci:quality:phpstan', 'ci-check:step-3', null),
        ]);
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::INVALID, $observation->state);
        self::assertStringContainsString('phpstan', (string) $observation->reason);
    }

    public function test_invalid_boundTo_divergent(): void
    {
        $plan = $this->plan();
        $run = $this->makeRun(outcomes: [
            new GateOutcomeRecord('phpstan', GateOutcome::PASSED, 'ci:test:unit', 'ci-check:step-3'),
        ]);
        $lookup = new GovernanceRunLookup(RunLookupState::FOUND, $run, null);

        $observation = (new GovernanceRunProjector())->observe($plan, $lookup);

        self::assertSame(ObservationState::INVALID, $observation->state);
        self::assertStringContainsString('phpstan', (string) $observation->reason);
    }

    /**
     * Plan fixture with two gates: `phpstan` (bound to `ci:quality:phpstan`) and `spec-review`
     * (unbound), to test both the stamping and the `PENDING` of a gate with no record.
     */
    private function plan(): GovernancePlan
    {
        return new GovernancePlan(
            'milpa-governance-plan/v1',
            'test-profile',
            '1',
            self::PROFILE_HASH,
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
     * @param array<GateOutcomeRecord> $outcomes
     */
    private function makeRun(
        string $planHash = self::PLAN_HASH,
        string $profileHash = self::PROFILE_HASH,
        string $runId = 'run-1',
        ?\DateTimeImmutable $startedAt = null,
        ?\DateTimeImmutable $finishedAt = null,
        array $outcomes = [],
    ): GovernanceRun {
        return new GovernanceRun(
            'milpa-governance-run/v1',
            $runId,
            $profileHash,
            $planHash,
            new InvocationIdentity('human', 'rodrigo'),
            new ProducerIdentity('ci-check', 'scripts/ci-check.php', null),
            'local',
            $startedAt ?? new \DateTimeImmutable('2026-07-16T10:00:00+00:00'),
            $finishedAt ?? new \DateTimeImmutable('2026-07-16T10:01:00+00:00'),
            [],
            $outcomes,
        );
    }
}
