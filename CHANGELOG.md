# Changelog

## [0.1.0] - 2026-06-13

First release, built on email-guard-core 0.1 (email-guard-spec 1.0.0).

### Added

- `VerifiedEmail` constraint + validator with per-constraint
  `blockOn` / `reviewOn` overrides.
- `'verify_email' => true` form option via form type extension.
- Bundle configuration mirroring the spec config keys
  (`api_key`, `block_on`, `review_on`, `fail_open`, `timeout_ms`).
- `EmailGuard` and `GuardFactory` available as services for review flows.
