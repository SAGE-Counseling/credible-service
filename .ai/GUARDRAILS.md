# Guardrails

These rules are always in effect unless a task explicitly overrides them.

## Critical (must not be violated)

- No secrets in code, state files, logs, commits, or tests. This repo is **public** on GitHub: Credible
  connection strings, URLs that embed keys, and real client data must never be committed, even in fixtures.
- No PHI in code, fixtures, logs, commits, issues, or PR text. Everything Credible returns is client data;
  test fixtures must be synthetic.
- Changes to logic, validation, orchestration, or security boundaries require tests.
  - Exception: schema migrations that only add/modify/drop columns, tables, or indexes do not themselves
    need a test. If a migration ships alongside logic that reads/writes the changed schema, that logic
    still needs tests per the normal rule. (This package ships no migrations today.)
- One task per branch. One branch per task.
- Do not change behavior outside the task scope.
- **NO MAIN/MASTER COMMITS:** Under no circumstances should work be performed directly on the main branch
  — **except** the interactive-session exception below.
- **BRANCH VALIDATION:** Before any file modification, the agent must verify it is on a branch matching the
  pattern `ai/claude/<issue-number>-<slug>`.
- **FAILURE STATE:** If the agent is on the main branch, it must STOP and request the user to create a
  branch, provide the command to create one, or create the branch and switch to it — unless the
  interactive-session exception below applies.
- **INTERACTIVE-SESSION EXCEPTION:** This rule exists to protect unsupervised/AFK agent runs and
  multi-agent collision avoidance, where a branch is the only checkpoint before a bad change lands. It is
  not needed when a human is live in the session watching each tool call. In a live interactive session,
  the agent may commit directly to main — skipping the issue/branch/PR flow — only when **all** of the
  following hold:
  - The user is present in the session and explicitly authorizes the direct commit (a standing instruction
    from an earlier turn does not count; ask each time).
  - The change is low-risk and small in scope: documentation, comments, or config tweaks — not logic,
    validation, orchestration, security boundaries, or anything requiring tests per the Critical rule above.
  - If there is any doubt about whether a change qualifies, treat it as out of scope for this exception and
    use the normal branch/issue/PR flow instead.
- **NO DESTRUCTIVE DB/STATE COMMANDS OUTSIDE THE TEST RUNNER:** Never run a full-reset/wipe command (or its
  programmatic equivalent, e.g. from a REPL/console) against real data — even one written specifically to
  "verify" something. See "Database safety" below.
- **NO LIVE CREDIBLE CALLS FROM AGENTS:** Don't call the real Credible API (SOAP export or POST import) to
  "verify" a change. A POST writes into the production EHR. Use a mocked `SoapClient` / `Http::fake()`.

## Important (default expectations)

- Keep diffs small and scoped to the task.
- No drive-by refactors unless explicitly allowed by the task.
- Every change must report:
  - Files changed
  - How to test (exact commands)

## Progress tracking

- Update `.ai/PROJECT_STATE.md` and post a completion comment on the issue before marking a task done;
  close the issue (or reference it so the merge closes it).
- If progress stalls, comment on the issue with the reason. Leave yourself assigned if you expect to
  resume; unassign if abandoning the approach.

## Git rules

- Do not run git commands unless explicitly instructed by the user or task.
- Do not merge into main without explicit instruction.
- Do not create or push release tags without explicit instruction; consuming apps resolve versions from them.
- Do not use:
  - `git push --force`
  - `git merge --squash`
  - `git rebase`
  - `git commit --amend`
    unless explicitly instructed and you understand the consequences.
- If unsure, stop and ask.

## PHP/Laravel package guardrails

- Stay compatible with every `illuminate/*` version `composer.json` allows (currently ^9–^13) and PHP ^8.2.
  Don't use APIs that only exist in a newer framework or PHP version than the lowest allowed.
- Prefer dependency injection over facades and global aliases (`use Http;`, `use Log;`) in new code. The
  consumers (bi-reflector, credible-importer) need to bind, mock, and fake this service.
- Never hardcode Credible URLs, connection strings, or credentials. Read them from `config('credible.*')`,
  backed by env vars in the consuming app.
- Treat every Credible response as PHI: don't log raw payloads, rows, or decoded XML. Log identifiers like
  the export name (`param1`), counts, and error messages only.
- Credible's CSV import uses its own parser, not RFC 4180: a comma inside a quoted field still splits the
  column. Escape commas as `&comma;`. Don't rely on `""`, `&quot;`, backslash escapes, or embedded newlines
  working; they're untested.

## Database safety

- This package has no database of its own. Its only persistent state is Redis keys (`backoff:api:<class>`
  and `...:step`, written by `ApiRetryWithBackoff`) and temporary XML files on the default `Storage` disk.
  Both belong to whatever Laravel app is running the code.
- Tests run under Orchestra Testbench (`Storage::fake()`, mocked `Redis`, `Http::fake()`). That isolation only
  applies inside `vendor/bin/phpunit`. Running the service from a consuming app's `php artisan tinker` hits
  that app's real Redis, real disk, and real Credible connection.
- Practical consequence: don't "quickly verify" backoff behavior by calling `clearBackoff()`,
  `forceHaltBackoff()`, or `Redis::flushdb()` from a consumer's tinker; that changes live import-worker
  throttling. Write a test with a faked/array Redis instead.
- If you must inspect state ad hoc, only ever run **read-only** operations — never a reset/wipe, and never
  anything that could resolve to one dynamically (e.g. a command name built from a variable).
- If a destructive command against real data is genuinely needed (not test verification), say so explicitly
  and confirm with the user first.

## Test stance

- Default test command: `vendor/bin/phpunit` (also `composer test`). Tests live in `tests/` and extend
  `Sage\Credible\Tests\TestCase`. `composer.lock` is gitignored (library), so suites resolve fresh.
- Mock the SOAP client (the constructor accepts one) and use `Http::fake()` for `post()`. Tests must never
  reach the network.
- Pure logic (XML parsing, row yielding, backoff step math) gets unit tests. Service-provider and config
  wiring gets Testbench tests.

## Quality tools (optional)

- Formatter: not configured (`.gitignore` mentions `.php-cs-fixer.cache`, but no config exists).
- Static analysis: not configured.
- Do not introduce new tools unless instructed.
- If not configured, note "not configured" in task output rather than inventing an invocation.

## Version compatibility

- Constraint changes to `illuminate/*` or `php` must keep every consuming app installable. Check
  bi-reflector's and credible-importer's `composer.json` before narrowing a range.
- Don't migrate test frameworks unprompted.
