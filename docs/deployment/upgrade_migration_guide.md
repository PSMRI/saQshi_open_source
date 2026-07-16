# Upgrade and Migration Guide

This guide explains how to move between SaQshi versions. It is intentionally conservative: take a backup first, apply changes in a test environment, then deploy to production.

## Version Path

| Version | Notes |
| --- | --- |
| MVP | Checklist-focused assessment build |
| V1 | Adds multiple facility types, action plans, KPI/outcome and dashboards |
| V2 | Adds fuller state monitoring, certification, improved reports and UI/UX |
| Open Source | Adds public documentation, GPL-3.0 licensing, test artifacts, deployment guidance and open-source readiness files |

## Before Upgrade

1. Confirm current running version.
2. Back up database.
3. Back up `uploads/`.
4. Back up `.env`.
5. Back up any local JSON configuration changes.
6. Review `CHANGELOG.md`.
7. Review database migration scripts.
8. Test upgrade in a staging copy.

## Migration Checklist

| Area | Check |
| --- | --- |
| Database | New tables and columns are applied |
| API | Endpoint paths still match UI calls |
| UI | Sidebar routes and page JSON files load |
| Config | Framework, facility and performance JSON remain valid |
| Reports | Download formats still generate |
| Roles | Facility/state/district/block scopes still work |
| Uploads | Evidence and certificate files remain accessible |

## Database Migration Rules

- Prefer additive migrations: add tables/columns before removing anything.
- Preserve existing assessment responses.
- Preserve certification history.
- Preserve uploaded evidence paths.
- Use transaction-safe scripts where possible.
- Record migration date and operator.

## Rollback Plan

1. Stop application access.
2. Restore previous application files.
3. Restore previous database backup.
4. Restore previous uploads if changed.
5. Restore previous `.env` if changed.
6. Verify login, dashboard and one sample report.

## After Upgrade

| Check | Expected Result |
| --- | --- |
| Login | Works for facility and monitoring roles |
| Assessment | Active assessment loads |
| Checklist | Existing responses remain visible |
| CQI | Action plans and closures remain visible |
| Performance | KPI/outcome monthly data loads |
| Certification | History and current status load |
| State monitoring | Counts and pagination work |
| Reports | Downloads generate correctly |
