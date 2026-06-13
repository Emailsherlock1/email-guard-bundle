# Changelog

## [0.2.0] - 2026-06-13

### Added

- Decision-telemetry wiring (EM-1005): registers the core `GuardReporter`
  as a service (integration `symfony-bundle`), injects it into the guard
  factory so every guard records decisions, and flushes the batch on
  `kernel.terminate` via `GuardTelemetryFlushListener`. Key-gated and
  fail-silent, so a form submit never waits on or breaks from the report.

### Changed

- Requires `emailsherlock/email-guard-core` `^0.2` (the reporter lives there).


## [0.1.1] - 2026-06-13

### Fixed

- VerifiedEmail no longer passes an options array to the base Constraint
  constructor (deprecated since symfony/validator 7.3, fatal in 8.0);
  the constructor now carries #[HasNamedArguments].


## [0.1.0] - 2026-06-13

First release, built on email-guard-core 0.1 (email-guard-spec 1.0.0).

### Added

- `VerifiedEmail` constraint + validator with per-constraint
  `blockOn` / `reviewOn` overrides.
- `'verify_email' => true` form option via form type extension.
- Bundle configuration mirroring the spec config keys
  (`api_key`, `block_on`, `review_on`, `fail_open`, `timeout_ms`).
- `EmailGuard` and `GuardFactory` available as services for review flows.
