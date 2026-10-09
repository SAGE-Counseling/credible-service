# Project State

Live snapshot only — not a history log. Task history lives in closed issues; query the tracker instead of
appending to this file indefinitely. When this file grows a long changelog, trim it back to recent entries
and point further back at the issue tracker.

## Current status

Stable and in production use by bi-reflector and credible-importer. Agent protocol and skills config were
added on 2026-10-09; no task is in flight.

## What's working

- SOAP export reads: `get()` (array) and `yieldRows()` (streams rows via `XMLReader`, with temp-file cleanup).
- CSV import `post()` to Credible.
- Redis-backed API backoff (`ApiRetryWithBackoff`).
- Config merge/publish through `CredibleServiceProvider`.

## What's broken / blocked

- No test suite exists, so any logic change needs PHPUnit + Testbench set up first.
- `illuminate/*` is capped at ^12, which blocks bi-reflector's Laravel 13 upgrade (issue #1).

## Next milestone

Issue #1: allow Laravel 13, add tests that run against it, and tag a release after `v1.0.3`.

## Recent decisions

- 2026-10-09: PHP ^8.2 required; Laravel 9–12 allowed (commits `6cd77cf`, `728ec3e`).
- 2026-10-09: GitHub Issues is the tracker, with the default triage labels (`docs/agents/`).
