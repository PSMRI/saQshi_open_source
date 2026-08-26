# Release Security Scan

Version: 1.1
Scan date: 2026-07-16  
Documentation status reviewed: 2026-08-26
Scope: `open_source/api`, `open_source/ui`, `open_source/tools`, `open_source/scripts`

## Purpose

This record documents the release security scan performed before public open-source/DPG readiness review.

## Commands Run

```text
rg -n "DB[_]PASSWORD=|password\s*[:=]|api[_-]?key|secret\s*[:=]|BEGIN RSA|BEGIN OPENSSH|BEGIN PRIVATE|private\.pem|\.env" open_source --glob "!api/storage/**" --glob "!uploads/**" --glob "!*.png" --glob "!*.jpg" --glob "!*.jpeg" --glob "!*.docx" --glob "!*.xlsx" --glob "!*.zip"
```

```text
Get-ChildItem open_source\api,open_source\tools -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

```text
Get-ChildItem open_source\ui -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
```

```text
rg -n "mysqli_query\s*\(|->query\s*\(|prepare\s*\(|bind_param\s*\(" open_source\api --glob "*.php"
```

```text
rg -n "move_uploaded_file|finfo|mime|extension|unlink\s*\(|realpath\s*\(|uploads|MAX_FILE|allowed" open_source\api\files open_source\api open_source\ui\assets\js\core\upload.js --glob "*.php" --glob "*.js"
```

```text
php tools\release_readiness_check.php
```

## Results

| Area | Result | Notes |
|---|---|---|
| Secret scan | Passed with documentation/example hits only | No committed `.env`, private key, runtime log or upload file found in release folder. `.env.example` contains `change_me` placeholder only. |
| PHP syntax | Passed | All PHP files under `api/` and `tools/` passed `php -l`. |
| JavaScript syntax | Passed | All JS files under `ui/` passed `node --check`. |
| Raw password transport | Hardened | `ui/assets/js/core/auth.js` no longer sends raw `password`; raw helper now fails closed. Login API requires `password_enc`. |
| Auth/session/CSRF | Reviewed | `auth_api.php` starts secure session, requires login and validates CSRF for POST/PUT/PATCH/DELETE. |
| Upload validation | Hardened | `api/files/v1/upload.php` now uses extension-specific MIME validation and image content validation. |
| Delete path traversal | Reviewed | `api/files/v1/delete.php` restricts delete paths to the local `uploads/` tree using `realpath`. |
| SQL execution sites | Reviewed | Most database operations use prepared statements. Static schema DDL remains for table/column creation. Dynamic schema identifier handling in `CertificationService::ensureColumn()` now validates identifiers. |
| Friendly error handling | Improved | Server errors are returned through friendly JSON with request IDs. Legacy array-returning services now sanitize low-level database/system messages, and the release checker flags direct raw exception/database output patterns. |
| Release readiness checker | Passed with review warnings | Remaining warnings are non-code release items: clean-install validation, final sign-offs, UAT VAPT and large asset review. |

## Focused Follow-up — 2026-08-26

This non-destructive follow-up covered recent public-page, GitBook and
configuration changes. It is not a full replacement for the 2026-07 source
scan or a UAT VAPT.

| Area | Result | Notes |
|---|---|---|
| Configuration JSON | Passed | `php tools/json_syntax_check.php` passed after removal of a UTF-8 BOM from `api/config/masters/facility_types_eduction_bihar.json`. |
| PHP and JavaScript style | Passed | `php tools/php_style_check.php` and `php tools/js_style_check.php` passed. |
| Unit regression tests | Passed | `php tools/run_unit_tests.php` reported 8 passed, 0 failed. |
| Release readiness | Passed with review warnings | Runtime/private data, local logs, key files and large assets remain deployment-hygiene items that must not be included in a public release. |
| Root document route | Passed | `GET /` returned HTTP 200 and served the profile-aware healthcare landing page rather than the login shell. |
| GitBook documentation | Passed | `GET /gitbook.html` returned HTTP 200. |
| Captcha method guard | Passed | `GET` returned text-math JSON; unsupported `HEAD` returned HTTP 405. |
| SQL hardening regression | Passed for source/syntax review | Action-plan placeholder binding and certification identifier validation remain documented in the SQL security review. |

### Follow-up Limits

- The full secret-scan command from the original scan was not rerun as part of
  this focused follow-up.
- The release-readiness output identified local runtime logs, uploaded data and
  key files for review. These are not evidence that they are tracked for
  release; confirm the final build manifest and ignore rules before publishing.
- No active exploit, upload-malware, authenticated authorization-bypass or
  production-load test was performed.

## Code Changes Applied During Scan

| File | Change |
|---|---|
| `ui/assets/js/core/auth.js` | Disabled raw password login helper and fail-closed generic login binding. |
| `api/auth/v1/login.php` | Event metadata now tracks `password_enc` presence instead of raw `password`. |
| `api/files/v1/upload.php` | Replaced global ZIP/octet-stream MIME allowance with extension-specific MIME map and image validation. |
| `api/service/CertificationService.php` | Added schema identifier validation before dynamic `SHOW COLUMNS`/`ALTER TABLE` maintenance SQL. |
| `api/service/DynamicAssessmentService.php` | Added sanitization for legacy service errors before returning API JSON arrays. |
| `api/service/DepartmentStatusService.php` | Added sanitization for legacy service errors before returning API JSON arrays. |
| `tools/release_readiness_check.php` | Added checks for missing page assets and direct raw exception/database error output patterns. |

## Remaining Security Review Items

| Item | Status | Required Action |
|---|---|---|
| Authenticated evidence download endpoint | Open | Uploaded evidence URLs still point under `/uploads`. For sensitive evidence, prefer a download endpoint that checks session/role/facility ownership. |
| Antivirus/malware scanning | Open | Add production malware scanning for uploaded Office/PDF/image files. |
| Final VAPT execution | Pending environment | The current pass is static/local. Run active VAPT test cases in a controlled UAT environment before public production release. |
| Sanitized base schema | Added / clean-install validation recorded | `api/sql/schema/001_base_schema.sql` and the clean-install validation record are documented in `docs/database/database_setup_and_migration.md`. |
| Maintainer/security contacts | Done for current public package | `MAINTAINERS.md` records Tech4Gov Team / Piramal Swasthya / `tech4gov@piramalswasthya.org`. |
| Data redistribution approval | Done for current public source package | `docs/compliance/data_redistribution_approval.md` records facility-data, framework/checklist, outcome and map/boundary decisions. |

## Release Decision

Security scan status: **Passed with review warnings**.

The 2026-07 scan found no immediate committed secret, PHP syntax, JavaScript
syntax, raw password transport, obvious upload traversal issue or direct raw
server-error exposure in its scanned source. The 2026-08-26 focused follow-up
passed the listed regression checks. Public release should still wait for a
fresh full secret scan, final build-manifest review, remaining non-code release
approvals and the final UAT VAPT pass.
