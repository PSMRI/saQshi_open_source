# SaQshi VAPT Report and Security Test Cases

Version: 1.1  
Updated: 2026-07-16

Security update note, 2026-07-16:

- Release security scan completed and documented in `docs/security/release_security_scan_2026_07_16.md`.
- Local `.env`, runtime uploads, event logs and generated key files were removed from the public source folder.
- Raw password transport helper in `ui/assets/js/core/auth.js` now fails closed; login uses `password_enc`.
- Upload validation now uses extension-specific MIME checks and image content validation.
- Dynamic schema identifier handling in `api/service/CertificationService.php` now validates table/column identifiers.

Security update note, 2026-07-13:

- SQLI-001 was documented and rectified in `api/assessment/v1/action_plan.php`.
- The previous dynamic `checkpoint_id IN ($ids)` action-plan suggestion query now uses prepared placeholders and bound parameters.
- Detailed record: `docs/security/sql_injection_security_review.md`.
Assessment type: Safe local static VAPT review and test-case preparation  
Scope: `api`, `ui`, authentication, session, role scope, file upload, reports, monitoring APIs, documentation

## 1. Executive Summary

A safe local VAPT review was performed without destructive exploitation. The review focused on common OWASP-style risks: authentication, authorization, role-scope leakage, secrets exposure, file upload, error handling, security headers, CSRF, report/download access and legacy endpoints.

Overall posture: **Improving, but release should require closure of high-risk items and execution of the VAPT test cases below.**

## 2. VAPT Scope

| Area | Included |
|---|---|
| Authentication | Login, captcha, encrypted password transport, legacy login endpoints |
| Authorization | Facility user vs monitoring roles 9, 5, 4, 8 |
| State monitoring scope | State, regional/division, district and block filtering |
| File upload | Evidence upload/delete controls |
| Reports | Facility, assessment, CQI, performance, certification and scorecard downloads |
| Error handling | PHP/SQL error exposure and user-friendly messages |
| Secrets | `.env`, DB credentials and ignored files |
| Security headers | API response headers and CSP |

## 3. Summary of Findings

| ID | Severity | Finding | Current status | Recommendation |
|---|---|---|---|---|
| VAPT-F-001 | High | Local `.env` contained real DB username/password. `.env` is ignored by git, but credentials were visible in local workspace. | Fixed for public source folder | `.env` removed from `open_source`; rotate any credentials that were previously present before deployment/sharing. |
| VAPT-F-002 | High | Legacy endpoint `api/auth/v1/login1.php` accepted raw `password` field. | Fixed | Endpoint now returns 410 and directs callers to encrypted `login.php`. Remove references to old endpoint. |
| VAPT-F-003 | Medium | Upload allowed broad `application/octet-stream` and `application/zip` matches for all allowed extensions. | Improved | Extension-specific MIME validation and image validation added; production antivirus scanning still recommended. |
| VAPT-F-004 | Medium | Uploaded files are under `/uploads`; direct public access may expose evidence URLs if guessed/shared. | Open | Consider access-controlled download endpoint for sensitive evidence. |
| VAPT-F-005 | Medium | Some dynamic DDL/query usages exist, mostly table/column maintenance. | Improved | `CertificationService::ensureColumn()` now validates identifiers; keep future dynamic identifiers allow-listed. |
| VAPT-F-006 | Low | CSP is strong for APIs, but full UI pages may need separate CSP review if inline scripts remain. | Open | Add UI-level CSP plan before production hardening. |

## 4. Positive Controls Observed

| Control | Evidence |
|---|---|
| Error display disabled | `api/bootstrap.php` sets `display_errors = 0` and `log_errors = 1`. |
| Friendly server errors | `api/core/Response.php` and `api/core/ErrorHandler.php` return request IDs without raw SQL/PHP details. |
| Security headers | `api/core/Security.php` sets X-Frame-Options, X-Content-Type-Options, Referrer-Policy, CSP and related headers. |
| CSRF for state-changing requests | `api/auth_api.php` validates CSRF for POST/PUT/PATCH/DELETE. |
| Password transport | Current `api/auth/v1/login.php` requires `password_enc` and captcha. |
| File delete path safety | `api/files/v1/delete.php` resolves path under uploads and blocks traversal outside uploads. |
| `.env` ignored | `.gitignore` ignores `.env` and `.env.*` while allowing `.env.example`. |
| Monitoring role scope | `StateDashboardService::applyMonitoringScope()` scopes role 9/5/4/8 data. |

## 5. VAPT Test Cases

### Authentication and Session

| ID | Test | Steps | Expected result | Severity |
|---|---|---|---|---|
| VAPT-AUTH-001 | Raw password not accepted | POST to `/api/auth/v1/login.php` with `password` but no `password_enc` | 422 validation error for `password_enc` | High |
| VAPT-AUTH-002 | Legacy login disabled | POST to `/api/auth/v1/login1.php` | HTTP 410 with disabled endpoint message | High |
| VAPT-AUTH-003 | Captcha required | POST valid username/password_enc without captcha | Validation error, login blocked | High |
| VAPT-AUTH-004 | Brute force lockout | Attempt invalid login repeatedly | Lockout/friendly failure after configured threshold | High |
| VAPT-AUTH-005 | Session hijack hardening | Reuse old/stolen session after logout | Unauthorized | High |
| VAPT-AUTH-006 | CSRF missing | POST to protected API without CSRF token | Request blocked | High |

### Authorization and Scope

