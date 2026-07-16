# Release Security Scan

Version: 1.0  
Scan date: 2026-07-16  
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
| Release readiness checker | Passed with review warnings | Remaining warnings are non-code release items: sanitized schema, data redistribution approvals, maintainer contacts and large asset review. |

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
| Sanitized base schema | Open | Add `api/sql/schema/001_base_schema.sql`. |
| Maintainer/security contacts | Open | Complete `MAINTAINERS.md`. |
| Data redistribution approval | Open | Complete `docs/compliance/data_redistribution_approval.md`. |

## Release Decision

Security scan status: **Passed with review warnings**.

No immediate committed secret, PHP syntax, JavaScript syntax, raw password transport, obvious upload traversal issue or direct raw server-error exposure remains in the scanned source. Public release should still wait for the remaining non-code release approvals and the final UAT VAPT pass.
