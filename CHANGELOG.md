# Changelog

All notable changes to SaQshi should be documented in this file.

This project follows a practical release log format inspired by Keep a Changelog. Formal semantic versioning should be adopted when SaQshi starts publishing tagged releases.

## Unreleased

### Added

- Facility assessment workflow pages and APIs.
- Department activation workflow.
- Assessor information workflow.
- Checklist scoring workflow with checkpoint-by-checkpoint entry.
- CQI gap analysis, action plan and gap closure workflows.
- Evidence upload/delete support for CQI workflows.
- Report dashboard, scorecard and progress report downloads.
- Performance monitoring module for KPI and outcome indicators.
- State monitoring dashboard and role-scoped state/district/block views.
- Certification status and certification map workflows.
- Facility drill-down and state reports.
- Indicator analytics for weak assessment indicators.
- API event abstraction for future event-driven/Kafka integration.
- API documentation, Postman collection and Swagger/OpenAPI files.
- Testing documentation: test plan, VAPT, load testing, black-box/white-box, WCAG.
- Open-source readiness documentation.
- Security policy, notice file, third-party attribution inventory, release checklist and database setup guide.

### Changed

- License metadata aligned to MIT across SaQshi-owned UI/config files to match `LICENSE.txt`.
- Dashboard/header accessibility controls now include text-size controls, screen reader mode, read page and stop speech.
- Screen reader mode now automatically speaks page content after route navigation.
- Error handling was improved to show friendlier messages instead of raw PHP/database errors in newer API paths.
- Database configuration moved to `.env` pattern.

### Security

- Added `SECURITY.md`.
- Added SQL injection review documentation.
- Added reminder to keep `.env`, generated keys, logs and uploads out of Git.

### Documentation

- Expanded `README.md`.
- Added `NOTICE`.
- Added `docs/compliance/third_party_licenses.md`.
- Added `docs/compliance/release_checklist.md`.
- Added `docs/database/database_setup_and_migration.md`.
- Added `docs/compliance/license_consistency_before_after.md`.

## 1.0.0-dev

Initial development baseline for SaQshi assessment, CQI, performance and state monitoring modules.