| ID | Test | Steps | Expected result | Severity |
|---|---|---|---|---|
| VAPT-AUTHZ-001 | Facility user cannot access state API | Login facility user, call `/api/state/v1/dashboard.php` | 403 forbidden | High |
| VAPT-AUTHZ-002 | District user cannot view other district data | Login role 4, call certification/assessment/performance pages | Only assigned district data returned | Critical |
| VAPT-AUTHZ-003 | Block user cannot view other block data | Login role 8, search facility outside block | No out-of-scope result | Critical |
| VAPT-AUTHZ-004 | Regional user scoped to division | Login role 5, open reports/downloads | Only assigned division data appears | Critical |
| VAPT-AUTHZ-005 | State user full data | Login role 9 | Full configured state data visible | Medium |
| VAPT-AUTHZ-006 | Direct URL access | Paste facility-only route as monitoring user | Facility menus/actions not available or API blocks unauthorized state-changing call | High |

### Input Validation and Injection

| ID | Test | Steps | Expected result | Severity |
|---|---|---|---|---|
| VAPT-INJ-001 | SQL injection login username | Submit `' OR '1'='1` as username | Login fails; no SQL error | Critical |
| VAPT-INJ-002 | SQL injection search | Use SQL payload in facility/NIN search | No SQL error; scoped results only | High |
| VAPT-INJ-003 | XSS in remarks/action plan | Save `<script>alert(1)</script>` in remarks | Rendered as text or escaped, no script execution | High |
| VAPT-INJ-004 | Formula injection in CSV/Excel | Save value beginning with `=cmd|...` in fields exported to CSV/XLSX | Export neutralizes or treats as text | High |
| VAPT-INJ-005 | Invalid JSON | POST malformed JSON to API | 400 friendly JSON error | Medium |

### File Upload and Delete

| ID | Test | Steps | Expected result | Severity |
|---|---|---|---|---|
| VAPT-FILE-001 | Disallowed extension | Upload `.php`, `.exe`, `.js` | Rejected | Critical |
| VAPT-FILE-002 | MIME mismatch | Rename PHP script to `.jpg` | Rejected by MIME/content checks | High |
| VAPT-FILE-003 | Oversized file | Upload file > 10 MB | Rejected | Medium |
| VAPT-FILE-004 | Path traversal delete | Delete `../../api/assets/conn/db.php` via file delete API | Rejected | Critical |
| VAPT-FILE-005 | Delete outside uploads | Delete absolute/non-upload path | Rejected | Critical |
| VAPT-FILE-006 | Upload evidence optional | Save CQI without evidence | Should still save if evidence optional | Low |

### Error Handling and Information Disclosure

| ID | Test | Steps | Expected result | Severity |
|---|---|---|---|---|
| VAPT-ERR-001 | DB connection failure | Temporarily point DB to invalid host in safe environment | Friendly message with request ID, no password/path/SQL | Critical |
| VAPT-ERR-002 | PHP warning path disclosure | Trigger known validation failure | No filesystem path in user response | High |
| VAPT-ERR-003 | 500 response schema | Force server error in test env | Standard JSON with request ID | Medium |
| VAPT-ERR-004 | Browser console | Navigate pages with failed API | Friendly UI empty/error state, no raw stack to user | Medium |

### Reports and Downloads

| ID | Test | Steps | Expected result | Severity |
|---|---|---|---|---|
| VAPT-RPT-001 | Scoped report download | Login role 4/5/8 and download all state reports | Downloads contain only assigned scope | Critical |
| VAPT-RPT-002 | Unauthorized download | Facility user calls `/api/state/v1/reports.php?download=facilities` | 403 forbidden | Critical |
| VAPT-RPT-003 | Report data leakage | Search/download with broad query as district user | No out-of-scope facilities | Critical |
| VAPT-RPT-004 | Excel formula injection | Export fields containing formula-like text | Exported as safe text | High |

### Security Headers

| ID | Test | Steps | Expected result | Severity |
|---|---|---|---|---|
| VAPT-HDR-001 | API headers | Inspect response headers | X-Frame-Options DENY, nosniff, CSP present | Medium |
| VAPT-HDR-002 | HTTPS HSTS | Deploy on HTTPS and inspect headers | HSTS present | Medium |
| VAPT-HDR-003 | Clickjacking | Try embedding API/page in iframe | Blocked by frame headers/CSP | Medium |

## 6. Immediate Remediation Checklist

1. Rotate any DB password that was previously present in local `.env` before any sharing or deployment.
2. Keep `.env` and `api/storage/keys` out of git.
3. Confirm no code references `api/auth/v1/login1.php`.
4. Consider replacing public evidence URLs with an authenticated download endpoint.
5. Review Office upload MIME handling and add antivirus scanning in production.
6. Execute role-scope report downloads for role IDs 9, 5, 4 and 8.
7. Add formula-injection hardening to CSV/XLSX exports if not already handled.

## 7. VAPT Execution Status

| Check | Status | Notes |
|---|---|---|
| Static secret scan | Completed | Public source folder now has no `.env`, runtime logs, generated keys or upload evidence files. |
| Error handling review | Completed | Friendly error handler exists. |
| Legacy login check | Fixed | `login1.php` now disabled with 410 response. |
| Upload review | Improved | Extension-specific MIME checks and image validation exist; production malware scanning recommended. |
| Scope review | Completed | Shared state bootstrap applies role scope. |
| Active exploit testing | Not performed | Requires explicit test environment and approval. |
