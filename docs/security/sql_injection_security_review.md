# SaQshi SQL Injection and Security Review

Version: 1.3
Updated: 2026-08-26

## Purpose

This document records SQL injection and security review findings before applying code changes. It should be updated whenever a security threat is discovered and rectified.

## Review Scope

Reviewed API PHP files for:

- Raw SQL query execution.
- User-controlled request values near SQL.
- Dynamic `WHERE`, `IN`, `ORDER BY` and `LIMIT` clauses.
- Endpoints using CSRF/session/authenticated access.
- Upload and report endpoints with special handling.

## API Page-by-page Query Coverage

The SQL query inventory is now published page by page for GitBook reviewers:

- [SQL Query Inventory](../database/sql_query_inventory.md)

It identifies every current API page that executes database queries, including
assessment lifecycle, checklist response, CQI, reports, framework and admin
endpoints, plus the shared services called by those pages. For each page it
records the query operation type, main records involved and source location.

Use the inventory when reviewing a new endpoint or a changed query. This review
records security findings and controls; the inventory records the full API
query map, so the two pages should be maintained together.

## Summary

| ID | Area | Risk | Status |
|---|---|---|---|
| SQLI-001 | `api/assessment/v1/action_plan.php` | Dynamic `IN ($ids)` query for action-plan suggestions | Rectified |
| SQLI-002 | `api/service/CertificationService.php` | Dynamic schema identifier use in maintenance DDL | Hardened |

## Rectification Update

Updated on: 2026-07-13

The confirmed SQL injection hardening item has been updated in code after documentation.

| ID | File | Before | After | Verification |
|---|---|---|---|---|
| SQLI-001 | `api/assessment/v1/action_plan.php` | `checkpoint_id IN ($ids)` built as a SQL string | `checkpoint_id IN (?, ?, ...)` generated with placeholders and bound values | `php -l api\assessment\v1\action_plan.php` passed |

### Current Status

SQLI-001 is closed. The action-plan suggestion query no longer places checkpoint IDs directly into the SQL string. The query now binds all checkpoint IDs and the framework code through a prepared statement.

SQLI-002 is closed for the current implementation. `CertificationService::ensureColumn()` is private and called with static identifiers, and it now validates table/column names using an alphanumeric/underscore allow-list before building schema-maintenance SQL.

## Focused Revalidation — 2026-08-26

This follow-up rechecked the two documented SQL-hardening controls and the
supporting regression checks after recent public-page and GitBook changes. It
was a non-destructive source and syntax review; it did not submit injection
payloads to a production or authenticated environment.

| Check | Result | Evidence |
|---|---|---|
| Action-plan suggestion syntax | Pass | `php -l api/assessment/v1/action_plan.php` completed without syntax errors. |
| Certification service syntax | Pass | `php -l api/service/CertificationService.php` completed without syntax errors. |
| Action-plan dynamic `IN` handling | Pass for source review | The SQL uses generated `?` placeholders; checkpoint IDs and framework code are bound with `bind_param()`. |
| Configuration JSON validation | Pass | `php tools/json_syntax_check.php` passed after removal of a UTF-8 BOM from `api/config/masters/facility_types_eduction_bihar.json`. |
| Unit regression checks | Pass | `php tools/run_unit_tests.php` reported 8 passed, 0 failed. |
| Public API safety baseline | Pass for availability | Captcha helper returned HTTP 200 for `GET` and HTTP 405 for an unsupported `HEAD` request. |

### Revalidation Limits

- This result does not prove every SQL query is free of injection risk.
- It does not replace a controlled authenticated test with malicious IDs,
  filter values, pagination values, search text and report parameters.
- Any new or changed query must still be added to the
  [SQL Query Inventory](../database/sql_query_inventory.md) and reviewed for
  prepared values or strict allow-listed identifiers.

## SQLI-001: Dynamic IN Clause in Action Plan Suggestions

### File

```text
api/assessment/v1/action_plan.php
```

### Finding

The action-plan suggestion query built an `IN` list as a string:

```php
$ids = implode(',', array_map('intval', array_keys($checkpointIds)));
...
WHERE checkpoint_id IN ($ids)
```

The values were cast to integers before being placed into SQL, so immediate exploitability was low. However, string-built SQL is still a security smell and can become risky if the upstream source changes later.

### Risk

- Future maintenance could accidentally allow unsanitized values into the same path.
- Static security scans may flag it as SQL injection risk.
- It is inconsistent with the rest of the file, which already uses prepared statements.

### Fix Applied

The `IN` list is now generated using prepared placeholders:

```php
WHERE checkpoint_id IN (?, ?, ...)
```

The checkpoint IDs and framework code are bound through `mysqli::prepare()` and `bind_param()`.

Implementation detail:

```php
$suggestionCheckpointIds = array_map('intval', array_keys($checkpointIds));
$suggestionPlaceholders = implode(',', array_fill(0, count($suggestionCheckpointIds), '?'));
$suggestionTypes = str_repeat('i', count($suggestionCheckpointIds)) . 's';
$stmtSuggestions->bind_param($suggestionTypes, ...$suggestionParams);
```

### Validation

Run:

```text
php -l api/assessment/v1/action_plan.php
```

Then test:

```text
GET /api/assessment/v1/action_plan.php?assessment_id=1
```

Expected result:

- No PHP syntax error.
- Action-plan data loads normally.
- Suggestions still load for matching checkpoints.

## General Security Controls Already Used

- Most database writes and reads use prepared statements.
- CSRF token is required for state-changing API calls.
- Session authentication is handled centrally.
- Friendly error handling avoids exposing raw database errors to users.
- `.env` is used for sensitive environment configuration.
- Login password transport uses encrypted `password_enc` instead of plain password.
- Upload endpoints validate file type and support delete of wrong uploads.
- The API page-by-page inventory makes query ownership traceable during code
  and security review.

## Recommended Future Hardening

- Avoid all string-built SQL unless the string is a trusted static schema operation.
- Whitelist any dynamic column, table, `ORDER BY` or report type values.
- Keep SQL helper methods for dynamic `IN` clauses to avoid repeating logic.
- Add automated Semgrep or similar static checks for SQL injection patterns.
- Add security test cases to Postman for invalid IDs, malicious strings and unauthorized scope access.
- In a dedicated test database, add controlled negative tests for action-plan
  suggestion parameters and dynamic list/sort/report filters; assert that
  queries remain parameterized and responses never include database details.
- Keep production PHP configured with `display_errors = Off`.
- Update `docs/database/sql_query_inventory.md` whenever an API page adds,
  removes or materially changes a database query.
