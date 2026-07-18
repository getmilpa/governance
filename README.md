<p align="center">
  <a href="https://github.com/getmilpa">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-dark.svg">
      <img src="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-light.svg" alt="Milpa" width="300">
    </picture>
  </a>
</p>

# Milpa Governance

> **Governance-as-contract** for the Milpa PHP framework: immutable ADRs compile into a
> **Governance Profile** and an inspectable **Governance Plan**. The core law: *an enforced gate is
> only honest if its mechanism actually guards the subject* — the validator refuses a profile that
> claims `enforced` without a real, allow-listed mechanism behind it.

[![CI](https://github.com/getmilpa/governance/actions/workflows/ci.yml/badge.svg)](https://github.com/getmilpa/governance/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/milpa/governance.svg)](https://packagist.org/packages/milpa/governance)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.3-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-API%20reference-blue.svg)](https://getmilpa.github.io/governance/)

`milpa/governance` answers one question honestly: does this system's declared law actually enforce
itself, or is `enforced` just a label? It takes the full corpus of immutable, ADR-backed
`GovernanceDecision` objects plus the `GovernanceProfile` that names which of them currently govern,
and produces two things: a **verdict** (`GovernanceValidator::validate()` throws a
`GovernanceException` the instant a claim is unproven — a duplicate decision id, a dangling
`supersedes`, a supersession cycle, a reference to a decision that is `rejected` or `superseded`
(declared or derived), or an `enforced` gate — the profile's own or one declared inside an ADR's
policies — with no allow-listed `boundTo`) and a **plan** (`GovernanceCompiler::compile()`
derives the honest `GovernancePlan` — decisions, rules, gates, and required artifacts — from that
same corpus). Supersession is *derived*, never mutated: an ADR file stays `accepted` forever; ending
up `superseded` is a calculated effect of the plan, so the audit trail never lies about what a
decision's file actually said.

## Install

```bash
composer require milpa/governance
```

## What it is (and isn't)

Milpa Governance is the **engine**: it validates and compiles a governance contract you give it — an
array of `GovernanceDecision` value objects plus a `GovernanceProfile`. It is **pure**: no I/O, no
wall clock, deterministic (the same input compiles to the same `GovernancePlan`, byte for byte). It
does **not** read files: a host implements `GovernanceRepositoryInterface` (`decisions(): array`,
`profile(): GovernanceProfile`) to load a `.milpa/governance/` directory — ADRs plus a
`profile.json` — and hands the resulting objects to the engine.

## Quick example

```php
use Milpa\Governance\GovernanceCompiler;
use Milpa\Governance\GovernanceValidator;

// $repository implements GovernanceRepositoryInterface — the host already loaded
// .milpa/governance/ (ADRs + profile.json) and handed the engine objects, not files.
$decisions = $repository->decisions();   // array<GovernanceDecision>
$profile = $repository->profile();       // GovernanceProfile

$validator = new GovernanceValidator(GovernanceValidator::DEFAULT_BOUND_TO);
$validator->validate($decisions, $profile); // throws GovernanceException if the profile lies

$plan = (new GovernanceCompiler($validator))->compile($decisions, $profile);

$plan->profileName;    // the profile's name
$plan->gates;          // array<GovernanceGate> — this contract's honest gates
$plan->toArray();      // deterministic, serializable shape
```

`compile()` calls `validate()` first — a plan is never built from an unproven contract — then
derives, from the **full** decision corpus (including superseded and historical lineage, so the plan
can show it honestly): one `GovernanceRule` per active decision, the merged `GovernanceGate`s and
`RequiredArtifact`s (from the profile itself and from `kind: 'gate'` / `kind: 'artifact'` policies
declared inside each ADR), and a `planHash` that binds this exact compilation. A `rejected`,
`proposed`, or `deprecated` decision never enters the plan — not as a decision entry, and none of its
policies govern anything.

## The value objects

| Value object | Role |
|-------|------------|
| `GovernanceDecision` | An immutable ADR: `id`, `title`, `DecisionStatus` (`proposed`\|`accepted`\|`rejected`\|`superseded`\|`deprecated`), what it `supersedes`, what it `governs`, its derived `policies`, and a `contentHash` that seals its content. |
| `GovernanceProfile` | The law's table of contents: the `decisionIds` that currently govern, the typed `GovernanceGate`s and `RequiredArtifact`s, and the profile's own `schemaVersion`/`name`/`version`. |
| `GovernanceGate` | A checkpoint: an `id`, a `description`, an `EnforcementTier` (`enforced`\|`advisory`\|`convention`\|`deferred`), and the `boundTo` mechanism an `enforced` gate must name to be honest. |
| `GovernancePlan` | The compiled, inspectable law: decisions (with derived `effectiveStatus`/`supersededBy`), rules, gates, artifacts, actors, assumptions, and the `planHash` that binds the whole compilation. `toArray()` is the deterministic, serializable shape. |
| `GovernanceManifest` | The `{id: contentHash}` immutability seal, ksorted and hashed — `verifyIntegrity()` detects drift against the current hashes, `immutabilityViolations()` detects a decision that changed or disappeared since a prior base manifest. |
| `GovernanceException` | Every contract break, one named factory per cause (`profileMissing`, `decisionDuplicate`, `supersessionCycle`, `enforcementUnproven`, …) — each message names what broke, why, and the fix. |

## Anatomy of a governance contract

A contract lives in `.milpa/governance/`: immutable ADRs (`adr/*.md`) plus a `profile.json`. The
engine consumes them through the host's `GovernanceRepositoryInterface` — it never reads the
directory itself. This is the minimal shape (generic — an example service, not any real repo's law):

**`.milpa/governance/adr/0001-tests-are-the-contract.md`**
```markdown
---
id: ADR-0001
title: Tests are the contract
status: accepted
date: 2026-01-15
supersedes: null
governs: [quality]
policies: []
---

## Context
A green suite is the cheapest proof that a change didn't break the promise.

## Decision
Every change ships with the test suite green. The `tests` gate is `enforced`, bound to the CI job
that actually runs it.

## Consequences
An `enforced` gate with no real job behind it would be a lie the validator rejects.
```

**`.milpa/governance/profile.json`**
```json
{
  "schemaVersion": "0.1",
  "name": "example-service",
  "version": "2026.01.1",
  "decisionIds": ["ADR-0001"],
  "gates": [
    { "id": "tests", "description": "The test suite passes.", "tier": "enforced", "boundTo": "ci:test:unit" }
  ],
  "artifacts": [],
  "actors": []
}
```

Compiling this contract yields a `GovernancePlan` whose `tests` gate is honestly `enforced` because
`ci:test:unit` is an allow-listed mechanism (`GovernanceValidator::DEFAULT_BOUND_TO`). Change `boundTo`
to something not on the allow-list and `validate()` throws — the wall that stops an aspirational gate.

## Requirements

- PHP **≥ 8.3**
- No runtime dependencies — the engine is dependency-free by design (`milpa/core` is a dev-only
  dependency, used solely to generate the API reference).

## Documentation

**Full API reference: [getmilpa.github.io/governance](https://getmilpa.github.io/governance/)** —
generated straight from the source DocBlocks and dressed with the Milpa design system.

## Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Please report security
issues via [SECURITY.md](SECURITY.md), and note that this project follows a
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

[Apache-2.0](LICENSE) © Rodrigo Vicente - TeamX Agency.

---

Milpa is designed, built, and maintained by **[Rodrigo Vicente - TeamX Agency](https://teamx.agency/?utm_source=github&utm_medium=readme&utm_campaign=milpa&utm_content=governance)**.
