# Production Hardening Guide

Version: 1.0  
Updated: 2026-07-16  
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

## Application Hardening

- Set `APP_ENV=production` or equivalent production mode.
- Disable PHP error display to users.
- Return friendly API errors and log detailed errors server-side.
- Use strong database credentials.
- Rotate secrets after deployment handover.
- Confirm CSRF is enabled for protected write actions.
- Confirm session cookie settings are secure for HTTPS.

## Database Hardening

- Use a dedicated database user.
- Grant only required privileges.
- Keep database backups encrypted or access-controlled.
- Test restore before production go-live.
- Avoid exposing database ports publicly.

## Upload and File Hardening

- Validate extension, MIME type and size.
- Store uploads outside executable PHP paths where possible.
- Never execute uploaded files.
- Restrict evidence/certificate access by role.
- Add malware scanning if required by deployment policy.

## Monitoring

- Monitor web server errors.
- Monitor API/application logs.
- Monitor failed login attempts.
- Monitor database connectivity and backup status.
- Review event/audit logs for critical actions.

## Release Checklist

Before release:

- Run PHP lint.
- Run smoke tests for login, dashboard, assessment, CQI, performance and reports.
- Confirm `.env` is not committed.
- Confirm third-party license inventory.
- Confirm database migration and backup plan.
- Confirm rollback plan.

## Related Documents

- [Deployment Guide](../deployment/deployment_guide.md)
- [Backup and Restore Guide](../deployment/backup_restore_guide.md)
- [Security Policy](../../SECURITY.md)
- [SQL Injection Security Review](sql_injection_security_review.md)

