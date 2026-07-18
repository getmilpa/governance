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
 * Exception of the governance system: a contract broken or violated.
 *
 * Each factory describes what broke, why, and what to do to fix it.
 */
final class GovernanceException extends \RuntimeException
{
    public const CODE_PROFILE_MISSING = 'MILPA_GOVERNANCE_PROFILE_MISSING';
    public const CODE_PROFILE_INVALID = 'MILPA_GOVERNANCE_PROFILE_INVALID';
    public const CODE_DECISION_NOT_FOUND = 'MILPA_GOVERNANCE_DECISION_NOT_FOUND';
    public const CODE_DECISION_DUPLICATE = 'MILPA_GOVERNANCE_DECISION_DUPLICATE';
    public const CODE_IMMUTABILITY_VIOLATION = 'MILPA_GOVERNANCE_DECISION_IMMUTABILITY_VIOLATION';
    public const CODE_SUPERSESSION_CYCLE = 'MILPA_GOVERNANCE_SUPERSESSION_CYCLE';
    public const CODE_DECISION_INACTIVE = 'MILPA_GOVERNANCE_DECISION_INACTIVE';
    public const CODE_PLAN_STALE = 'MILPA_GOVERNANCE_PLAN_STALE';
    public const CODE_ENFORCEMENT_UNPROVEN = 'MILPA_GOVERNANCE_ENFORCEMENT_UNPROVEN';
    public const CODE_RUN_STALE = 'MILPA_GOVERNANCE_RUN_STALE';
    public const CODE_RUN_INVALID = 'MILPA_GOVERNANCE_RUN_INVALID';
    public const CODE_HISTORY_PLAN_MISMATCH = 'MILPA_GOVERNANCE_HISTORY_PLAN_MISMATCH';
    public const CODE_SNAPSHOT_MISSING = 'MILPA_GOVERNANCE_SNAPSHOT_MISSING';
    public const CODE_SNAPSHOT_INVALID = 'MILPA_GOVERNANCE_SNAPSHOT_INVALID';

    /**
     * The governance repository has no registered profile.
     *
     * @param string $context Description of where the access was attempted
     */
    public static function profileMissing(string $context): self
    {
        return new self(
            '[' . self::CODE_PROFILE_MISSING . '] No governance profile is registered in the repository. '
            . "Context: {$context}. "
            . 'Fix: make sure the host has loaded the profile before compiling the plan. '
            . 'Blocked: conformance validation, plan compilation.',
        );
    }

    /**
     * The loaded profile has an invalid structure or content.
     *
     * @param string $reason Specific description of what is invalid
     */
    public static function profileInvalid(string $reason): self
    {
        return new self(
            '[' . self::CODE_PROFILE_INVALID . '] The governance profile is invalid: ' . $reason . '. '
            . 'Rules: schemaVersion, name, version must not be empty; decisionIds and gates must be arrays. '
            . 'Fix: check the profile file and correct the structure. '
            . 'Blocked: plan compilation, decision validation.',
        );
    }

    /**
     * An attempt was made to access a decision that does not exist in the profile.
     *
     * @param string $decisionId ID of the decision sought
     */
    public static function decisionNotFound(string $decisionId): self
    {
        return new self(
            '[' . self::CODE_DECISION_NOT_FOUND . "] Decision '{$decisionId}' is not found in the profile. "
            . 'Fix: verify that the ID is correct and that the decision is registered in the profile. '
            . "Blocked: any rule that refers to '{$decisionId}'.",
        );
    }

    /**
     * The profile has two decisions with the same ID.
     *
     * @param string $decisionId Duplicated ID
     */
    public static function decisionDuplicate(string $decisionId): self
    {
        return new self(
            '[' . self::CODE_DECISION_DUPLICATE . "] Decision '{$decisionId}' appears more than once in the profile. "
            . 'Each decision needs a unique ID. '
            . 'Fix: remove or rename one of the duplicates. '
            . "Blocked: plan compilation, traceability of '{$decisionId}'.",
        );
    }

    /**
     * An attempt was made to modify a decision that is already in effect (inviolable).
     *
     * @param string $decisionId ID of the decision that cannot be modified
     */
    public static function immutabilityViolation(string $decisionId): self
    {
        return new self(
            '[' . self::CODE_IMMUTABILITY_VIOLATION . "] Decision '{$decisionId}' cannot be modified: "
            . 'it is already in effect and immutable. '
            . 'Fix: if you need to change it, create a new decision that supersedes it. '
            . "Blocked: any change to '{$decisionId}'.",
        );
    }

    /**
     * There is a cycle in the supersession relationships between decisions.
     *
     * @param array<string> $cycle List of IDs that form the cycle
     */
    public static function supersessionCycle(array $cycle): self
    {
        $cycleStr = implode(' → ', $cycle);
        return new self(
            '[' . self::CODE_SUPERSESSION_CYCLE . '] Cycle detected in supersession: ' . $cycleStr . '. '
            . 'A decision cannot (directly or indirectly) supersede itself. '
            . 'Fix: review the supersession chain and remove the circular reference. '
            . 'Blocked: plan compilation.',
        );
    }

