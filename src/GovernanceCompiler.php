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
 * Compiles decisions + profile (already validated) into a deterministic GovernancePlan.
 *
 * Loading doctrine (Amendment 2 of ADR#14): supersession is DERIVED, the past is never
 * mutated. ADR files are `accepted` byte-identical forever; a decision ending up
 * `superseded` is a CALCULATED effect in the plan (`effectiveStatus`), never a change
 * in the input decision (`declaredStatus`, which reflects the file as-is, immutable).
 *
 * The same Amendment 2 requires the plan to reflect ONLY accepted governance. That is enforced in
 * code with two criteria (see `entersPlan()` / `isActive()`), a single source of truth
 * used uniformly in `compileDecisions()`, `compileRules()`, `compileGates()`,
 * `compileArtifacts()`, and `indexSupersededBy()`:
 *
 * - **Enters the plan** (`entersPlan`): `status === Accepted`. A `superseded` decision has
 *   `status === Accepted` in its file (the file never changes) — that's why it DOES enter, with its
 *   `effectiveStatus` derived to `superseded` to show the lineage honestly.
 *   `rejected`/`proposed`/`deprecated` NEVER enter: neither as a `decisions` entry, nor do their
 *   gates/artifacts declared via policies govern anything.
 * - **Active** (`isActive`): enters the plan AND is not superseded. Only active decisions
 *   generate `rules`, and their `kind:gate`/`kind:artifact` policies flow into `gates`/`artifacts` —
 *   a superseded decision still appears in `plan.decisions` for traceability, but no longer
 *   governs (the successor replaced it).
 *
 * `$decisions` is the full corpus known to the compiler (includes historical lineage:
 * already-superseded decisions, or even rejected/proposed ones that never governed, needed
 * to derive `supersededBy` and so the plan can honestly show its history).
 * `$profile->decisionIds` is, instead, the set of CURRENT decisions the profile
 * declares — `validate()` (the T2 wall) rejects any profile that directly references
 * an already-superseded decision, even when the one that supersedes it is also listed. That's why the
 * plan's `decisions`/`gates`/`artifacts` sections are derived from ALL of `$decisions` (the full
 * lineage, filtered by the criteria above), not just from `$profile->decisionIds` — so the
 * plan can honestly show "ADR-0004 was superseded by ADR-0014" without the profile
 * having to break the wall.
 */
final class GovernanceCompiler
{
    /**
     * Schema version of the compiled plan. Single source of truth — T5/T7 will align
     * the JSON schema file with this value.
     */
    public const string SCHEMA_VERSION = 'milpa-governance-plan/v1';

    public function __construct(
        private readonly GovernanceValidator $validator,
    ) {
    }

    /**
     * Validates and compiles a deterministic governance plan.
     *
     * @param list<GovernanceDecision> $decisions Full corpus of known decisions
     *                                            (includes historical superseded lineage and
     *                                            rejected/proposed decisions that never governed)
     *
     * @throws GovernanceException if `$decisions`/`$profile` do not form a coherent
     *                             and honest contract (propagated unwrapped from `validate()`), or if
     *                             a `kind:gate`/`kind:artifact` policy declares an unrecognized
     *                             tier
     */
    public function compile(array $decisions, GovernanceProfile $profile): GovernancePlan
    {
        $this->validator->validate($decisions, $profile);

        $decisionsById = $this->indexById($decisions);
        $ids = array_keys($decisionsById);
        sort($ids, SORT_STRING);

        $supersededBy = $this->indexSupersededBy($decisions);

        $profileHash = $this->computeProfileHash($decisionsById, $ids, $profile);
        $decisionEntries = $this->compileDecisions($decisionsById, $ids, $supersededBy);
        $rules = $this->compileRules($decisionsById, $ids, $supersededBy);
        $gates = $this->compileGates($decisions, $supersededBy, $profile);
        $artifacts = $this->compileArtifacts($decisions, $supersededBy, $profile);

        $planHash = $this->computePlanHash(
            self::SCHEMA_VERSION,
            $profile,
            $profileHash,
            $decisionEntries,
            $rules,
            $gates,
            $artifacts,
        );

        return new GovernancePlan(
            self::SCHEMA_VERSION,
            $profile->name,
            $profile->version,
            $profileHash,
            $decisionEntries,
            $rules,
            $gates,
            $artifacts,
            $profile->actors,
            [],
            $planHash,
        );
    }

