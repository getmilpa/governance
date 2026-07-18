# Contributing to Milpa Governance

Thanks for your interest in contributing! Milpa Governance is the governance-as-contract engine of
the Milpa framework — pure logic that compiles immutable ADR decisions and a `GovernanceProfile`
into a deterministic `GovernancePlan`, classifying rules, gates, and required artifacts and
enforcing them honestly: an enforced gate only counts if its mechanism actually guards the subject
it names. It never touches the filesystem, the network, or the clock; ingestion and projection
live outside the engine.

## Getting started

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse src
php tools/validate-docblocks.php
```

These run in CI on PHP 8.3 and 8.4 (alongside `composer validate --strict` and a
`php -l` syntax pass); run them locally before opening a PR.

## Guidelines

- **PHP >= 8.3**, with `declare(strict_types=1);` in every file.
- **Document every public symbol.** A public class/interface/enum/trait or public
  method without a DocBlock summary fails CI (`tools/validate-docblocks.php`).
  Trivial accessors and magic methods are exempt.
- **Keep the engine pure.** The compiler derives; it does not read the wall clock, mutate
  history, or perform I/O — same input → same Plan, byte for byte. Outcomes are overlaid at read
  time, never baked into the snapshot.
- **Keep the engine dependency-free.** `milpa/governance` has no runtime dependencies of its
  own — `src/` must not import from `milpa/core` or any other family package. `milpa/core`
  appears only as a dev dependency, for the shared docs generator (`tools/gen-docs.php`).
- **[Conventional Commits](https://www.conventionalcommits.org/)** — releases and
  the CHANGELOG are generated automatically from commit messages. Use
  `feat:` / `fix:` / `docs:` / `chore:` etc.; a breaking change to a public
  interface or plan schema is a `feat!:` / `BREAKING CHANGE:` (bumps MINOR
  while the package is `0.x`, MAJOR once it reaches `1.0`).

## Code style

The whole Milpa family (`milpa/core`, `milpa/http`, `milpa/tool-runtime`, `milpa/event-store`,
`milpa/data`, `milpa/command`, `milpa/governance`) shares one coding standard, committed verbatim
in every repo as `.php-cs-fixer.dist.php` and enforced by CI. In short:

- **[PSR-12](https://www.php-fig.org/psr/psr-12/) base**: 4 spaces (never tabs);
  opening braces on the **next line** for classes and methods, on the **same line**
  for control structures; one statement per line.
- **Family deltas on top of PSR-12**: short array syntax (`[]`), one space around
  string concatenation (`$a . $b`), fully-multiline method arguments when split,
  no unused imports, aligned/separated/trimmed PHPDoc tags, trailing commas in
  multiline constructs.

Check and fix locally before pushing:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff   # what CI runs
vendor/bin/php-cs-fixer fix                    # apply
```

Do not tweak `.php-cs-fixer.dist.php` in one package alone — the standard changes
in lockstep across the family or not at all.

## Pull requests

Keep PRs focused, add tests for behavior changes, and make sure the four commands
above are green. A maintainer will review and, once merged to `main`,
release-please will handle versioning.

## License

By contributing, you agree that your contributions are licensed under the
[Apache License 2.0](LICENSE).

---

Milpa is developed and maintained by [TeamX Agency](https://teamx.agency).
