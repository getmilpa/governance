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
use Milpa\Governance\GovernanceDecision;
use Milpa\Governance\GovernanceException;
use Milpa\Governance\GovernanceGate;
use Milpa\Governance\GovernanceProfile;
use Milpa\Governance\GovernanceValidator;
use PHPUnit\Framework\TestCase;

final class GovernanceValidatorTest extends TestCase
{
    public function test_duplicate_decision_id_throws(): void
    {
        $decisions = [
            $this->decision('ADR-0004'),
            $this->decision('ADR-0004'),
        ];
        $profile = $this->profile(['ADR-0004']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_DECISION_DUPLICATE);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_profile_references_unknown_decision_throws(): void
    {
        $decisions = [$this->decision('ADR-0004')];
        $profile = $this->profile(['ADR-9999']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_DECISION_NOT_FOUND);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_supersedes_unknown_decision_throws(): void
    {
        $decisions = [
            $this->decision('ADR-0005', supersedes: 'ADR-9999'),
        ];
        $profile = $this->profile(['ADR-0005']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_DECISION_NOT_FOUND);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_supersession_cycle_throws(): void
    {
        $decisions = [
            $this->decision('ADR-0001', supersedes: 'ADR-0002'),
            $this->decision('ADR-0002', supersedes: 'ADR-0001'),
        ];
        $profile = $this->profile(['ADR-0001']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_SUPERSESSION_CYCLE);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_rejected_decision_in_profile_throws(): void
    {
        $decisions = [$this->decision('ADR-0004', status: DecisionStatus::Rejected)];
        $profile = $this->profile(['ADR-0004']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_DECISION_INACTIVE);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_superseded_decision_referenced_directly_in_profile_throws(): void
    {
        // ADR-0002 supersedes ADR-0001: ADR-0001 ends up superseded by derived effect.
        // The profile must not be able to reference ADR-0001 as if it were still active.
        $decisions = [
            $this->decision('ADR-0001'),
            $this->decision('ADR-0002', supersedes: 'ADR-0001'),
        ];
        $profile = $this->profile(['ADR-0001']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_DECISION_INACTIVE);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_enforced_gate_without_valid_boundTo_throws(): void
    {
        // the wall: a GovernanceGate tier=Enforced with boundTo null (or 'ci:inventado') → CODE_ENFORCEMENT_UNPROVEN
        $decisions = [$this->decision('ADR-0004')];
        $gates = [new GovernanceGate('phpstan', 'PHPStan level 6', EnforcementTier::Enforced, null)];
        $profile = $this->profile(['ADR-0004'], $gates);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_ENFORCEMENT_UNPROVEN);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_enforced_gate_with_boundTo_outside_allowlist_throws(): void
    {
        $decisions = [$this->decision('ADR-0004')];
        $gates = [new GovernanceGate('phpstan', 'PHPStan level 6', EnforcementTier::Enforced, 'ci:inventado')];
        $profile = $this->profile(['ADR-0004'], $gates);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_ENFORCEMENT_UNPROVEN);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_advisory_or_convention_gate_without_boundTo_is_ok(): void
    {
        $decisions = [$this->decision('ADR-0004')];
        $gates = [new GovernanceGate('spec-review', 'Spec review', EnforcementTier::Convention, null)];
        $profile = $this->profile(['ADR-0004'], $gates);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);

        self::assertTrue(true, 'validate() must not throw for non-Enforced gates without boundTo');
    }

    public function test_a_valid_profile_passes(): void
    {
        $decisions = [$this->decision('ADR-0004')];
        $gates = [new GovernanceGate('phpstan', 'PHPStan level 6', EnforcementTier::Enforced, 'ci:quality:phpstan')];
        $profile = $this->profile(['ADR-0004'], $gates);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);

        self::assertTrue(true, 'a coherent profile with properly bound enforced gates must not throw');
    }

    public function test_enforced_gate_bound_to_governance_authenticity_is_ok(): void
    {
        // GOV-7: the governance-authenticity gate will be bound to this mechanism once the authority
        // activates the boundary. The allowlist must recognize it as proven enforcement.
        $decisions = [$this->decision('ADR-0004')];
        $gates = [new GovernanceGate('governance-authenticity', 'Governance authenticity', EnforcementTier::Enforced, 'ci:governance:authenticity')];
        $profile = $this->profile(['ADR-0004'], $gates);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);

        self::assertTrue(true, 'an enforced gate bound to ci:governance:authenticity must not throw');
    }

    public function test_policy_gate_enforced_without_bound_to_throws(): void
    {
        // the wall also applies to gates declared as kind:gate policies within a decision,
        // even when the profile brings no typed gates of its own. The tier 'enforced' (lowercase, valid)
        // resolves correctly and falls into the classic wall.
        $decisions = [
            $this->decision('ADR-0014', policies: [
                ['kind' => 'gate', 'id' => 'self-approval-block', 'tier' => 'enforced', 'boundTo' => null],
            ]),
        ];
        $profile = $this->profile(['ADR-0014']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_ENFORCEMENT_UNPROVEN);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_policy_gate_with_unresolvable_tier_throws_profile_invalid(): void
    {
        // the fail-open leak: a mis-capitalized tier ('Enforced' instead of 'enforced') must not
        // slip past the wall with a free pass just because it fails to resolve to EnforcementTier::Enforced.
        // EnforcementTier::tryFrom() does not resolve 'Enforced' → it must fail-closed, not fail-open.
        $decisions = [
            $this->decision('ADR-0014', policies: [
                ['kind' => 'gate', 'id' => 'sneaky-gate', 'tier' => 'Enforced', 'boundTo' => null],
            ]),
        ];
        $profile = $this->profile(['ADR-0014']);

        $this->expectException(GovernanceException::class);
        $this->expectExceptionMessage(GovernanceException::CODE_PROFILE_INVALID);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);
    }

    public function test_policy_gate_enforced_with_valid_bound_to_is_ok(): void
    {
        $decisions = [
            $this->decision('ADR-0014', policies: [
                ['kind' => 'gate', 'id' => 'self-approval-block', 'tier' => 'enforced', 'boundTo' => 'runtime:SelfApprovalException'],
            ]),
        ];
        $profile = $this->profile(['ADR-0014']);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);

        self::assertTrue(true, 'an enforced policy-gate with an allowlisted boundTo must not throw');
    }

    public function test_policy_entry_that_is_not_kind_gate_is_ignored(): void
    {
        // free-form metadata without kind:gate (even if it declares a loose "enforced" tier) is ignored — it is not a gate.
        $decisions = [
            $this->decision('ADR-0014', policies: [
                ['kind' => 'note', 'tier' => 'enforced', 'boundTo' => null],
            ]),
        ];
        $profile = $this->profile(['ADR-0014']);

        (new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO))->validate($decisions, $profile);

        self::assertTrue(true, 'policies without kind:gate are free-form metadata and are ignored');
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
     * @param array<string>         $decisionIds
     * @param array<GovernanceGate> $gates
     */
    private function profile(array $decisionIds, array $gates = []): GovernanceProfile
    {
        return new GovernanceProfile('0.1', 'milpa-framework', '2026.07.1', $decisionIds, $gates, [], []);
    }
}