    /**
     * "Enters the plan" criterion (Amendment 2): only `status === Accepted`. A
     * `rejected`/`proposed`/`deprecated` decision never governed and does not appear in the plan in
     * any form — neither as a `decisions` entry, nor as a source of gates/artifacts.
     */
    private function entersPlan(GovernanceDecision $decision): bool
    {
        return $decision->status === DecisionStatus::Accepted;
    }

    /**
     * "Active" criterion (Amendment 2): enters the plan AND is not superseded. It is the only
     * criterion that generates `rules` and enables a decision's `kind:gate`/`kind:artifact`
     * policies to govern — a superseded decision still appears in `plan.decisions` (lineage), but
     * no longer governs: the successor replaced it.
     *
     * @param array<string, string> $supersededBy
     */
    private function isActive(GovernanceDecision $decision, array $supersededBy): bool
    {
        return $this->entersPlan($decision) && !isset($supersededBy[$decision->id]);
    }

    /**
     * @param list<GovernanceDecision> $decisions
     *
     * @return array<string, GovernanceDecision>
     */
    private function indexById(array $decisions): array
    {
        $decisionsById = [];
        foreach ($decisions as $decision) {
            $decisionsById[$decision->id] = $decision;
        }

        return $decisionsById;
    }

    /**
     * Supersession index: `$supersededBy[<oldId>] = <newId>`. Only registered when
     * the superseding decision is itself `Accepted` — a rejected or proposed decision
     * never governed, so its claim of "I supersede X" is void and cannot derive
     * anyone's status.
     *
     * @param list<GovernanceDecision> $decisions
     *
     * @return array<string, string>
     */
    private function indexSupersededBy(array $decisions): array
    {
        $supersededBy = [];
        foreach ($decisions as $decision) {
            if ($decision->supersedes !== null && $decision->status === DecisionStatus::Accepted) {
                $supersededBy[$decision->supersedes] = $decision->id;
            }
        }

        return $supersededBy;
    }

    /**
     * @param array<string, GovernanceDecision> $decisionsById
     * @param array<string>                     $ids           ids sorted (ksort) from `$decisionsById`
     * @param array<string, string>             $supersededBy
     *
     * @return array<int, array{id: string, title: string, declaredStatus: string, effectiveStatus: string, supersededBy: ?string, contentHash: string}>
     */
    private function compileDecisions(array $decisionsById, array $ids, array $supersededBy): array
    {
        $entries = [];
        foreach ($ids as $id) {
            $decision = $decisionsById[$id];
            if (!$this->entersPlan($decision)) {
                continue;
            }

            $newId = $supersededBy[$id] ?? null;

            $entries[] = [
                'id' => $decision->id,
                'title' => $decision->title,
                'declaredStatus' => $decision->status->value,
                'effectiveStatus' => $newId !== null ? DecisionStatus::Superseded->value : $decision->status->value,
                'supersededBy' => $newId,
                'contentHash' => $decision->contentHash,
            ];
        }

        return $entries;
    }

    /**
     * One GovernanceRule per active decision (see `isActive()`); superseded/rejected/
     * proposed/deprecated ones generate no rule.
     *
     * @param array<string, GovernanceDecision> $decisionsById
     * @param array<string>                     $ids           ids sorted (ksort) from `$decisionsById`
     * @param array<string, string>             $supersededBy
     *
     * @return array<GovernanceRule>
     */
    private function compileRules(array $decisionsById, array $ids, array $supersededBy): array
    {
        $rules = [];
        foreach ($ids as $id) {
            $decision = $decisionsById[$id];

            if (!$this->isActive($decision, $supersededBy)) {
                continue;
            }

            $rules[] = new GovernanceRule("rule:{$id}", $this->ruleStatement($decision), $id);
        }

        return $rules;
    }

    /**
     * The rule's statement: the explicit phrase from a `kind:rule` policy if it exists,
     * or the decision's title otherwise.
     */
    private function ruleStatement(GovernanceDecision $decision): string
    {
        foreach ($decision->policies as $policy) {
            if (is_array($policy) && ($policy['kind'] ?? null) === 'rule' && isset($policy['statement'])) {
                return (string) $policy['statement'];
            }
        }

        return $decision->title;
    }

