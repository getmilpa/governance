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
use Milpa\Governance\GateOutcome;
use Milpa\Governance\GovernanceCompiler;
use Milpa\Governance\GovernanceDecision;
use Milpa\Governance\GovernanceException;
use Milpa\Governance\GovernanceGate;
use Milpa\Governance\GovernancePlan;
use Milpa\Governance\GovernanceProfile;
use Milpa\Governance\GovernanceValidator;
use Milpa\Governance\RequiredArtifact;
use PHPUnit\Framework\TestCase;

final class GovernanceCompilerTest extends TestCase
{
    public function test_supersession_is_derived_without_mutating_the_old_decision(): void
    {
        $adr0004 = $this->decision('ADR-0004', status: DecisionStatus::Accepted);
        $adr0014 = $this->decision('ADR-0014', supersedes: 'ADR-0004');

        // The profile only references the current decision (ADR-0014): the T2 wall rejects
        // any profile that directly references an already-superseded decision, even if
        // the one that supersedes it is also listed. The compilation knows the full lineage
        // because both decisions are passed in $decisions.
        $profile = $this->profile(['ADR-0014']);

        $plan = $this->compile([$adr0004, $adr0014], $profile);

        $d4 = $this->findDecision($plan, 'ADR-0004');

        self::assertSame('accepted', $d4['declaredStatus'], "ADR-0004's file never changed status");
        self::assertSame('superseded', $d4['effectiveStatus'], 'the effect of being superseded is derived');
        self::assertSame('ADR-0014', $d4['supersededBy']);

        // The input decision was not mutated: it is still the original, readonly, intact VO.
        self::assertSame(DecisionStatus::Accepted, $adr0004->status);
    }

    public function test_a_decision_not_superseded_stays_effective_accepted(): void
    {
        $adr0020 = $this->decision('ADR-0020');
        $profile = $this->profile(['ADR-0020']);

        $plan = $this->compile([$adr0020], $profile);

        $entry = $this->findDecision($plan, 'ADR-0020');

        self::assertSame('accepted', $entry['declaredStatus']);
        self::assertSame($entry['declaredStatus'], $entry['effectiveStatus']);
        self::assertNull($entry['supersededBy']);
    }

    public function test_compile_is_deterministic_byte_identical(): void
    {
        $adr0004 = $this->decision('ADR-0004');
        $adr0014 = $this->decision('ADR-0014', supersedes: 'ADR-0004', policies: [
            ['kind' => 'gate', 'id' => 'self-approval-block', 'tier' => 'advisory', 'boundTo' => null],
        ]);
        $decisions = [$adr0004, $adr0014];
        $gates = [new GovernanceGate('spec-review', 'Spec review', EnforcementTier::Convention, null)];
        $artifacts = [new RequiredArtifact('adr-log', EnforcementTier::Convention)];
        $profile = $this->profile(['ADR-0014'], $gates, $artifacts);

        $a = $this->compile($decisions, $profile)->toArray();
        $b = $this->compile($decisions, $profile)->toArray();

        self::assertSame(
            json_encode($a, JSON_THROW_ON_ERROR),
            json_encode($b, JSON_THROW_ON_ERROR),
            'the same input must produce a byte-identical toArray() across runs',
        );
    }

    public function test_one_rule_per_active_decision_from_its_governance_phrase(): void
    {
        $adrOld = $this->decision('ADR-0001');
        $adrNew = $this->decision('ADR-0002', supersedes: 'ADR-0001');
        $adrWithPhrase = $this->decision('ADR-0003', policies: [
            ['kind' => 'rule', 'statement' => 'Every ADR decision is immutable once accepted.'],
        ]);

        $profile = $this->profile(['ADR-0002', 'ADR-0003']);

        $plan = $this->compile([$adrOld, $adrNew, $adrWithPhrase], $profile);

        $ruleIds = array_map(static fn ($rule) => $rule->fromDecision, $plan->rules);
        sort($ruleIds);

        self::assertSame(['ADR-0002', 'ADR-0003'], $ruleIds, 'ADR-0001 is superseded and generates no rule');

        $ruleForNew = $this->findRule($plan, 'ADR-0002');
        self::assertSame('rule:ADR-0002', $ruleForNew->id);
        self::assertSame($adrNew->title, $ruleForNew->statement, 'without a kind:rule policy, the statement falls back to the title');

        $ruleForPhrase = $this->findRule($plan, 'ADR-0003');
        self::assertSame(
            'Every ADR decision is immutable once accepted.',
            $ruleForPhrase->statement,
            'with a kind:rule policy, the statement uses the explicit phrase',
        );
    }

