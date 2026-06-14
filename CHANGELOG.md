# Changelog

## [0.2.2] - 2026-06-14

### Fixed

- `psr/log` moved from require-dev to require: `VerifiedEmailValidator`
  references `Psr\Log\LoggerInterface` in src, so it is a runtime dependency
  (in practice always present transitively via symfony/http-kernel, but now
  declared correctly).


## [0.2.1] - 2026-06-14

### Added

- Key-independent deny logging (EM-1013): `VerifiedEmailValidator` logs every
  blocked decision via an optional PSR-3 logger (autowired in Symfony) as
  `email_guard.denied` with domain, verdict, reasons, source. Domain only,
  never the address. Gives operators visibility into what the local-only
  guard filters without any API key, credits, or DB.


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
