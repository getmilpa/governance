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

use Milpa\Governance\GovernanceManifest;
use PHPUnit\Framework\TestCase;

/**
 * The pure manifest (arrays in, arrays out): {@see GovernanceManifest::build()} sorts by
 * id (ksort) before hashing, so the resulting manifest is identical regardless of the
 * order the decisions arrived in — the same principle that {@see \Milpa\Plugin\LockFileManager}
 * applies to `milpa.lock`. {@see GovernanceManifest::verifyIntegrity()} confirms that a manifest
 * is in sync with the current hashes; {@see GovernanceManifest::immutabilityViolations()}
 * confirms that no decision accepted in a base manifest disappeared or changed in a current one
 * (Amendment 1B) — new IDs are allowed.
 */
final class GovernanceManifestTest extends TestCase
{
    public function test_build_is_deterministic_regardless_of_insertion_order(): void
    {
        $orderA = ['ADR-0005' => 'hash-5', 'ADR-0004' => 'hash-4', 'ADR-0014' => 'hash-14'];
        $orderB = ['ADR-0014' => 'hash-14', 'ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5'];

        $manifestA = GovernanceManifest::build($orderA, 'snap-hash');
        $manifestB = GovernanceManifest::build($orderB, 'snap-hash');

        self::assertSame($manifestA, $manifestB);
        self::assertSame(GovernanceManifest::SCHEMA_VERSION, $manifestA['schemaVersion']);
        self::assertSame(['ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5', 'ADR-0014' => 'hash-14'], $manifestA['decisions']);
    }

    public function test_content_hash_changes_when_a_decision_hash_changes(): void
    {
        $manifest = GovernanceManifest::build(['ADR-0004' => 'hash-4'], 'snap-hash');
        $changed = GovernanceManifest::build(['ADR-0004' => 'hash-4-changed'], 'snap-hash');

        self::assertNotSame($manifest['contentHash'], $changed['contentHash']);
    }

    public function test_build_anchors_the_snapshot_hash(): void
    {
        $manifest = \Milpa\Governance\GovernanceManifest::build(
            ['ADR-0004' => 'aaa', 'ADR-0005' => 'bbb'],
            'snap-hash-123',
        );

        self::assertSame('snap-hash-123', $manifest['snapshotHash']);
        self::assertSame(['ADR-0004' => 'aaa', 'ADR-0005' => 'bbb'], $manifest['decisions']);
        self::assertArrayHasKey('contentHash', $manifest);
    }

    public function test_verify_integrity_is_true_when_hashes_match(): void
    {
        $idToContentHash = ['ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5'];
        $manifest = GovernanceManifest::build($idToContentHash, 'snap-hash');

        self::assertTrue(GovernanceManifest::verifyIntegrity($manifest, $idToContentHash, 'snap-hash'));
    }

    public function test_verify_integrity_is_false_when_a_hash_differs(): void
    {
        $manifest = GovernanceManifest::build(['ADR-0004' => 'hash-4'], 'snap-hash');

        self::assertFalse(GovernanceManifest::verifyIntegrity($manifest, ['ADR-0004' => 'hash-4-drifted'], 'snap-hash'));
    }

    public function test_verify_integrity_is_false_when_a_decision_is_missing_or_added(): void
    {
        $manifest = GovernanceManifest::build(['ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5'], 'snap-hash');

        self::assertFalse(GovernanceManifest::verifyIntegrity($manifest, ['ADR-0004' => 'hash-4'], 'snap-hash'));
        self::assertFalse(GovernanceManifest::verifyIntegrity(
            $manifest,
            ['ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5', 'ADR-0006' => 'hash-6'],
            'snap-hash',
        ));
    }

    public function test_verify_integrity_rejects_a_tampered_snapshot_hash(): void
    {
        $manifest = \Milpa\Governance\GovernanceManifest::build(['ADR-0004' => 'aaa'], 'snap-hash-123');

        // Same map of decisions, but the snapshot changed → the contentHash no longer matches.
        self::assertTrue(
            \Milpa\Governance\GovernanceManifest::verifyIntegrity($manifest, ['ADR-0004' => 'aaa'], 'snap-hash-123'),
        );
        self::assertFalse(
            \Milpa\Governance\GovernanceManifest::verifyIntegrity($manifest, ['ADR-0004' => 'aaa'], 'snap-hash-OTHER'),
        );
    }

    public function test_verify_integrity_still_rejects_a_tampered_decision(): void
    {
        $manifest = \Milpa\Governance\GovernanceManifest::build(['ADR-0004' => 'aaa'], 'snap-hash-123');

        self::assertFalse(
            \Milpa\Governance\GovernanceManifest::verifyIntegrity($manifest, ['ADR-0004' => 'TAMPERED'], 'snap-hash-123'),
        );
    }

    public function test_immutability_violation_when_a_base_id_is_missing_in_current(): void
    {
        $base = GovernanceManifest::build(['ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5'], 'snap-hash');
        $current = GovernanceManifest::build(['ADR-0005' => 'hash-5'], 'snap-hash');

        self::assertSame(['ADR-0004'], GovernanceManifest::immutabilityViolations($base, $current));
    }

    public function test_immutability_violation_when_a_base_hash_changed_in_current(): void
    {
        $base = GovernanceManifest::build(['ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5'], 'snap-hash');
        $current = GovernanceManifest::build(['ADR-0004' => 'hash-4-mutated', 'ADR-0005' => 'hash-5'], 'snap-hash');

        self::assertSame(['ADR-0004'], GovernanceManifest::immutabilityViolations($base, $current));
    }

    public function test_new_id_in_current_is_not_a_violation(): void
    {
        $base = GovernanceManifest::build(['ADR-0004' => 'hash-4'], 'snap-hash');
        $current = GovernanceManifest::build(['ADR-0004' => 'hash-4', 'ADR-0015' => 'hash-15'], 'snap-hash');

        self::assertSame([], GovernanceManifest::immutabilityViolations($base, $current));
    }

    public function test_identical_manifests_have_no_violations(): void
    {
        $manifest = GovernanceManifest::build(['ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5'], 'snap-hash');

        self::assertSame([], GovernanceManifest::immutabilityViolations($manifest, $manifest));
    }

    public function test_multiple_violations_are_all_reported(): void
    {
        $base = GovernanceManifest::build(['ADR-0004' => 'hash-4', 'ADR-0005' => 'hash-5', 'ADR-0006' => 'hash-6'], 'snap-hash');
        $current = GovernanceManifest::build(['ADR-0005' => 'hash-5-mutated', 'ADR-0006' => 'hash-6'], 'snap-hash');

        self::assertSame(['ADR-0004', 'ADR-0005'], GovernanceManifest::immutabilityViolations($base, $current));
    }
}
