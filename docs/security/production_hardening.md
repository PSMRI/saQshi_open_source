# Production Hardening Guide

Version: 1.1
Updated: 2026-08-26
License: GPL-3.0

## Purpose

This guide lists minimum hardening steps before deploying SaQshi in production.

## Server Hardening

- Use HTTPS for all production traffic.
- Disable directory listing.
- Deny public access to `.env`, backups, logs, keys and private storage.
- Keep PHP, web server and database patched.
- Use least-privilege filesystem permissions.
- Configure request size limits for uploads.
- Enable log rotation.
- Disable directory listing for `uploads/` and runtime storage folders.
- Serve the profile-aware root `index.html` as the default document; do not set
  `ui/login.html` as the server default document, or it will bypass healthcare/
  education landing-page selection.
- Restrict direct web access to `api/storage/`, `uploads/` private evidence,
  backup archives, development output and temporary folders. Serve sensitive
  evidence through an authenticated, scope-checked download route.

## Application Hardening

- Set `APP_ENV=production` or equivalent production mode.
- Disable PHP error display to users.
- Return friendly API errors and log detailed errors server-side.
- Use strong database credentials.
- Set a long random `SAQSHI_FIELD_ENCRYPTION_KEY` and keep it stable for encrypted user profile fields.
- Rotate secrets after deployment handover.
- Confirm CSRF is enabled for protected write actions.
- Confirm session cookie settings are secure for HTTPS.
- Select and validate the deployment profile (`healthcare`, `education` or
  `generic-inspection`) before creating production assessments. Confirm the
  configured profile and modules through the deployment API after release.
- Keep GitBook documentation public only when its documents contain no private
  operational data, credentials, test session cookies or internal export files.

## Database Hardening

- Use a dedicated database user.
- Grant only required privileges.
- Keep database backups encrypted or access-controlled.
- Confirm encrypted user profile columns have been widened with `api/sql/schema/user_profile_encryption_columns.sql`.
- Confirm encrypted assessor/assessee columns have been widened with `api/sql/schema/assessor_info_encryption_columns.sql`.
- For existing deployments, run `php scripts/encrypt_existing_user_profile_fields.php` and `php scripts/encrypt_existing_assessor_info_fields.php` once after backup.
- Test restore before production go-live.
- Avoid exposing database ports publicly.

## Upload and File Hardening

- Validate extension, MIME type and size.
- Store uploads outside executable PHP paths where possible.
- Never execute uploaded files.
- Restrict evidence/certificate access by role.
- Use authenticated download endpoints or non-public storage for sensitive evidence where required.
- Apply evidence retention/deletion schedule from `docs/security/evidence_upload_and_log_retention.md` or an approved local policy before go-live.
- Add malware scanning if required by deployment policy.

## Monitoring

- Monitor web server errors.
- Monitor API/application logs.
- Monitor failed login attempts.
- Monitor database connectivity and backup status.
- Review event/audit logs for critical actions.
- Configure log retention/rotation and restrict log access to authorized technical/security users. Default retention is application error logs 90 days, API/event logs 180 days and security incident logs 1 year after incident closure unless local policy overrides it.
- Confirm logs do not contain passwords, tokens, captcha values, secrets or patient-identifiable information.

## Release Checklist

Before release:

- Run PHP lint.
- Run smoke tests for login, dashboard, assessment, CQI, performance and reports.
- Confirm `.env` is not committed.
- Confirm third-party license inventory.
- Confirm database migration and backup plan.
- Confirm rollback plan.

### Current Validation Commands

Run these from the application root on a staging deployment before release:

```text
php tools/php_syntax_check.php
php tools/json_syntax_check.php
php tools/php_style_check.php
php tools/js_style_check.php
php tools/run_unit_tests.php
php tools/release_readiness_check.php
php api/cli/deployment-readiness.php
```

Then verify the intended public surface:

```text
GET {main_url}/
GET {main_url}/gitbook.html
GET {main_url}/ui/login.html
GET {main_url}/api/auth/v1/captcha.php
```

Expected results:

- `/` serves the configured profile-aware landing page.
- `/gitbook.html` and documentation links return only approved public content.
- `/ui/login.html` is reachable but is not the web-server default document.
- The captcha endpoint returns text-math JSON for `GET` and rejects unsupported
  methods.

### Release-readiness Warnings Require Resolution

Treat every `Passed with review warnings` result as a release gate, not a clean
approval. In particular, confirm that local runtime logs, uploaded data, key
files and backup/export artifacts are excluded from the deployment package and
from source-control publication. Large public assets should be reviewed for
size, licensing and intended distribution.

## Related Documents

- [Deployment Guide](../deployment/deployment_guide.md)
- [Backup and Restore Guide](../deployment/backup_restore_guide.md)
- [Security Policy](../../SECURITY.md)
- [SQL Injection Security Review](sql_injection_security_review.md)
- [Evidence Upload and Log Retention](evidence_upload_and_log_retention.md)