    /**
     * `$profile->gates` (the profile's explicit contract, all of them included) + the gates
     * declared as `kind:gate` policies of ACTIVE decisions (see `isActive()`) — a
     * superseded/rejected/proposed/deprecated decision does not govern, so its `kind:gate`
     * policies produce no gate in the plan. Dedupe by id (first occurrence wins), ksort
     * by id. The `outcome` of EVERY gate in the plan is always null — Amendment 3, uniform for
     * both the profile's gates and the ones derived from policy.
     *
     * @param list<GovernanceDecision> $decisions
     * @param array<string, string>    $supersededBy
     *
     * @throws GovernanceException if a `kind:gate` policy declares an unrecognized tier
     *
     * @return array<GovernanceGate>
     */
    private function compileGates(array $decisions, array $supersededBy, GovernanceProfile $profile): array
    {
        /** @var array<string, GovernanceGate> $gatesById */
        $gatesById = [];
        foreach ($profile->gates as $gate) {
            $gatesById[$gate->id] ??= new GovernanceGate($gate->id, $gate->description, $gate->tier, $gate->boundTo, null);
        }

        foreach ($decisions as $decision) {
            if (!$this->isActive($decision, $supersededBy)) {
                continue;
            }

            foreach ($decision->policies as $policy) {
                if (!is_array($policy) || ($policy['kind'] ?? null) !== 'gate') {
                    continue;
                }

                $gateId = (string) ($policy['id'] ?? $decision->id);
                if (isset($gatesById[$gateId])) {
                    continue;
                }

                $rawTier = (string) ($policy['tier'] ?? '');
                $tier = EnforcementTier::tryFrom($rawTier);
                if ($tier === null) {
                    throw GovernanceException::profileInvalid(
                        "gate '{$gateId}' (derived from decision '{$decision->id}''s policy) declares an "
                        . "unrecognized tier '{$rawTier}' — valid tiers are: "
                        . implode(', ', array_map(static fn (EnforcementTier $t): string => $t->value, EnforcementTier::cases())),
                    );
                }

                $boundTo = $policy['boundTo'] ?? null;
                $description = isset($policy['description']) ? (string) $policy['description'] : $decision->title;

                $gatesById[$gateId] = new GovernanceGate(
                    $gateId,
                    $description,
                    $tier,
                    is_string($boundTo) ? $boundTo : null,
                    null,
                );
            }
        }

        $ids = array_keys($gatesById);
        sort($ids, SORT_STRING);

        return array_map(static fn (string $id): GovernanceGate => $gatesById[$id], $ids);
    }

    /**
     * `$profile->artifacts` (the profile's explicit contract, all of them included) + the
     * artifacts declared as `kind:artifact` policies of ACTIVE decisions (see
     * `isActive()`). Dedupe by id (first occurrence wins), ksort by id.
     *
     * @param list<GovernanceDecision> $decisions
     * @param array<string, string>    $supersededBy
     *
     * @throws GovernanceException if a `kind:artifact` policy declares an unrecognized tier
     *
     * @return array<RequiredArtifact>
     */
    private function compileArtifacts(array $decisions, array $supersededBy, GovernanceProfile $profile): array
    {
        /** @var array<string, RequiredArtifact> $artifactsById */
        $artifactsById = [];
        foreach ($profile->artifacts as $artifact) {
            $artifactsById[$artifact->id] ??= $artifact;
        }

        foreach ($decisions as $decision) {
            if (!$this->isActive($decision, $supersededBy)) {
                continue;
            }

            foreach ($decision->policies as $policy) {
                if (!is_array($policy) || ($policy['kind'] ?? null) !== 'artifact') {
                    continue;
                }

                $artifactId = (string) ($policy['id'] ?? $decision->id);
                if (isset($artifactsById[$artifactId])) {
                    continue;
                }

                $rawTier = (string) ($policy['tier'] ?? '');
                $tier = EnforcementTier::tryFrom($rawTier);
                if ($tier === null) {
                    throw GovernanceException::profileInvalid(
                        "artifact '{$artifactId}' (derived from decision '{$decision->id}''s policy) declares "
                        . "an unrecognized tier '{$rawTier}' — valid tiers are: "
                        . implode(', ', array_map(static fn (EnforcementTier $t): string => $t->value, EnforcementTier::cases())),
                    );
                }

                $artifactsById[$artifactId] = new RequiredArtifact($artifactId, $tier);
            }
        }

        $ids = array_keys($artifactsById);
        sort($ids, SORT_STRING);

        return array_map(static fn (string $id): RequiredArtifact => $artifactsById[$id], $ids);
    }