    /**
     * An attempt was made to use a decision that is not in "accepted" status.
     *
     * @param string $decisionId    ID of the decision
     * @param string $currentStatus Current status it is in
     */
    public static function decisionInactive(string $decisionId, string $currentStatus): self
    {
        return new self(
            '[' . self::CODE_DECISION_INACTIVE . "] Decision '{$decisionId}' is in status '{$currentStatus}', "
            . 'not "accepted". Only accepted decisions govern the system. '
            . 'Fix: wait for the decision to be accepted or choose one that already is. '
            . "Blocked: enforcement of '{$decisionId}''s rules.",
        );
    }

    /**
     * The compiled plan does not match the current profile (stale plan).
     *
     * @param string $expectedHash Expected hash of the current profile
     * @param string $planHash     Hash of the profile the plan was compiled with
     */
    public static function planStale(string $expectedHash, string $planHash): self
    {
        return new self(
            '[' . self::CODE_PLAN_STALE . '] The plan is stale: the profile changed since it was compiled. '
            . "Expected hash: {$expectedHash}, plan hash: {$planHash}. "
            . 'Fix: recompile the plan by running the governance compiler. '
            . 'Blocked: conformance validation until recompiled.',
        );
    }

    /**
     * An enforcement tier is claimed with no evidence of execution.
     *
     * @param string $gateId ID of the gate or rule
     * @param string $tier   Claimed enforcement tier
     */
    public static function enforcementUnproven(string $gateId, string $tier): self
    {
        return new self(
            '[' . self::CODE_ENFORCEMENT_UNPROVEN . "] There is no evidence that '{$gateId}' is enforced at tier '{$tier}'. "
            . 'Fix: implement the gate in the pipeline, make sure it runs, and record the result. '
            . "Blocked: conformance certification for tier '{$tier}'.",
        );
    }

    /**
     * The most recent governance run observed a plan other than the current one
     * (`profileHash`/`planHash` do not match) — it is evidence of another law, not this one.
     *
     * @param string $reason Specific reason returned by `GovernanceRunProjector::observe()`
     */
    public static function runStale(string $reason): self
    {
        return new self(
            '[' . self::CODE_RUN_STALE . '] The available evidence is stale: ' . $reason . '. '
            . 'Fix: recompile the current plan and rerun governance against that exact plan. '
            . 'Blocked: conformance certification until a CURRENT run exists.',
        );
    }

    /**
     * The governance run is internally inconsistent (empty runId, inverted
     * dates, duplicate/unknown gate, PASSED without producerRef, WAIVED without
     * justification, or boundTo that redefines the gate's law) — it is not reliable evidence.
     *
     * @param string $reason Specific reason naming the offender, returned by
     *                       `GovernanceRunProjector::observe()`
     */
    public static function runInvalid(string $reason): self
    {
        return new self(
            '[' . self::CODE_RUN_INVALID . '] The governance run is invalid: ' . $reason . '. '
            . 'Fix: correct the evidence producer so it emits a consistent run '
            . '(unique runId, coherent dates, one record per known gate, PASSED with producerRef, '
            . 'WAIVED with justification, boundTo that respects the plan\'s law). '
            . 'Blocked: conformance certification until a valid run exists.',
        );
    }

    /**
     * A request was made to derive the health status (`status`) by consulting a history built for
     * a plan other than the current one — evidence of another law. This is NOT re-adjudicating runs
     * (that was done by `observe()`); it is verifying that the consulted history belongs to THIS plan.
     *
     * @param string $planHash    Hash of the current plan the status was requested against
     * @param string $historyHash Hash of the plan the received history belongs to
     */
    public static function historyPlanMismatch(string $planHash, string $historyHash): self
    {
        return new self(
            '[' . self::CODE_HISTORY_PLAN_MISMATCH . '] The consulted history belongs to another plan: '
            . "the current plan is '{$planHash}', but the history was built for '{$historyHash}'. "
            . 'A health status cannot be derived from the history of another law. '
            . 'Fix: build the RunHistory with the same plan you pass to the status projector '
            . '(GovernanceHistoryProjector::project() seals it with plan.planHash). '
            . 'Blocked: the status report (coa:governance status).',
        );
    }

    /**
     * The compiled snapshot (`snapshots/governance-plan.json`) does not exist on disk.
     *
     * @param string $path Absolute path of the expected snapshot
     */
    public static function snapshotMissing(string $path): self
    {
        return new self(
            '[' . self::CODE_SNAPSHOT_MISSING . "] The compiled snapshot 'snapshots/governance-plan.json' does not exist at '{$path}'. "
            . 'Fix: regenerate it by running coa:governance compile (or RepoGovernanceManifest::generate()). '
            . 'Blocked: integrity verification, external consumption of the contract.',
        );
    }

    /**
     * The compiled snapshot exists but could not be read.
     *
     * @param string $reason Specific description of what failed while reading it
     */
    public static function snapshotInvalid(string $reason): self
    {
        return new self(
            '[' . self::CODE_SNAPSHOT_INVALID . '] The snapshot could not be read: ' . $reason . '. '
            . 'Fix: regenerate it by running coa:governance compile. '
            . 'Blocked: integrity verification.',
        );
    }
}
