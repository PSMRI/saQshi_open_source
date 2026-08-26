# Changelog

All notable changes to SaQshi should be documented in this file.

This project follows a practical release log format inspired by Keep a Changelog. Formal semantic versioning should be adopted when SaQshi starts publishing tagged releases.

## Unreleased

### 2026-08-26 Release-Preparation Update

#### Added

- Profile-aware public landing-page verification for Healthcare and Education deployments, plus public deployment-branding API documentation.
- A digital healthcare team landing-page image asset and descriptive alternative text.
- Public-pages and GitBook revalidation evidence, expanded VAPT follow-up cases, and refreshed black-box, white-box, load, non-functional and accessibility testing documentation.
- Public API access-boundary documentation for captcha and deployment branding, plus an updated endpoint inventory and source-reference entry for assessment-period policy.
- Release-safe database dictionary, sanitized-schema, migration-history, data-classification and non-PII seed-data guidance.

#### Changed

- Web-server default-document configuration no longer forces `ui/login.html`; the root route can serve the profile-aware public landing page.
- Healthcare deployments continue on `index.html`; Education deployments redirect to `education-index.html` after the public branding profile check.
- User, assessor/DPO, UI deployment, deployment-profile, Memurai session, Open Source/DPG, API, architecture and database GitBook pages were refreshed for the current development baseline.
- The API and deployment documentation now distinguish public presentation data from authenticated configuration and operational data.
- The version matrix now records deployment profiles, public landing routing, non-PII sample exports and FHIR R4 assessment-summary export as Open Source baseline capabilities.

#### Security

- Documented the static public-page CSP/clickjacking-header gap as an open VAPT follow-up item; API-only header evidence is not treated as closure.
- Reinforced release checks for secrets, runtime data, session configuration, non-PII exports and role-scoped API access.

#### Verification

- Confirmed local public root, GitBook documentation and public deployment-branding routes return HTTP 200 during focused revalidation.
- JSON syntax, focused PHP syntax and repository style/unit checks were recorded in the related testing/security evidence. Active UAT VAPT, manual accessibility checks, legal/privacy sign-off and final release-manifest review remain release gates.

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

- License metadata aligned to GPL-3.0 across SaQshi-owned UI/config files to match `LICENSE`.
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
