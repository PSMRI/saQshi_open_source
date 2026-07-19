# Coding Standards and Engineering Conventions

This document answers the main engineering-standard questions for SaQshi. It is written as a practical developer reference rather than a long checklist.

## Short Answer

SaQshi follows a lightweight, modular PHP + JavaScript standard:

- PHP code is PSR-inspired, with readable indentation, clear service classes, versioned API endpoints and shared core helpers.
- JavaScript uses modular page scripts, shared core utilities and page-level initialization.
- UI pages follow a predictable `html + js + css + json` file pattern.
- API responses follow a consistent JSON structure.
- Configuration is JSON-driven for frameworks, facility types, indicators, map boundaries and page metadata.
- Security is handled through sessions, CSRF, RBAC, prepared statements, encryption helpers, upload validation, friendly errors and event logging.

SaQshi now includes a dependency-free quality gate for syntax checks, lightweight PHP/JavaScript style checks, JSON validation, unit tests and release readiness. The checks are intentionally practical for this PHP/static-UI codebase and can later be replaced or supplemented with PHPCS, PHP CS Fixer and ESLint.

## Evidence from the Codebase

The standards below are not only proposed rules. They are visible in the current codebase.

| Standard Area | Evidence File | Actual Example | What It Shows |
| --- | --- | --- | --- |
| Standard JSON API response | `api/core/Response.php` | `Response::success`, `Response::validation`, `Response::serverError` | APIs return a consistent JSON shape. |
| Friendly error handling | `api/core/Response.php` | `Response::serverError` | Raw PHP/database errors are not shown directly to users. |
| Testable score logic | `api/core/ScoreCalculator.php` | `percentage`, `checkpointMaxScore`, `totalCheckpointScore` | Score calculation is moved into pure reusable functions. |
| Unit test evidence | `tests/unit/*Test.php` | `sqTest(...)` | Core logic and encryption behavior have executable tests. |
| API versioning | `api/auth/v1/login.php`, `api/performance/v1/dashboard.php` | Endpoint paths include `/v1/`. | Public API URLs are versioned by module. |
| HTTP method guard | `api/auth/v1/login.php` | `Security::requireMethod('POST');` | Write/security-sensitive endpoints enforce the intended HTTP method. |
| Event abstraction | `api/core/Event.php` | `Event::dispatch(...)` | Domain/API events are dispatched through a central abstraction. |
| Log redaction | `api/core/Event.php` | `isSensitiveKey()` | Security logs/events avoid sensitive values. |
| Encrypted login transport | `api/auth/v1/login.php` | `LoginCrypto::decryptPassword(...)` | Login API expects encrypted password transport. |
| CSRF after login | `api/auth/v1/login.php` | `$csrfToken = Csrf::regenerate();` | CSRF token is regenerated after successful login. |
| UI page manifest pattern | `ui/pages/assessment/checklist.json` | `assets.css`, `assets.js`, `api`, `breadcrumb`, `authentication` | Pages declare metadata, assets, API links and auth requirements in JSON. |
| UI runtime accessibility | `ui/assets/js/core/a11y.js` | `labelDynamicControls()` | Dynamic JS-rendered controls are improved after load. |
| Quality gate | `tools/quality_gate.php` | Runs PHP syntax, JSON syntax, PHP style, JS style, unit tests and release readiness. | Local quality checks are executable. |
| CI workflow | `.github/workflows/quality.yml` | `run: php tools/quality_gate.php` | Pull requests/pushes can run the same quality gate in GitHub Actions. |

Example standard response shape from `api/core/Response.php`:

```php
echo json_encode([
    'status'    => $status,
    'message'   => $message,
    'data'      => $data,
    'errors'    => $errors,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```

Example page manifest from `ui/pages/assessment/checklist.json`:

```json
{
    "id": "assessment-checklist",
    "layout": "dashboard",
    "authentication": {
        "required": true
    },
    "assets": {
        "css": ["/ui/pages/assessment/checklist.css?v=20260702-10"],
        "js": ["/ui/pages/assessment/checklist.js?v=20260702-10"]
    }
}
```

Example event abstraction from `api/core/Event.php`:

