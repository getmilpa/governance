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
use Milpa\Governance\GovernanceGate;
use Milpa\Governance\GovernanceHistoryProjector;
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
 * `GovernanceHistoryProjector::project()` — adjudicates each run in a list against the
 * current plan, reusing the GOV-1 per-run wall (`GovernanceRunProjector::observe()`)
 * without reimplementing any check.
 */
final class GovernanceHistoryProjectorTest extends TestCase
{
    private const string PLAN_HASH = 'plan-hash-xyz';
    private const string PROFILE_HASH = 'profile-hash-abc';

    public function test_project_adjudicates_each_lookup_via_observe(): void
    {
        $plan = $this->plan();

        $current = $this->makeRun(runId: 'run-current');
        $stale = $this->makeRun(runId: 'run-stale', planHash: 'other-plan-hash');

        $lookups = [
            new GovernanceRunLookup(RunLookupState::FOUND, $current, null),
            new GovernanceRunLookup(RunLookupState::FOUND, $stale, null),
            new GovernanceRunLookup(RunLookupState::INVALID, null, 'unreadable file: run-3.json'),
        ];

        $history = (new GovernanceHistoryProjector(new GovernanceRunProjector()))->project($plan, $lookups);

        self::assertSame(self::PLAN_HASH, $history->planHash);
        self::assertCount(3, $history->entries);

        self::assertSame(ObservationState::CURRENT, $history->entries[0]->state);
        self::assertSame('run-current', $history->entries[0]->run?->runId);
        self::assertNull($history->entries[0]->reason);

        self::assertSame(ObservationState::STALE, $history->entries[1]->state);
        self::assertSame('run-stale', $history->entries[1]->run?->runId);
        self::assertNotNull($history->entries[1]->reason);

        self::assertSame(ObservationState::INVALID, $history->entries[2]->state);
        self::assertNull($history->entries[2]->run);
        self::assertSame('unreadable file: run-3.json', $history->entries[2]->reason);
    }

    public function test_aggregation_current_excludes_stale_and_invalid(): void
    {
        $plan = $this->plan();

        $lookups = [
            new GovernanceRunLookup(RunLookupState::FOUND, $this->makeRun(runId: 'run-current'), null),
            new GovernanceRunLookup(RunLookupState::FOUND, $this->makeRun(runId: 'run-stale', planHash: 'other-plan-hash'), null),
            new GovernanceRunLookup(RunLookupState::INVALID, null, 'unreadable'),
        ];

        $history = (new GovernanceHistoryProjector(new GovernanceRunProjector()))->project($plan, $lookups);

        $current = $history->current();
        self::assertCount(1, $current, 'current() only keeps the CURRENT entry, excluding stale and invalid');
        self::assertSame('run-current', $current[0]->run?->runId);

        $summary = $history->summary();
        self::assertSame(1, $summary['byState']['current']);
        self::assertSame(1, $summary['byState']['stale']);
        self::assertSame(1, $summary['byState']['invalid']);
    }

    public function test_does_not_mutate_the_plan(): void
    {
        $plan = $this->plan();
        $lookups = [
            new GovernanceRunLookup(RunLookupState::FOUND, $this->makeRun(runId: 'run-current'), null),
        ];

        (new GovernanceHistoryProjector(new GovernanceRunProjector()))->project($plan, $lookups);

        foreach ($plan->gates as $gate) {
            self::assertNull($gate->outcome, 'the INPUT plan is never mutated, even when an entry is CURRENT');
        }
    }

    public function test_empty_lookups_gives_empty_history(): void
    {
        $plan = $this->plan();

        $history = (new GovernanceHistoryProjector(new GovernanceRunProjector()))->project($plan, []);

        self::assertSame(self::PLAN_HASH, $history->planHash);
        self::assertSame([], $history->entries);
    }

    /**
     * Plan fixture with two gates, matching the template from `GovernanceRunProjectorTest`.
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

    private function makeRun(
        string $planHash = self::PLAN_HASH,
        string $profileHash = self::PROFILE_HASH,
        string $runId = 'run-1',
    ): GovernanceRun {
        return new GovernanceRun(
            'milpa-governance-run/v1',
            $runId,
            $profileHash,
            $planHash,
            new InvocationIdentity('human', 'rodrigo'),
            new ProducerIdentity('ci-check', 'scripts/ci-check.php', null),
            'local',
            new \DateTimeImmutable('2026-07-17T10:00:00+00:00'),
            new \DateTimeImmutable('2026-07-17T10:01:00+00:00'),
            [],
            [],
        );
    }
}
