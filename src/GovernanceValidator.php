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
 * The wall: makes it impossible to declare a gate "enforced" without a real mechanism backing it.
 *
 * Validates in fail-fast mode — throws on the first violation found, in input order:
 * (a) unique decision IDs; (b) every profile reference points to an existing decision;
 * (c) every `supersedes` points to an existing decision; (d) no supersession cycles;
 * (e) no decision referenced by the profile is rejected or superseded (directly or
 * derivedly); (f) every gate with tier Enforced has a real `boundTo` belonging to
 * the allowlist — both the profile's typed `GovernanceGate`s and the gates declared as
 * `policies` of kind `kind: 'gate'` within each decision. For the latter, a tier that does not
 * resolve to a valid `EnforcementTier` case (typo, different casing, or absent) fails closed
 * with `CODE_PROFILE_INVALID` — it is not treated as innocent free-form metadata.
 *
 * "enforced with no demonstrable mechanism is a governance lie."
 */
final class GovernanceValidator
{
    /**
     * Frozen allowlist of enforcement mechanisms recognized by the framework.
     *
     * An Enforced gate is only honest if its `boundTo` points to one of these mechanisms
     * (or to the set injected by the host if this default is replaced).
     *
     * @var array<string>
     */
    public const array DEFAULT_BOUND_TO = [
        'ci:validate:syntax',
        'ci:validate:composer',
        'ci:quality:phpstan',
        'ci:quality:code-style',
        'ci:quality:templates',
        'ci:test:unit',
        'ci:test:integration',
        'ci:security:audit',
        'ci:governance',
        'ci:governance:authenticity',
        'sembrador:pre-commit',
        'sembrador:commit-msg',
        'sembrador:pre-push',
        'sembrador:allowlist',
        'sembrador:managed-clone',
        'runtime:PolicyGate',
        'runtime:SelfApprovalException',
        'runtime:GateDefinition.block',
    ];

    /**
     * @param array<string> $validBoundTo Allowlist of accepted enforcement mechanisms
     */
    public function __construct(
        private readonly array $validBoundTo,
    ) {
    }

    /**
     * Validates that the decisions and profile form a coherent, honest governance contract.
     *
     * @param list<GovernanceDecision> $decisions
     *
     * @throws GovernanceException on the first violation found
     */
    public function validate(array $decisions, GovernanceProfile $profile): void
    {
        $decisionsById = $this->indexByIdOrFail($decisions);

        foreach ($profile->decisionIds as $decisionId) {
            if (!isset($decisionsById[$decisionId])) {
                throw GovernanceException::decisionNotFound($decisionId);
            }
        }

        foreach ($decisionsById as $decision) {
            if ($decision->supersedes !== null && !isset($decisionsById[$decision->supersedes])) {
                throw GovernanceException::decisionNotFound($decision->supersedes);
            }
        }

        $this->assertNoSupersessionCycles($decisionsById);

        $supersededIds = $this->derivedSupersededIds($decisionsById);

        foreach ($profile->decisionIds as $decisionId) {
            $decision = $decisionsById[$decisionId];
            if ($decision->status === DecisionStatus::Rejected || $decision->status === DecisionStatus::Superseded) {
                throw GovernanceException::decisionInactive($decisionId, $decision->status->value);
            }
            if (isset($supersededIds[$decisionId])) {
                throw GovernanceException::decisionInactive($decisionId, DecisionStatus::Superseded->value);
            }
        }

        foreach ($profile->gates as $gate) {
            $this->assertEnforcementProven($gate->id, $gate->tier, $gate->boundTo);
        }

        foreach ($decisionsById as $decision) {
            foreach ($decision->policies as $policy) {
                if (!is_array($policy) || ($policy['kind'] ?? null) !== 'gate') {
                    continue;
                }

                $gateId = (string) ($policy['id'] ?? $decision->id);
                $rawTier = (string) ($policy['tier'] ?? '');
                $tier = EnforcementTier::tryFrom($rawTier);

                if ($tier === null) {
                    // Fail-closed: a policy that self-declares `kind: 'gate'` but whose tier does not
                    // resolve to a valid EnforcementTier case (typo, different casing, or
                    // absent) is a malformed governance declaration — not free-form metadata.
                    throw GovernanceException::profileInvalid(
                        "gate '{$gateId}' declares an unrecognized tier '{$rawTier}' — valid tiers are: "
                        . implode(', ', array_map(static fn (EnforcementTier $t) => $t->value, EnforcementTier::cases())),
                    );
                }

                $boundTo = $policy['boundTo'] ?? null;

                $this->assertEnforcementProven($gateId, $tier, is_string($boundTo) ? $boundTo : null);
            }
        }
    }