```php
Event::dispatch('auth.login.succeeded', [
    'user_id' => $result['data']['user']['u_id'] ?? null,
    'facility_id' => $result['data']['user']['facility_id'] ?? null
]);
```

## Coding Standard

| Question | SaQshi Standard |
| --- | --- |
| Which coding standard is followed? | PSR-inspired PHP style, modular service/controller separation, consistent JSON responses and page-level JavaScript modules. |
| Is PSR-12 followed? | Mostly as a target style, but not yet enforced automatically across every legacy file. New PHP code should follow PSR-12 principles. |
| Why this standard? | It is readable for PHP teams, simple for public-sector deployment and does not require a complex build pipeline. |
| Is the standard documented? | Yes, this document defines the project convention. |
| Is formatting enforced? | Yes, through lightweight project checks under `tools/`. Full PSR-12 auto-formatting can be added later through PHPCS/PHP CS Fixer. |
| Indentation and braces | Use 4 spaces in PHP, readable block braces, one statement per line, and avoid deeply nested logic. |
| Comments | Use comments for file headers, public functions, complex business rules, security decisions and non-obvious calculations. Avoid comments that only repeat the code. |
| Duplicate code | Prefer shared `api/core`, `api/service`, UI core utilities, shared components and JSON configuration instead of copying logic. |

## Naming Conventions

| Area | Convention |
| --- | --- |
| PHP classes | `PascalCase`, for example `PerformanceService`, `StateDashboardService`, `Crypto`. |
| PHP functions/methods | `camelCase`, action-oriented names, for example `loadActiveAssessment`, `saveDepartmentStatus`. |
| PHP variables | `camelCase` where new code allows it. Some legacy DB-oriented code may still use snake_case names. |
| Constants | `UPPER_SNAKE_CASE`. |
| PHP endpoint files | Lowercase descriptive names, often snake_case or module action names, for example `active_assessment.php`, `dashboard.php`. |
| Folders | Lowercase module names with version folders, for example `api/assessment/v1`, `api/performance/v1`. |
| JavaScript functions | `camelCase`, page-specific functions inside the page script. |
| CSS classes | Lowercase kebab-case or shared `sq-` prefixed classes where common styling is used. |
| HTML IDs | Lowercase kebab-case, used only where scripting needs stable selectors. Prefer classes/data attributes for styling. |
| JSON keys | Lowercase snake_case or stable config names matching existing configuration files. |
| API endpoints | Versioned module paths: `api/<module>/v1/<action>.php`. |
| Database tables | Lowercase snake_case. Existing legacy tables are retained where already used. |
| Database columns | Lowercase snake_case where possible; legacy columns are not renamed without migration. |
| Primary keys | Usually `<entity>_id`, `id`, or existing legacy key names. |
| Foreign keys | Prefer `<entity>_id` or `<entity>_id_fk` where legacy schema already uses that pattern. |
| UI labels | User-friendly names, not raw table/column names. |

Technical database names should not be exposed directly to end users. UI text should use terms such as "Assessment", "Department", "Facility", "Action Plan", "Gap Closure" and "Certification Status".

## Application Architecture

SaQshi uses a modular monolithic architecture:

```text
Browser UI
  -> ui/dashboard.html router
  -> ui/pages/<module>/<page>.html/js/css/json
  -> api/<module>/v1/<endpoint>.php
  -> api/service/<Service>.php
  -> api/core shared helpers
  -> MySQL/MariaDB and JSON configuration
```

| Topic | Standard |
| --- | --- |
| Architecture style | Modular PHP application with static JavaScript UI. |
| Separation of concerns | UI rendering stays in `ui/`; API request handling stays in `api/*/v1`; business logic is moved to `api/service`; shared helpers stay in `api/core`. |
| Configuration | JSON files under `api/config` and page manifests under `ui/pages/**/*.json`. |
| Environment handling | `.env` loaded through core environment helpers. Secrets are not committed. |
| Reusable UI | Shared components under `ui/components` and shared CSS/JS under `ui/assets`. |
| Reusable API logic | Shared classes in `api/core` and `api/service`. |
| Framework support | Frameworks such as NQAS, MusQan and LaQshya are expected to be configuration-driven through JSON master/config files. |

## Function and Class Design