    public function test_gates_merge_profile_and_adr_policies(): void
    {
        $adr = $this->decision('ADR-0004', policies: [
            ['kind' => 'gate', 'id' => 'self-approval-block', 'tier' => 'advisory', 'boundTo' => null],
        ]);
        $profileGates = [new GovernanceGate('spec-review', 'Spec review', EnforcementTier::Convention, null)];
        $profile = $this->profile(['ADR-0004'], $profileGates);

        $plan = $this->compile([$adr], $profile);

        $gateIds = array_map(static fn ($gate) => $gate->id, $plan->gates);
        sort($gateIds);

        self::assertSame(['self-approval-block', 'spec-review'], $gateIds);

        $policyGate = $this->findGate($plan, 'self-approval-block');
        self::assertSame(EnforcementTier::Advisory, $policyGate->tier);
        self::assertNull($policyGate->outcome, 'outcome is always null in the compiled plan — Amendment 3');
    }

    public function test_compile_calls_validate_and_propagates_its_failure(): void
    {
        $profile = $this->profile(['ADR-9999']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_DECISION_NOT_FOUND);

        $this->compile([], $profile);
    }

    public function test_artifacts_merge_profile_and_adr_policies(): void
    {
        $adr = $this->decision('ADR-0004', policies: [
            ['kind' => 'artifact', 'id' => 'threat-model', 'tier' => 'advisory'],
        ]);
        $profileArtifacts = [new RequiredArtifact('adr-log', EnforcementTier::Convention)];
        $profile = $this->profile(['ADR-0004'], [], $profileArtifacts);

        $plan = $this->compile([$adr], $profile);

        $artifactIds = array_map(static fn ($artifact) => $artifact->id, $plan->artifacts);
        sort($artifactIds);

        self::assertSame(['adr-log', 'threat-model'], $artifactIds);
    }

    public function test_rejected_decision_stays_out_of_the_plan_entirely(): void
    {
        // The exact scenario from the review: a rejected decision, not referenced by the
        // profile, carries an enforced kind:gate policy with a valid boundTo (so the T2 wall
        // lets it through). Amendment 2: rejected NEVER enters the plan — neither as a decision,
        // nor does its gate govern.
        $adr0004 = $this->decision('ADR-0004');
        $adr9000 = $this->decision('ADR-9000', status: DecisionStatus::Rejected, policies: [
            ['kind' => 'gate', 'id' => 'evil-gate', 'tier' => 'enforced', 'boundTo' => 'ci:governance'],
        ]);

        $profile = $this->profile(['ADR-0004']);

        $plan = $this->compile([$adr0004, $adr9000], $profile);

        $decisionIds = array_map(static fn (array $entry): string => $entry['id'], $plan->decisions);
        self::assertNotContains('ADR-9000', $decisionIds, 'rejected does not enter the plan');

        $gateIds = array_map(static fn (GovernanceGate $gate): string => $gate->id, $plan->gates);
        self::assertNotContains('evil-gate', $gateIds, "a rejected decision's gate does not govern");
    }

    public function test_proposed_and_deprecated_decisions_stay_out_of_the_plan(): void
    {
        $adr1000 = $this->decision('ADR-1000');
        $adr2000 = $this->decision('ADR-2000', status: DecisionStatus::Proposed);
        $adr3000 = $this->decision('ADR-3000', status: DecisionStatus::Deprecated);

        $profile = $this->profile(['ADR-1000']);

        $plan = $this->compile([$adr1000, $adr2000, $adr3000], $profile);

        $decisionIds = array_map(static fn (array $entry): string => $entry['id'], $plan->decisions);

        self::assertSame(['ADR-1000'], $decisionIds, 'proposed and deprecated do not enter the plan');
    }

