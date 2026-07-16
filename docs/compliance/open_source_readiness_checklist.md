# SaQshi Open Source Readiness Checklist

Version: 1.1  
Reviewed: 2026-07-16  
Repository path: project root (`D:\sAQshi_new_27112025\up\SaQSHI_Main\open_source`)

## Executive Summary

SaQshi is **open-source capable and close to public-release readiness**, but it should still complete final release verification before publishing as a clean public open-source distribution.

The project includes a GPL-3.0 open-source license at the repository root, contribution guidance, a code of conduct, API documentation, testing documents, VAPT notes, WCAG notes, a sample environment file, DPG readiness documentation, privacy notes, governance guidance, production hardening notes and role-access guidance. These are strong open-source foundations.

The main gaps before public release are:

- Local `.env` exists and must stay untracked and never be committed.
- Third-party dependency/license inventory has been source-scanned; detected CDN versions are documented, and the remaining release item is resolving/documenting the Leaflet map reference plus final owner/legal sign-off.
- Database setup/migration documentation exists, but a full sanitized base schema file still needs to be added if it is not already managed elsewhere.
- Facility master/configuration data ownership and public redistribution rights must be confirmed.
- Official maintainer, security, release and issue-triage contacts must be finalized in `MAINTAINERS.md`.
- Legal/privacy confirmation must be completed in `docs/compliance/legal_privacy_confirmation.md`.

License inconsistency was updated on 2026-07-14 by aligning SaQshi-owned UI/config headers and visible license metadata to **GPL-3.0**. See `docs/compliance/license_consistency_before_after.md`.

## Source Criteria Used

This checklist is based on generally accepted open-source release expectations and the OSI Open Source Definition:

- OSI Open Source Definition: `https://opensource.org/osd`
- GPL-3.0 license notice currently present in this repository: `LICENSE`
- License consistency fix record: `docs/compliance/license_consistency_before_after.md`

## Current Verdict

| Area | Verdict |
|---|---|
| Can this be open source? | **Yes** |
| Is the license currently clear and consistent? | **Done for SaQshi-owned UI/config files** |
| Is it safe to publish immediately? | **Partial / publish after release-owner review items are closed** |
| Biggest blocker | Sanitized base schema, Leaflet/reference dependency resolution, public data rights, official governance contacts and final release security scan |
| Security-publication status | `.env` is ignored and not tracked; `SECURITY.md` now exists |

Current automated check:

```text
php tools/release_readiness_check.php
```

Latest local result: `PASSED_WITH_REVIEW`.

Review items currently detected:

- Local `.env` exists for development and must remain untracked.
- Sanitized base schema is not yet present at `api/sql/schema/001_base_schema.sql`.
- Facility master and other real master/configuration data require owner redistribution approval in `docs/compliance/data_redistribution_approval.md`.
- Large release files require owner review: `api/config/performance/outcome.json`, `api/config/frameworks/saqshi-nqas.json`, architecture images and journey image.

## OSI Open Source Criteria Checklist

| # | Criterion | Status | Evidence / Notes |
|---:|---|---|---|
| 1 | Free redistribution allowed | Done | `LICENSE` applies GPL-3.0 terms, which permit redistribution subject to its conditions. |
| 2 | Source code available | Done | Repository contains PHP API source, UI HTML/CSS/JS, config JSON and docs. |
| 3 | Derived works allowed | Done | GPL-3.0 permits modification and distribution of modified versions subject to its conditions. |
| 4 | Integrity of author's source code | Done | GPL-3.0 permits modified versions under its stated conditions. |
| 5 | No discrimination against persons or groups | Done | GPL-3.0 does not discriminate. |
| 6 | No discrimination against fields of endeavor | Done | GPL-3.0 does not restrict medical, government, commercial, or research use. |
| 7 | License distribution | Done | Root `LICENSE` applies to recipients when distributed with the software. |
| 8 | License not specific to a product | Done | GPL-3.0 is project-independent. |
| 9 | License does not restrict other software | Done | GPL-3.0 governs this program and its derivative distributions. |
| 10 | License technology-neutral | Done | GPL-3.0 is technology-neutral. |

## Repository Readiness Checklist