    /**
     * Indexes the decisions by ID, in input order, or throws on the first duplicate.
     *
     * @param list<GovernanceDecision> $decisions
     *
     * @return array<string, GovernanceDecision>
     */
    private function indexByIdOrFail(array $decisions): array
    {
        $decisionsById = [];
        foreach ($decisions as $decision) {
            if (isset($decisionsById[$decision->id])) {
                throw GovernanceException::decisionDuplicate($decision->id);
            }
            $decisionsById[$decision->id] = $decision;
        }

        return $decisionsById;
    }

    /**
     * Walks the supersession graph (a single outgoing edge per decision, via `supersedes`)
     * looking for cycles, with a deterministic DFS in input order.
     *
     * @param array<string, GovernanceDecision> $decisionsById
     */
    private function assertNoSupersessionCycles(array $decisionsById): void
    {
        $globallyVisited = [];

        foreach ($decisionsById as $start) {
            if (isset($globallyVisited[$start->id])) {
                continue;
            }

            /** @var array<string, true> $path */
            $path = [];
            $current = $start;

            while (true) {
                if (isset($path[$current->id])) {
                    throw GovernanceException::supersessionCycle($this->cycleFrom($path, $current->id));
                }
                if (isset($globallyVisited[$current->id])) {
                    break;
                }

                $path[$current->id] = true;

                if ($current->supersedes === null || !isset($decisionsById[$current->supersedes])) {
                    break;
                }

                $current = $decisionsById[$current->supersedes];
            }

            foreach (array_keys($path) as $visitedId) {
                $globallyVisited[$visitedId] = true;
            }
        }
    }

    /**
     * Builds the cycle list, starting at `$closingId` and closing back onto it.
     *
     * @param array<string, true> $path
     *
     * @return array<string>
     */
    private function cycleFrom(array $path, string $closingId): array
    {
        $ids = array_keys($path);
        $startIndex = array_search($closingId, $ids, true);
        $cycle = array_slice($ids, $startIndex === false ? 0 : $startIndex);
        $cycle[] = $closingId;

        return $cycle;
    }

    /**
     * IDs of decisions that end up superseded by derived effect (another decision supersedes them).
     *
     * @param array<string, GovernanceDecision> $decisionsById
     *
     * @return array<string, true>
     */
    private function derivedSupersededIds(array $decisionsById): array
    {
        $supersededIds = [];
        foreach ($decisionsById as $decision) {
            if ($decision->supersedes !== null) {
                $supersededIds[$decision->supersedes] = true;
            }
        }

        return $supersededIds;
    }

    /**
     * The wall: an Enforced gate without a real `boundTo` belonging to the allowlist is a
     * governance lie.
     */
    private function assertEnforcementProven(string $gateId, EnforcementTier $tier, ?string $boundTo): void
    {
        if ($tier !== EnforcementTier::Enforced) {
            return;
        }

        if ($boundTo === null || !in_array($boundTo, $this->validBoundTo, true)) {
            throw GovernanceException::enforcementUnproven($gateId, $tier->value);
        }
    }
}
