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

use Milpa\Governance\GateOutcome;
use Milpa\Governance\GateOutcomeRecord;
use Milpa\Governance\GovernanceRun;
use Milpa\Governance\InvocationIdentity;
use Milpa\Governance\ProducerIdentity;
use PHPUnit\Framework\TestCase;

final class EvidenceVocabularyTest extends TestCase
{
    public function test_gate_outcome_semantics(): void
    {
        self::assertTrue(GateOutcome::PASSED->isSatisfied());
        self::assertTrue(GateOutcome::WAIVED->isSatisfied());
        self::assertFalse(GateOutcome::FAILED->isSatisfied());
        self::assertFalse(GateOutcome::PENDING->isSatisfied());
        self::assertTrue(GateOutcome::FAILED->isFinal());
        self::assertFalse(GateOutcome::PENDING->isFinal());
    }

    public function test_invocation_identity_rejects_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InvocationIdentity('', 'rodrigo');
    }

    public function test_run_serializes_deterministically(): void
    {
        $run = new GovernanceRun(
            'milpa-governance-run/v1',
            'run-1',
            'ph',
            'plh',
            new InvocationIdentity('human', 'rodrigo'),
            new ProducerIdentity('ci-check', 'scripts/ci-check.php', null),
            'local',
            new \DateTimeImmutable('2026-07-16T10:00:00+00:00'),
            new \DateTimeImmutable('2026-07-16T10:01:00+00:00'),
            ['php' => '8.3'],
            [new GateOutcomeRecord('phpstan', GateOutcome::PASSED, 'ci:quality:phpstan', 'ci-check:step-3')],
        );
        $a = $run->toArray();
        self::assertSame('milpa-governance-run/v1', $a['schemaVersion']);
        self::assertSame('2026-07-16T10:00:00+00:00', $a['startedAt']);
        self::assertSame('passed', $a['outcomes'][0]['outcome']);
        self::assertSame('ci-check:step-3', $a['outcomes'][0]['producerRef']);
        self::assertNull($a['outcomes'][0]['justification']);
    }
}
