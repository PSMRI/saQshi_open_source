# SaQshi Black-Box and White-Box Testing Guide

Version: 1.0  
Updated: 2026-07-13

## Purpose

This document explains how SaQshi should be tested using both black-box and white-box testing methods. It also records the initial tests executed in this workspace.

## Definitions

| Testing Type | Meaning in SaQshi | Who Can Perform |
|---|---|---|
| Black-box testing | Test the application from outside without reading code. Use browser, Postman, API URLs, reports and UI workflows. | QA, user, tester, product owner |
| White-box testing | Test internal code, database logic, validation, services, SQL safety, syntax and security controls. | Developer, technical tester |

## Black-Box Testing Scope

Black-box testing should cover what the user can see or call:

- Login, captcha and encrypted password flow.
- Facility dashboard.
- Assessment creation, cancellation and completion.
- Department activation.
- Assessor information.
- Checklist response entry.
- Gap analysis.
- CQI action plan and closure.
- Evidence upload and delete.
- Score/progress reports.
- KPI and outcome entry.
- Performance dashboard/trend/download.
- State, regional, district and block monitoring pages.
- Certification status, map, category, facility drill-down and state reports.
- Error messages and friendly failure handling.

## White-Box Testing Scope

White-box testing should cover internal implementation:

- PHP syntax validation.
- JavaScript syntax validation.
- Prepared statements and SQL injection review.
- CSRF enforcement for state-changing APIs.
- Session and role-scope enforcement.
- File upload extension, MIME, size and path traversal checks.
- Report download scope.
- Password hashing/encryption behavior.
- `.env` and secret handling.
- Friendly error handling and event logging.
- Pagination for large state-level lists.
- Service methods and formula calculations.

## Black-Box Test Cases

| ID | Area | Test | Steps | Expected Result | Priority |
|---|---|---|---|---|---|
| BB-AUTH-001 | Auth | Login page opens | Open `/ui/login.html` | Login UI loads with captcha | P0 |
| BB-AUTH-002 | Auth | Invalid login | Enter wrong credential/captcha | Friendly error; no raw PHP/DB error | P0 |
| BB-AUTH-003 | Auth | Valid login | Login with valid facility user | Facility dashboard opens | P0 |
| BB-AUTH-004 | Auth | Monitoring login | Login role 9/5/4/8 | Monitoring sidebar only, scoped pages open | P0 |
| BB-ASM-001 | Assessment | Create assessment rule | Create when no active assessment exists | Assessment created | P0 |
| BB-ASM-002 | Assessment | Active assessment rule | Try create while active exists | Creation blocked with friendly message | P0 |
| BB-DEP-001 | Department | Activation list | Open Departments page | Departments load with active/inactive status | P0 |
| BB-DEP-002 | Department | Activate department | Click activate | Status updates; button locks if active | P0 |
| BB-ASSR-001 | Assessor | Save assessor info | Fill assessor/assessee/date/type | Data saves and reloads | P1 |
| BB-CHK-001 | Checklist | Load checkpoint | Select department/area/subtype | Checkpoint loads one by one | P0 |
| BB-CHK-002 | Checklist | Save response | Save 0, 1 and 2 responses | Scores save and progress updates | P0 |
| BB-CQI-001 | Gap | Gap analysis | Open gap analysis after checklist | Non-compliant/partial checkpoints appear | P1 |
| BB-CQI-002 | Action Plan | Save action plan | Enter plan/responsible/target date | Action plan saves | P1 |
| BB-CQI-003 | Closure | Close gap | Add revised score/remarks/evidence optional | Closure saves and score updates | P1 |
| BB-FILE-001 | Upload | Allowed file | Upload image/PDF/doc/xls | File accepted and URL returned | P1 |
| BB-FILE-002 | Upload | Delete wrong file | Delete uploaded evidence | File removed or delete success response | P1 |
| BB-PERF-001 | Performance | KPI entry | Select month/department and save KPI | Entry saved | P1 |
| BB-PERF-002 | Performance | Outcome entry | Select month/department and save outcome | Entry saved | P1 |
| BB-RPT-001 | Reports | Scorecard download | Download score report | Excel contains live checklist data | P0 |
| BB-RPT-002 | Reports | State report scope | District/block user downloads report | Only scoped data appears | P0 |
| BB-MON-001 | Monitoring | State dashboard | Open state dashboard | Current month and category/status cards load | P1 |
| BB-MON-002 | Monitoring | Pagination | Open large list page | Data loads page-wise; no browser freeze | P1 |
| BB-ERR-001 | Error | Friendly failure | Trigger missing parameter | JSON/UI shows friendly validation message | P0 |

