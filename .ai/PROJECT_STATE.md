# Project State

Live snapshot only — not a history log. Task history lives in closed issues; query the tracker instead of
appending to this file indefinitely. When this file grows a long changelog, trim it back to recent entries
and point further back at the issue tracker.

## Current status

Stable and in production use by bi-reflector and credible-importer. Issue #1 (Laravel 13) is in review on
branch `ai/claude/1-allow-laravel-13`; a release tag after `v1.0.3` is pending the user's go-ahead.

## What's working

- SOAP export reads: `get()` (array) and `yieldRows()` (streams rows via `XMLReader`, with temp-file cleanup).
- CSV import `post()` to Credible.
- Redis-backed API backoff (`ApiRetryWithBackoff`).
- Config merge/publish through `CredibleServiceProvider`.
- Test suite (`vendor/bin/phpunit`, 8 tests) passes on Laravel 12 and 13. Laravel 9–11 are allowed but
  untested locally.

## What's broken / blocked

- Nothing blocking. Known rough edges are listed under "Hot spots" in `.ai/CONTEXT.md`.

## Next milestone

Merge issue #1's PR and tag a release after `v1.0.3` so bi-reflector#78 can install Laravel 13.

## Recent decisions

- 2026-10-09: PHP ^8.2 required; Laravel 9–12 allowed (commits `6cd77cf`, `728ec3e`).
- 2026-10-09: GitHub Issues is the tracker, with the default triage labels (`docs/agents/`).
- 2026-10-09: Laravel 13 allowed; PHPUnit + Testbench added as dev deps; `composer.lock` gitignored (#1).
