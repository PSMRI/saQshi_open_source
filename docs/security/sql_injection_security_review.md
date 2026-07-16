# SaQshi SQL Injection and Security Review

Version: 1.1  
Updated: 2026-07-16

## Purpose

This document records SQL injection and security review findings before applying code changes. It should be updated whenever a security threat is discovered and rectified.

## Review Scope

Reviewed API PHP files for:

- Raw SQL query execution.
- User-controlled request values near SQL.
- Dynamic `WHERE`, `IN`, `ORDER BY` and `LIMIT` clauses.
- Endpoints using CSRF/session/authenticated access.
- Upload and report endpoints with special handling.

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

## Recommended Future Hardening

- Avoid all string-built SQL unless the string is a trusted static schema operation.
- Whitelist any dynamic column, table, `ORDER BY` or report type values.
- Keep SQL helper methods for dynamic `IN` clauses to avoid repeating logic.
- Add automated Semgrep or similar static checks for SQL injection patterns.
- Add security test cases to Postman for invalid IDs, malicious strings and unauthorized scope access.
- Keep production PHP configured with `display_errors = Off`.
