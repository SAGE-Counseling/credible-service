# Project Context

## Project type

Laravel Composer package (`sage/credible-service`, namespace `Sage\Credible\*`) that wraps the Credible
(CredibleBH) EHR APIs: SOAP data-export reads (`get()`, `yieldRows()`) and HTTP CSV import posts
(`post()`). Library only; it has no app, routes, or deployment of its own.

## Framework and versions

- PHP ^8.2; `illuminate/support`, `illuminate/console`, `illuminate/contracts` ^9|^10|^11|^12|^13.
- Needs `ext-soap`, `ext-simplexml`, `ext-dom`, `ext-json`.
- Auto-discovered provider: `Sage\Credible\CredibleServiceProvider` (merges and publishes `config/credible.php`).

## Tooling

- Test command: `vendor/bin/phpunit` (PHPUnit + Orchestra Testbench; Testbench major follows Laravel: 7→9 … 11→13)
- Formatter: not configured
- Static analysis: not configured

## Hot spots

- `src/Traits/ApiRetryWithBackoff.php` assumes it is mixed into a queued job: `failed()` reads
  `$this->param1`, `$this->param2` and `$this->job`, which `CredibleService` doesn't define. It also reads
  config key `importworker.backoff_steps`, which belongs to the consuming app, before falling back to
  `credible.api_backoff_timing`.
- The trait uses the `Redis` facade, but `illuminate/redis` isn't required. The consuming app must provide
  Redis.
- `CredibleService` imports global aliases (`use Http; use Log; use Storage; use Str;`), not the
  `Illuminate\Support\Facades\*` classes. These only resolve if the consuming app registers the aliases.
- `CredibleService::__construct()` reads `credible.cross_reference.<connection>`, which isn't in this
  package's `config/credible.php`. Consuming apps define it.
- `yieldRows()` / `processXml()` write decoded export XML (PHI) to the default `Storage` disk and delete it
  in `finally`. Changes there must keep the cleanup path intact.
- `CredibleServiceProvider` imports `Sage\Credible\Console\Commands\CompareCredibleServicesCommand`,
  which doesn't exist. The import is unused, so it's harmless until something references it.

## Local Development Environment

Windows 11, PowerShell as the primary shell; Git Bash is also available. PHP and Composer come from Laravel
Herd; Git Bash shims in `~/bin` let bare `php` / `composer` / `vendor/bin/*` work from Bash. bi-reflector
loads this package as a Composer path repository (`../Packages/credible-service`), so local edits show up
there without a tag.

## Server Environment

None of its own. Runs inside consuming apps:

- bi-reflector (`sage/credible-service: *`, VCS repo plus local path repo)
- credible-importer (`Packages/credible-importer`)

Defer to their `.ai/CONTEXT.md` for server facts. Consumers resolve released versions from git tags
(`v1.0.0`, `v1.0.1`, `v1.0.3`).

## Databases

None. State lives in the consuming app's Redis (backoff keys) and default filesystem disk (temporary XML).

## Languages and Frameworks

PHP only: Laravel package conventions (PSR-4, service provider, mergeable/publishable config), SOAP via
`SoapClient` against `src/Services/CredibleWsdl/ExportService.wsdl`, streaming XML via `XMLReader`.

## Tooling Expectations

Write shell instructions for PowerShell or Git Bash and say which. Composer constraints typed in PowerShell
lose `^`, so quote them or run Composer from Bash.

## Schema/Data Authority

Credible export column sets are defined by the Credible-side export (`param1` names the export), not by this
repo. Don't infer column names from memory; read a consuming app's mapping or ask. Known Credible quirks
(CSV parsing, `&comma;`, unconfirmed timestamp time zone) are in `~/.claude/memory/domain/credible.md`.