| Item | Status | Evidence | Required Action |
|---|---|---|---|
| Root license file | Done | `LICENSE` exists. `LICENSE.txt` is kept as a compatibility copy. | Confirm final license choice before release tagging. |
| License consistency | Done | Root license is GPL-3.0; SaQshi-owned UI/config headers and visible footer/login metadata now use GPL-3.0. | Continue to avoid changing third-party/vendor license notices. |
| README | Done | `README.md` now includes overview, modules, setup, environment, database, API, testing, release docs and license details. | Keep synchronized with major architecture changes. |
| Contribution guide | Done | `CONTRIBUTING.md` includes branch workflow, coding standards, testing expectations, documentation expectations, review expectations and PR checklist. | Keep synchronized with project workflow changes. |
| Code of conduct | Done | `CODE_OF_CONDUCT.md` exists. | Keep updated with contact/escalation details. |
| Security policy | Done | `SECURITY.md` exists. | Replace placeholder security contact before public release. |
| Notice/attribution file | Done | `NOTICE` exists and points to third-party attribution inventory. | Keep updated before releases. |
| Third-party dependency inventory | Improved / source-scanned | `docs/compliance/third_party_licenses.md` now records detected CDN versions, notes no Composer/npm manifests were found, identifies libraries not detected in the current public source scan and flags the missing Leaflet map reference. | Resolve or vendor the Leaflet reference and complete final owner/legal sign-off before public release. |
| Environment sample | Done | `.env.example` exists. | Keep secrets out of examples. |
| Real secrets excluded | Done | `.gitignore` excludes `.env`, `.env.*`, keys, logs, uploads. Git does not list `.env` as tracked. | Continue checking before every release. |
| Generated/private storage excluded | Done | `.gitignore` excludes `api/storage/events/*.log`, `api/storage/logs/*.log`, `api/storage/keys/`, `uploads/`. | Good. |
| Release readiness script | Done | `tools/release_readiness_check.php` checks required docs, local `.env`, private artifacts, obvious secrets and sanitized schema availability. | Run before release and resolve/review warnings. |
| API documentation | Done | `docs/api/openapi.yaml`, `swagger-ui.html`, Postman collection, and guide exist. | Keep synchronized with API changes. |
| Testing documentation | Done | Test plan, black-box/white-box, VAPT, load testing, WCAG docs exist under `docs/testing`. | Keep results updated with each release. |
| Accessibility statement | Done | `docs/testing/saqshi_wcag_web_platform_compliance.md` exists. | Add manual screen-reader/keyboard results when completed. |
| Security review notes | Done | `docs/security/sql_injection_security_review.md` exists. | Keep remediation status current. |
| Release security scan | Done for current source pass | `docs/security/release_security_scan_2026_07_16.md` documents secret scan, PHP/JS syntax checks, raw SQL review, upload/auth/session review and code hardening. | Run final active VAPT in UAT before production/public release. |
| Database migration/install docs | Partial | `docs/database/database_setup_and_migration.md` and `api/sql/schema/README.md` exist; full sanitized base schema file still needs confirmation. | Add/confirm `api/sql/schema/001_base_schema.sql` and seed strategy. |
| Public issue templates | Missing / Unknown | `.github` directory not found in quick scan. | Add bug report, feature request, security advisory templates. |
| Release/versioning policy | Partial | UI/footer mentions version, docs contain versions, but no release policy found. | Add semantic versioning and changelog policy. |
| Changelog | Done | `CHANGELOG.md` exists. | Update for every release. |
| Governance/maintainers | Partial | `docs/compliance/governance_and_ownership.md` and `MAINTAINERS.md` exist. | Replace pending maintainer/contact rows with official project-owner approved names and contacts. |
| Trademark/branding policy | Missing | SaQshi name/logo usage is not defined. | Add `TRADEMARK.md` or branding section if public reuse matters. |
| Data/privacy guidance | Improved / Partial | `docs/compliance/data_privacy_policy.md`, `docs/compliance/privacy_data_protection.md` and `docs/security/production_hardening.md` exist. | Complete legal/privacy review for the intended public deployment context. |
| Legal/privacy confirmation | Partial | `docs/compliance/legal_privacy_confirmation.md` exists and records required sign-off gates. | Replace Pending entries with official project-owner/data-owner/legal/security decisions. |
| Public data audit | Improved / Partial | `docs/compliance/public_data_audit.md` exists; runtime uploads, logs, generated keys and `.env` were removed from the public source folder. | Confirm redistribution rights for facility master and framework/checklist data before public release. |
| Data redistribution approval | Partial | `docs/compliance/data_redistribution_approval.md` exists and records files requiring approval. | Replace Pending entries with project-owner/data-owner/legal decisions. |

## Application-Specific Open Source Checklist

