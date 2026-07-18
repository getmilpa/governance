# Changelog

## 0.1.0 (2026-07-18)

Initial public release of `milpa/governance` — the governance-as-contract engine of the Milpa PHP
framework, extracted as a standalone package.

### Features

* Governance Profile validation: an `enforced` gate is rejected unless its `boundTo` names a real,
  allow-listed mechanism (the honesty wall).
* Deterministic compilation of immutable ADRs into an inspectable Governance Plan (derived
  supersession, no history mutation).
* Governance Manifest with SHA-256 integrity over the compiled snapshot.
* Evidence + history value objects (Run, Observation, RunHistory) and status/history projectors —
  outcomes overlaid at read time, never baked into the snapshot.