    /**
     * Deterministic integrity hash: no timestamps and no non-deterministic insertion order.
     *
     * @param array<string, GovernanceDecision> $decisionsById
     * @param array<string>                     $ids           ids sorted (ksort) from `$decisionsById`
     */
    private function computeProfileHash(array $decisionsById, array $ids, GovernanceProfile $profile): string
    {
        $decisionHashes = [];
        foreach ($ids as $id) {
            $decisionHashes[$id] = $decisionsById[$id]->contentHash;
        }

        $sortedDecisionIds = $profile->decisionIds;
        sort($sortedDecisionIds, SORT_STRING);

        $sortedGates = $profile->gates;
        usort($sortedGates, static fn (GovernanceGate $a, GovernanceGate $b): int => $a->id <=> $b->id);

        $sortedArtifacts = $profile->artifacts;
        usort($sortedArtifacts, static fn (RequiredArtifact $a, RequiredArtifact $b): int => $a->id <=> $b->id);

        $canonical = [
            'decisions' => $decisionHashes,
            'profile' => [
                'schemaVersion' => $profile->schemaVersion,
                'name' => $profile->name,
                'version' => $profile->version,
                'decisionIds' => $sortedDecisionIds,
                'gates' => array_map(static fn (GovernanceGate $g): array => $g->toArray(), $sortedGates),
                'artifacts' => array_map(static fn (RequiredArtifact $a): array => $a->toArray(), $sortedArtifacts),
                'actors' => $profile->actors,
            ],
        ];

        return hash('sha256', (string) json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    /**
     * Deterministic integrity hash of the complete plan-as-law: the link that binds a
     * `GovernanceRun` (the evidence) to this EXACT `GovernancePlan` — protects against a
     * different recompilation of the same profile.
     *
     * FROZEN canonicalization — it must never change without breaking every `planHash` already
     * issued: excludes `planHash` (it is computed before building the plan, it cannot self-reference)
     * and every gate `outcome` (Amendment 3: always null in the pure plan, irrelevant for
     * identifying the plan-as-law); recursive ksort ONLY of associative arrays — the lists
     * (`decisions`/`gates`/`rules`/`artifacts`, already sorted by id upstream) preserve their
     * order; `json_encode` without pretty-print, with fixed flags; `hash('sha256', ...)`.
     *
     * Reuses the `profileHash` already computed by `computeProfileHash()` — does not recompute it.
     *
     * @param array<mixed>            $decisions already sorted by id (`compileDecisions()`)
     * @param array<GovernanceRule>   $rules     already sorted by id (`compileRules()`)
     * @param array<GovernanceGate>   $gates     already sorted by id (`compileGates()`)
     * @param array<RequiredArtifact> $artifacts already sorted by id (`compileArtifacts()`)
     */
    private function computePlanHash(
        string $schemaVersion,
        GovernanceProfile $profile,
        string $profileHash,
        array $decisions,
        array $rules,
        array $gates,
        array $artifacts,
    ): string {
        $canonical = [
            'schemaVersion' => $schemaVersion,
            'profileName' => $profile->name,
            'profileVersion' => $profile->version,
            'profileHash' => $profileHash,
            'decisions' => $decisions,
            'gates' => array_map(static function (GovernanceGate $g): array {
                $a = $g->toArray();
                unset($a['outcome']); // outcome EXCLUDED from the hash — Amendment 3

                return $a;
            }, $gates),
            'rules' => array_map(static fn (GovernanceRule $r): array => $r->toArray(), $rules),
            'artifacts' => array_map(static fn (RequiredArtifact $a): array => $a->toArray(), $artifacts),
            'actors' => $profile->actors,
        ];

        $this->ksortRecursive($canonical);

        return hash(
            'sha256',
            (string) json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Sorts by key ONLY associative arrays — lists (keys `0..n` consecutive in their
     * natural order) preserve the input order, which is already deterministic upstream.
     *
     * @param array<mixed> $arr
     */
    private function ksortRecursive(array &$arr): void
    {
        foreach ($arr as &$v) {
            if (is_array($v)) {
                $this->ksortRecursive($v);
            }
        }
        unset($v);

        if ($arr !== [] && array_keys($arr) !== range(0, count($arr) - 1)) {
            ksort($arr);
        }
    }
}