## White-Box Test Cases

| ID | Area | Test | Method | Expected Result | Priority |
|---|---|---|---|---|---|
| WB-PHP-001 | Syntax | PHP syntax scan | `php -l` over all `api/**/*.php` | No syntax errors | P0 |
| WB-JS-001 | Syntax | JS syntax scan | `node -c` for utility scripts | No syntax errors | P1 |
| WB-SQL-001 | SQL Injection | Raw SQL review | Search `query()`, dynamic `IN`, `ORDER BY`, `LIMIT` | User input is bound or whitelisted | P0 |
| WB-SQL-002 | SQL Injection | Action plan suggestion query | Inspect `api/assessment/v1/action_plan.php` | Dynamic `IN` uses placeholders | P0 |
| WB-CSRF-001 | CSRF | State-changing APIs | Inspect API bootstrap/headers | POST/PUT/DELETE require CSRF | P0 |
| WB-AUTH-001 | Auth | Role scope | Inspect state `_bootstrap.php` and service scope | Role 9/5/4/8 scopes applied centrally | P0 |
| WB-FILE-001 | Upload | Path traversal | Inspect delete/upload path resolution | File operations stay inside uploads | P0 |
| WB-ERR-001 | Error Handling | DB/PHP error leakage | Inspect `Response`, `ErrorHandler`, bootstrap | User sees friendly message | P0 |
| WB-CONFIG-001 | Secrets | DB credentials | Inspect config loading | Secrets loaded from `.env`, not committed code | P0 |
| WB-PERF-001 | Pagination | State list services | Inspect API list responses | Page/per_page supported on large lists | P1 |
| WB-EVENT-001 | Audit/Event | Event abstraction | Inspect `api/core/Event.php` and bootstrap | Request events are logged | P2 |

## Initial Test Execution Results

Executed on: 2026-07-13

### Black-Box Smoke Test

Command:

```text
node scripts\load-test\saqshi-load-test.js --url http://localhost:94/api/auth/v1/csrf.php --duration 5 --concurrency 2 --output docs\testing\load_test_results\blackbox-smoke-csrf.json
```

Result:

| Metric | Value |
|---|---:|
| Total requests | 360 |
| Requests per second | 68.88 |
| Failures | 0 |
| Failure rate | 0% |
| HTTP 200 responses | 360 |
| Average latency | 27.85 ms |
| p95 latency | 57.47 ms |
| Max latency | 508.19 ms |

Status: Passed.

Result file:

```text
docs/testing/load_test_results/blackbox-smoke-csrf.json
```

### White-Box PHP Syntax Test

Command:

```text
php -l over all api/**/*.php
```

Result:

```text
PHP syntax OK: 146 files
```

Status: Passed.

### White-Box JavaScript Syntax Test

Command:

```text
node -c scripts\load-test\saqshi-load-test.js
```

Result: No syntax errors.

Status: Passed.

## Recommended Testing Order

1. Run white-box syntax checks after every code change.
2. Run SQL/security review for any file that touches SQL or file upload.
3. Run black-box API smoke tests.
4. Run browser workflow tests for facility user.
5. Run browser workflow tests for state/regional/district/block users.
6. Run report download checks.
7. Run moderate load tests only on a test environment.

## Limitations

The initial execution in this workspace was safe and non-destructive. It did not:

- Submit destructive POST load tests.
- Run automated browser tests across every UI route.
- Run scanner-based VAPT tools.
- Test production-level concurrency.
- Modify real assessment/performance/certification data.

These should be performed on a dedicated test environment with test users and test data.