- Each endpoint should perform request parsing, authentication/authorization, validation, service call and response formatting.
- Business rules should live in service classes where possible.
- Functions should do one clear task and return predictable arrays/objects.
- Validate all external inputs before database use.
- Prefer small helper functions over long conditional blocks.
- Use static methods only for stateless utility/service operations already following the project pattern.
- Do not expose raw exceptions or database errors to end users.
- Use `Event::dispatch(...)` for important domain events so the application is ready for future event streaming.

## Database Standards

| Topic | Standard |
| --- | --- |
| SQL injection prevention | Use prepared statements for user-controlled values. |
| Transactions | Use for multi-step writes where partial save can corrupt workflow state. |
| Duplicate prevention | Enforce in service logic and add database constraints where safe. Example: one active assessment rule, duplicate NIN checks. |
| Audit fields | Use created/updated by and timestamp fields where available; use event/audit logging for important state changes. |
| Migrations | Store schema/migration files under `api/sql`. |
| Stored procedures | Not the default pattern. PHP service logic plus prepared SQL is preferred for portability. |
| Error handling | Log technical details internally and return friendly API errors. |
| Data integrity | Validate IDs, role scope, facility scope and active assessment state before writes. |

Existing legacy schema names should not be renamed casually. Rename only with migration scripts and compatibility checks.

## API Standards

| Topic | Standard |
| --- | --- |
| API style | REST-like PHP endpoints. |
| URL structure | `api/<module>/v1/<resource-or-action>.php`. |
| Versioning | Version folder such as `v1`. |
| HTTP methods | `GET` for reads, `POST` for creates/updates/actions; avoid unsafe writes through plain GET. |
| Response format | JSON with `status`, `message`, `data`, `errors`, `timestamp`. |
| Errors | Friendly message for users; technical details logged internally. |
| Auth | Session-based authentication. |
| Authorization | Role and scope checks by facility/block/district/division/state as applicable. |
| CSRF | Protected write endpoints should validate CSRF tokens. |
| Documentation | OpenAPI, endpoint inventory, Postman collection and source reference under `docs/api`. |

## Security Standards

| Area | SaQshi Handling |
| --- | --- |
| Passwords | Passwords must be hashed, not stored as plain text. Legacy plain passwords are converted during login/update flow where supported. |
| Sensitive profile fields | User name, mobile and email use encryption helpers when saved through current profile APIs. |
| Assessor/assessee names | Encrypted because they are personal names. |
| CSRF | CSRF endpoint and token validation are used for protected requests. |
| Sessions | Session-based login with role context. Session regeneration and timeout handling should be retained in auth flow. |
| RBAC | Role-specific menus and APIs restrict access by role and administrative scope. |
| SQL injection | Prepared statements are required for user-controlled inputs. |
| XSS | Escape user-rendered content in UI and avoid inserting untrusted HTML. |
| File uploads | Validate type, size and extension. Evidence uploads are optional unless a workflow explicitly requires them. |
| Secrets | Use `.env`; never commit database passwords, tokens or keys. |
| Errors | Use friendly responses, not raw PHP/DB errors. |
| Events/logs | Event logging redacts sensitive keys before writing logs. |

## UI and Accessibility Standards

| Topic | Standard |
| --- | --- |
| UI architecture | Static HTML/CSS/JS with dashboard shell and routed pages. |
| Page pattern | `page.html`, `page.js`, `page.css`, `page.json`. |
| Labels | Use user-friendly healthcare quality terms. |
| Responsive design | Pages should work on desktop, tablet and mobile where the workflow allows. |
| Theme support | Light/dark theme must keep readable text, controls and buttons. |
| Accessibility | Follow WCAG-oriented labels, focus visibility, keyboard support and readable contrast. |
| Icons | Use icons with visible labels or tooltips where meaning is not obvious. |
| Validation | Show clear user-friendly validation messages near the action. |
| Multilingual support | Documentation supports multilingual guidance; application labels should be kept ready for future translation/configuration. |

## Error Handling and Logging

