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
 * The immutability manifest: an `{id: contentHash}` map of the decisions, ordered
 * deterministically (ksort) and sealed with its own content hash — the same pattern that
 * {@see \Milpa\Plugin\LockFileManager} applies to `milpa.lock` (ksort → `hash('sha256',
 * json_encode(..., JSON_THROW_ON_ERROR))` → `hash_equals` to compare).
 *
 * Pure: only arrays in, arrays out. No I/O — reading ADRs and writing the file to disk
 * is the host's responsibility, {@see \Milpa\app\Governance\RepoGovernanceManifest}.
 *
 * Two questions, two methods:
 * - {@see verifyIntegrity()}: does this manifest still describe the CURRENT hashes? (is it in
 *   sync, or did someone change an ADR without regenerating?)
 * - {@see immutabilityViolations()}: compared to a previous BASE manifest, did any decision that
 *   already existed disappear or change content? (Amendment 1B — a new ID is not a violation.)
 */
final class GovernanceManifest
{
    public const string SCHEMA_VERSION = 'milpa-governance-manifest/v1';

    /**
     * Builds the deterministic manifest from `{id: contentHash}` + the hash of the compiled
     * snapshot. Sorts by id (ksort) before sealing. The `snapshotHash` (sha256 of the on-disk
     * snapshot bytes, provided by the host) anchors the compiled artifact that an EXTERNAL
     * consumer reads — GOV-5: without this anchor, a consumer that cannot recompile (Sembrador, bash)
     * could not detect a snapshot that is CORRUPTED or OUT OF SYNC with the profile (drift, or
     * accidental corruption in transit/on disk). The manifest's own `contentHash` covers that. This
     * is NOT authenticity: `snapshotHash` is a `sha256` recomputable without any secret, so an
     * adversary with write access to the repository can regenerate the snapshot + hash consistently
     * and pass verification (see ADR-0019). Detecting a snapshot tampered with by that adversary
     * requires a signature / trust root external to the producer — out of scope for GOV-5.
     *
     * @param array<string, string> $idToContentHash
     *
     * @return array{schemaVersion: string, decisions: array<string, string>, snapshotHash: string, contentHash: string}
     */
    public static function build(array $idToContentHash, string $snapshotHash): array
    {
        ksort($idToContentHash);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'decisions' => $idToContentHash,
            'snapshotHash' => $snapshotHash,
            'contentHash' => self::computeHash($idToContentHash, $snapshotHash),
        ];
    }

    /**
     * Is the manifest still in sync with the current hashes (decisions + snapshot)? Rebuilds the
     * content-hash from `$idToContentHash` + `$snapshotHash` (same order, same algorithm)
     * and compares it with `hash_equals` — timing-safe. An ADR edited without regenerating, or a
     * snapshot that is corrupted or out of sync with the profile, break the equality. This
     * verification demonstrates consistency (integrity), not authenticity: an adversary with write
     * access can regenerate both hashes consistently (see ADR-0019).
     *
     * @param array{schemaVersion?: mixed, decisions?: mixed, snapshotHash?: mixed, contentHash?: mixed} $manifest
     * @param array<string, string>                                                                      $idToContentHash
     */
    public static function verifyIntegrity(array $manifest, array $idToContentHash, string $snapshotHash): bool
    {
        ksort($idToContentHash);
        $recomputed = self::computeHash($idToContentHash, $snapshotHash);

        return hash_equals((string) ($manifest['contentHash'] ?? ''), $recomputed);
    }

    /**
     * Did any decision accepted in the BASE manifest disappear or change hash in the CURRENT
     * manifest? (Amendment 1B.) Every `id => hash` from the base must still exist in the current one
     * with the same hash (`hash_equals`); if it's missing or differs, the id is a violation. A NEW
     * id in the current one (absent from the base) is allowed — it is not a violation, it is a
     * decision born after the base. Deterministic: iterates the base in its already-sorted order
     * (ksort from {@see build()}).
     *
     * @param array{decisions?: mixed} $baseManifest
     * @param array{decisions?: mixed} $currentManifest
     *
     * @return list<string>
     */
    public static function immutabilityViolations(array $baseManifest, array $currentManifest): array
    {
        $base = is_array($baseManifest['decisions'] ?? null) ? $baseManifest['decisions'] : [];
        $current = is_array($currentManifest['decisions'] ?? null) ? $currentManifest['decisions'] : [];

        $violations = [];
        foreach ($base as $id => $hash) {
            $id = (string) $id;
            $currentHash = $current[$id] ?? null;

            if (!is_string($currentHash) || !hash_equals((string) $hash, $currentHash)) {
                $violations[] = $id;
            }
        }

        return $violations;
    }

    /**
     * Content hash over the ALREADY-sorted decisions + the snapshot hash. `json_encode`
     * with `JSON_THROW_ON_ERROR` (loud failure) and `sha256`.
     *
     * @param array<string, string> $sortedDecisions
     */
    private static function computeHash(array $sortedDecisions, string $snapshotHash): string
    {
        return hash(
            'sha256',
            json_encode(['decisions' => $sortedDecisions, 'snapshotHash' => $snapshotHash], JSON_THROW_ON_ERROR),
        );
    }
}