    public function test_superseded_decision_shows_lineage_but_no_longer_governs(): void
    {
        $adr0004 = $this->decision('ADR-0004', policies: [
            ['kind' => 'gate', 'id' => 'old-gate', 'tier' => 'advisory', 'boundTo' => null],
        ]);
        $adr0014 = $this->decision('ADR-0014', supersedes: 'ADR-0004');

        $profile = $this->profile(['ADR-0014']);

        $plan = $this->compile([$adr0004, $adr0014], $profile);

        $d4 = $this->findDecision($plan, 'ADR-0004');
        self::assertSame('superseded', $d4['effectiveStatus'], 'the lineage is shown in decisions');
        self::assertSame('ADR-0014', $d4['supersededBy']);

        $gateIds = array_map(static fn (GovernanceGate $gate): string => $gate->id, $plan->gates);
        self::assertNotContains('old-gate', $gateIds, 'a superseded decision no longer governs');
    }

    public function test_artifact_policy_with_unresolvable_tier_throws_learnable_profile_invalid(): void
    {
        $adr = $this->decision('ADR-0004', policies: [
            ['kind' => 'artifact', 'id' => 'threat-model', 'tier' => 'BOGUS'],
        ]);
        $profile = $this->profile(['ADR-0004']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_PROFILE_INVALID);

        $this->compile([$adr], $profile);
    }

    public function test_gate_outcome_is_always_null_even_when_the_profile_declares_one(): void
    {
        $adr = $this->decision('ADR-0004');
        $profileGates = [new GovernanceGate('spec-review', 'Spec review', EnforcementTier::Convention, null, GateOutcome::PASSED)];
        $profile = $this->profile(['ADR-0004'], $profileGates);

        $plan = $this->compile([$adr], $profile);

        $gate = $this->findGate($plan, 'spec-review');
        self::assertNull($gate->outcome, 'Amendment 3: outcome is always null in the plan, even if the profile declares one');
    }

    /**
     * @param list<GovernanceDecision> $decisions
     */
    private function compile(array $decisions, GovernanceProfile $profile): GovernancePlan
    {
        $validator = new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO);

        return (new GovernanceCompiler($validator))->compile($decisions, $profile);
    }

    /**
     * @return array{id: string, title: string, declaredStatus: string, effectiveStatus: string, supersededBy: ?string, contentHash: string}
     */
    private function findDecision(GovernancePlan $plan, string $id): array
    {
        foreach ($plan->decisions as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }

        self::fail("decision '{$id}' was not found in the compiled plan");
    }

    private function findRule(GovernancePlan $plan, string $fromDecision): \Milpa\Governance\GovernanceRule
    {
        foreach ($plan->rules as $rule) {
            if ($rule->fromDecision === $fromDecision) {
                return $rule;
            }
        }

        self::fail("no rule derived from '{$fromDecision}' was found");
    }

    private function findGate(GovernancePlan $plan, string $id): GovernanceGate
    {
        foreach ($plan->gates as $gate) {
            if ($gate->id === $id) {
                return $gate;
            }
        }

        self::fail("gate '{$id}' was not found in the compiled plan");
    }

    /**
     * @param array<mixed> $policies
     */
    private function decision(
        string $id,
        DecisionStatus $status = DecisionStatus::Accepted,
        ?string $supersedes = null,
        array $policies = [],
    ): GovernanceDecision {
        return new GovernanceDecision(
            $id,
            "Title of {$id}",
            $status,
            'ADR#14',
            $supersedes,
            [],
            $policies,
            'hash-' . $id,
        );
    }

    /**
     * @param array<string>           $decisionIds
     * @param array<GovernanceGate>   $gates
     * @param array<RequiredArtifact> $artifacts
     */
    private function profile(array $decisionIds, array $gates = [], array $artifacts = []): GovernanceProfile
    {
        return new GovernanceProfile('0.1', 'milpa-framework', '2026.07.1', $decisionIds, $gates, $artifacts, []);
    }
}