- API responses should use a standard JSON shape.
- End users should see friendly messages such as "Something went wrong while processing your request."
- Logs should capture enough technical detail for debugging without storing passwords, CSRF tokens or sensitive personal data.
- Fatal errors and uncaught exceptions should be converted to safe responses where bootstrap/core handling is available.
- Evidence and audit logs follow retention guidance documented in `docs/security/evidence_upload_and_log_retention.md`.

## Testing and Quality Standards

| Area | Standard |
| --- | --- |
| Syntax checks | Run PHP syntax checks on changed PHP files. |
| API tests | Use Postman collection and OpenAPI/Swagger guidance. |
| Security tests | Use SQL injection review, VAPT report and release security scan guidance. |
| Accessibility tests | Use WCAG page audit and web platform compliance documents. |
| Load tests | Use load testing guide before production-scale rollout. |
| Release checks | Run `php tools/release_readiness_check.php`. |
| Regression | Re-test affected modules after API, DB, routing or shared component changes. |

Automated unit-test and CI checks are available through `tools/quality_gate.php` and `.github/workflows/quality.yml`. Coverage should grow as more service classes are separated from endpoint/session/database code.

## Documentation Standards

Every major feature should have:

- User-facing explanation in the User Guide where needed.
- Developer detail in GitBook documentation.
- API endpoint documentation or inventory entry.
- Database migration note when schema changes.
- Security/privacy note when personal or sensitive data is handled.
- Change entry in `CHANGELOG.md` for release-level work.

## SaQshi-Specific Answers

| Question | Answer |
| --- | --- |
| How is the application configuration-driven? | Frameworks, facility types, departments, checkpoints, indicators, formulas, validation and maps are driven through JSON configuration and database records. |
| How are NQAS/MusQan/LaQshya supported? | They are handled as quality-framework configurations, so the workflow can reuse the same engine with different framework data. |
| How are departments activated? | Facility users activate applicable departments for an active assessment before checklist, assessor information and CQI workflows. |
| How is assigned facility restricted? | Facility user workflows are scoped to the logged-in user's facility context. State/district/division/block users are scoped by administrative hierarchy. |
| How are duplicate active assessments prevented? | The create-assessment flow checks for an active assessment and blocks new creation until the current one is completed or cancelled. |
| How are scores calculated? | Assessment score is based on completed checkpoint score divided by total checkpoint score, then multiplied by 100. Revised scores are used where CQI/gap closure updates apply. |
| How are action plans and gap closures tracked? | CQI modules capture action plans, responsible role/post, target dates, evidence and closure/revised score status. |
| How is audit history maintained? | Event logging, certification history, update timestamps and workflow tables provide traceability. |
| How can another state reuse it? | Replace/configure facility master sample data, state boundary/map JSON, framework/config JSON, roles and deployment environment values. |
| Which licence is used? | GPL-3.0. |
| How is DPG readiness supported? | Open-source licence, documentation, standards mapping, privacy notes, non-PII sample data, reusable configuration and governance documents are maintained under `docs/compliance`. |

## Current Gaps and Improvements

| Area | Current Status | Implemented Improvement |
| --- | --- | --- |
| PSR-12 enforcement | Lightweight automated checks added | `tools/php_style_check.php` checks common style issues; future releases can add PHPCS/PHP CS Fixer for full PSR-12 enforcement. |
| JavaScript linting | Lightweight automated checks added | `tools/js_style_check.php` checks common JS issues until ESLint is adopted. |
| Unit tests | Initial focused tests added | `tools/run_unit_tests.php` runs tests under `tests/unit`, including encryption and score calculation tests. |
| CI/CD | GitHub quality workflow added | `.github/workflows/quality.yml` runs the quality gate on push and pull request. |
| Legacy naming | Migration policy documented | `docs/architecture/legacy_naming_migration.md` defines compatibility and migration rules. |

## Quality Gate Commands

Run the complete local quality gate from the project root:

```bash
php tools/quality_gate.php
```

Run individual checks:

```bash
php tools/php_syntax_check.php
php tools/json_syntax_check.php
php tools/php_style_check.php
php tools/js_style_check.php
php tools/run_unit_tests.php
php tools/release_readiness_check.php
```

Use strict mode when a release owner wants style warnings to fail:

```bash
php tools/php_style_check.php --strict
php tools/js_style_check.php --strict
```
