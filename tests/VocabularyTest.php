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
use Milpa\Governance\GovernanceGate;
use Milpa\Governance\GovernanceRule;
use Milpa\Governance\RequiredArtifact;
use Milpa\Governance\GovernancePlan;
use PHPUnit\Framework\TestCase;

final class VocabularyTest extends TestCase
{
    public function test_enforcement_tier_has_the_four_frozen_values(): void
    {
        self::assertSame(
            ['enforced', 'advisory', 'convention', 'deferred'],
            array_map(fn (EnforcementTier $t) => $t->value, EnforcementTier::cases())
        );
    }

    public function test_gate_serializes_with_null_outcome_by_default(): void
    {
        $g = new GovernanceGate('phpstan', 'PHPStan level 6', EnforcementTier::Enforced, 'ci:quality:phpstan');
        self::assertSame(
            ['id' => 'phpstan', 'description' => 'PHPStan level 6', 'tier' => 'enforced', 'boundTo' => 'ci:quality:phpstan', 'outcome' => null],
            $g->toArray()
        );
    }

    public function test_decision_status_covers_the_lifecycle(): void
    {
        self::assertSame(
            ['proposed', 'accepted', 'rejected', 'superseded', 'deprecated'],
            array_map(fn (DecisionStatus $s) => $s->value, DecisionStatus::cases())
        );
    }

    public function test_plan_toArray_is_the_frozen_shape(): void
    {
        $plan = new GovernancePlan(
            '0.1',
            'milpa-framework',
            '2026.07.1',
            'abc',
            decisions: [['id' => 'ADR-0004', 'title' => 't', 'declaredStatus' => 'accepted', 'effectiveStatus' => 'accepted', 'supersededBy' => null, 'contentHash' => 'h']],
            rules: [new GovernanceRule('r1', 'phrase', 'ADR-0004')],
            gates: [new GovernanceGate('phpstan', 'd', EnforcementTier::Enforced, 'ci:quality:phpstan')],
            artifacts: [new RequiredArtifact('spec', EnforcementTier::Convention)],
            actors: [['id' => 'architect', 'authorizedTo' => ['design']]],
            assumptions: ['x'],
            planHash: 'plan-hash'
        );
        $out = $plan->toArray();
        self::assertSame('0.1', $out['schemaVersion']);
        self::assertSame('abc', $out['profileHash']);
        self::assertSame('plan-hash', $out['planHash']);
        self::assertSame('enforced', $out['gates'][0]['tier']);
        self::assertNull($out['gates'][0]['outcome']);
    }
}