| Area | Status | Notes |
|---|---|---|
| Facility assessment workflow source available | Done | UI/API files are present. |
| CQI workflow source available | Done | Gap analysis, action plan, closure modules have been built. |
| Performance monitoring source available | Done | KPI/outcome/dashboard/trend modules exist. |
| State monitoring source available | Done | State dashboard, map, certification, CQI, performance, reports, drill-down, user admin modules exist. |
| API event abstraction | Done | `api/core/Event.php` exists for future event-driven/Kafka migration. |
| Friendly error handling | Improved / source-guarded | `api/core/ErrorHandler.php` and `api/core/Response.php` return friendly JSON errors with request IDs for server errors. Legacy array-returning services now sanitize low-level database/system messages, and `tools/release_readiness_check.php` flags direct raw exception/database output patterns. | Continue active endpoint testing in UAT and keep new APIs on `Response::serverError()` for server-side failures. |
| Secrets handling | Partial | `.env` pattern exists and DB config is env-based, but release needs secret scanning before publication. |
| Large config data | Partial | Large JSON config files are present; public data ownership/licensing should be confirmed. |
| Healthcare data caution | Partial | No real patient data should be included in public repo. Facility master data licensing should be confirmed. |

## DPG and Public-Good Evidence Added

| Evidence | Status | Document |
|---|---|---|
| SDG relevance | Added | `docs/compliance/sdg_mapping.md` |
| Data privacy policy | Added | `docs/compliance/data_privacy_policy.md` |
| Legal/privacy confirmation | Added | `docs/compliance/legal_privacy_confirmation.md` |
| Public data audit | Added | `docs/compliance/public_data_audit.md` |
| Non-PII data export/import guidance | Added | `docs/compliance/non_pii_data_export_import.md` |
| Governance and ownership guidance | Added | `docs/compliance/governance_and_ownership.md` |
| Maintainer/contact register | Added | `MAINTAINERS.md` |
| Open standards mapping | Added | `docs/compliance/open_standards_mapping.md` |
| Production hardening | Added | `docs/security/production_hardening.md` |
| Role/access matrix | Added | `docs/security/role_access_matrix.md` |
| Use cases | Added | `docs/architecture/use_cases.md` |

## Current Release Actions Before Public Release

### 1. Keep License Consistent

Final selected license: **GPL-3.0**, matching `LICENSE`.

Already aligned:

- SaQshi-owned source headers in `ui/`
- `ui/config/app.json`
- `ui/components/footer/footer.js`
- `ui/components/footer/footer.html`
- `ui/pages/login/login.html`

Remaining release rules:

- Do not rewrite third-party/vendor license notices.
- Keep README, footer metadata and source headers aligned to GPL-3.0.

### 2. Finalize Security Contact

`SECURITY.md` now exists. Before public release:

- Replace placeholder `security@saqshi.org` if a different official contact is required.
- Confirm supported versions.
- Confirm vulnerability triage owner.

### 3. Verify Third-Party Attribution

Created:

- `NOTICE`
- `docs/compliance/third_party_licenses.md`

Before public release:

- Verify the source-scanned dependency inventory remains current.
- Resolve or vendor the missing Leaflet map files referenced by the state map.
- Decide whether to add an SBOM.
- Confirm CDN dependencies or vendor exact local copies.
- Keep OpenStreetMap attribution visible where maps are shown.

### 4. Keep `README.md` Updated

`README.md` has been expanded. Keep it updated when:

- API paths change.
- Setup requirements change.
- Database setup changes.
- New modules are added.
- Release process changes.

### 5. Use Release Safety Checklist

Created:

- `docs/compliance/release_checklist.md`

Before pushing publicly:

- Confirm `.env` is not tracked.
- Run secret scan.
- Remove test credentials and sample private data.
- Verify database dumps do not contain live data.
- Verify upload/log/key directories are ignored.
- Run syntax checks and smoke tests.
- Update OpenAPI/Postman docs.
- Update changelog.

### 6. Complete Database Release Package

Created:

- `docs/database/database_setup_and_migration.md`

Still required:

- Confirm/add sanitized base schema.
- Confirm seed data strategy.
- Confirm migration tracking strategy.
- Confirm no production data is committed.

## Final Recommendation

SaQshi should be treated as **open-source eligible and much closer to public-release ready**.

The next best step is to verify third-party dependency licenses and add/confirm a sanitized base database schema so a new developer can install SaQshi from the repository without private database files.
