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

use Milpa\Governance\DecisionStatus;
use Milpa\Governance\EnforcementTier;
use Milpa\Governance\GovernanceCompiler;
use Milpa\Governance\GovernanceDecision;
use Milpa\Governance\GovernanceGate;
use Milpa\Governance\GovernanceProfile;
use Milpa\Governance\GovernanceValidator;
use PHPUnit\Framework\TestCase;

/**
 * `planHash`: the deterministic link that binds a `GovernanceRun` (the evidence) to an
 * EXACT `GovernancePlan` — protects against a different recompilation of the same profile.
 *
 * Its canonicalization is FROZEN (see `GovernanceCompiler::computePlanHash()`): two
 * implementations must never produce different hashes for the same plan-as-law. The golden
 * fixture below (`assertSame` with the real value) acts as a lock against drift.
 */
final class PlanHashTest extends TestCase
{
    public function test_plan_hash_is_deterministic_and_golden(): void
    {
        $decisions = [$this->decision('ADR-0004')];
        $profile = new GovernanceProfile('0.1', 'x', '1', ['ADR-0004'], [], [], []);
        $compiler = new GovernanceCompiler(new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO));

        $p1 = $compiler->compile($decisions, $profile);
        $p2 = $compiler->compile($decisions, $profile);

        self::assertSame($p1->planHash, $p2->planHash, 'the same input must produce the same planHash');
        self::assertSame(64, strlen($p1->planHash), 'sha256 in hexadecimal is 64 characters');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $p1->planHash);

        // Golden fixture: the hash must NOT change between canonicalization implementations.
        self::assertSame('43ede66bf964e448d590749a49d6e7bb1fc611ffcc0d8d5915bc025c67fd710f', $p1->planHash);
    }

    public function test_gate_outcome_is_typed_and_null_in_pure_plan(): void
    {
        $decisions = [$this->decision('ADR-0004')];
        $gates = [new GovernanceGate('spec-review', 'Spec review', EnforcementTier::Convention, null)];
        $profile = new GovernanceProfile('0.1', 'x', '1', ['ADR-0004'], $gates, [], []);
        $compiler = new GovernanceCompiler(new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO));

        $plan = $compiler->compile($decisions, $profile);

        self::assertNotEmpty($plan->gates, 'the fixture must produce at least one gate');
        foreach ($plan->gates as $gate) {
            self::assertNull($gate->outcome, 'every gate of a pure plan has outcome === null (?GateOutcome)');
        }
    }

    private function decision(string $id): GovernanceDecision
    {
        return new GovernanceDecision(
            $id,
            "Title of {$id}",
            DecisionStatus::Accepted,
            'ADR#14',
            null,
            [],
            [],
            'aaa',
        );
    }
}
