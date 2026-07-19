# SaQshi Open Source Readiness Checklist

Version: 1.4  
Reviewed: 2026-07-19  
Repository location: project root

## Executive Summary

SaQshi is **open-source capable and close to public-release readiness**. The
current package has a `PASSED_WITH_REVIEW` release-check result; the remaining
publication evidence is listed below.

This checklist covers the current healthcare/NQAS public release package. Any future module expansion should be reviewed separately before it is included in public release material.

The project includes a GPL-3.0 open-source license at the repository root, contribution guidance, a code of conduct, API documentation, testing documents, VAPT notes, WCAG notes, a sample environment file, DPG readiness documentation, privacy notes, governance guidance, production hardening notes, evidence/log retention guidance and role-access guidance. These are strong open-source foundations.

The main release-review items before public release are:

- Leaflet is now documented as a pinned CDN dependency for the state certification map; production/offline releases may vendor the same version with BSD-2-Clause attribution.
- Database setup/migration documentation, sanitized base schema and clean-install validation evidence are recorded at `api/sql/schema/001_base_schema.sql` and in the database documentation.
- Facility master data is treated as deployment/runtime data. A local testing copy may use authorized real facility JSON and this is ignored for open-source/DPG readiness review. Public redistribution of real facility names, NINs and hierarchy data still requires data-owner approval or a sample/template package. Framework/checklist/action-plan and outcome indicator JSON files are retained unchanged as approved NQAS-aligned core configuration files. Map/boundary data is recorded as public DataMeet-sourced data under CC BY 4.0 attribution.
- Official maintainer, security, release and issue-triage contacts are recorded in `MAINTAINERS.md` as Tech4Gov Team, Piramal Swasthya, `tech4gov@piramalswasthya.org`.
- Legal/privacy confirmation must be completed in `docs/compliance/legal_privacy_confirmation.md`.
- Large release files such as architecture and journey images need owner review before tagging a public release.

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
| Biggest blocker | Legal/privacy sign-off and final UAT/security validation |
| Security-publication status | `.env` is absent from the public package, `.env.example` is present, runtime keys/logs/uploads are excluded, and security contact is recorded as `tech4gov@piramalswasthya.org` |

Current automated check:

```text
php tools/release_readiness_check.php
```

Latest local result: `PASSED_WITH_REVIEW`.

Review items currently detected by the latest release checker in this local development/testing copy:

- Local `.env` exists and must remain untracked/unpublished; `.env.example` is present for setup.
- Sanitized base schema and clean-install validation evidence are recorded at `api/sql/schema/001_base_schema.sql` and in the database documentation.
- Legal/privacy sign-off is still pending in `docs/compliance/legal_privacy_confirmation.md`.
- Runtime event logs and generated login keys exist locally for testing and must not be published.
- Large release files require owner review: architecture images and journey image.

The 2026-07-19 check reported the same release-review categories: a local
`.env`, three local runtime event logs, local generated login keys, pending
legal/privacy confirmation, and three large visual assets. These are local
review items, not evidence of tracked public secrets.

Data-source status now recorded as complete for the current public package:

- Local authorized real facility JSON used for testing/deployment is ignored as an open-source/DPG blocker. Public redistribution of facility names, NINs and hierarchy data still requires data-owner approval or sample/template packaging.
- Framework/checklist/action-plan and outcome indicator JSON files are approved NQAS-aligned core configuration files.
- Map/boundary configuration is public DataMeet-sourced data with CC BY 4.0 attribution in `docs/compliance/data_redistribution_approval.md`.

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
| Security policy | Done | `SECURITY.md` exists and `MAINTAINERS.md` records `tech4gov@piramalswasthya.org` as the security reporting contact. | Keep the security contact monitored and current. |
| Notice/attribution file | Done | `NOTICE` exists and points to third-party attribution inventory. | Keep updated before releases. |
| Third-party dependency inventory | Done / source-scanned | `docs/compliance/third_party_licenses.md` records detected CDN versions, notes no Composer/npm manifests were found, documents Leaflet as a pinned BSD-2-Clause CDN dependency and records DataMeet/OpenStreetMap attribution. | Keep synchronized when dependencies change; vendor exact files if offline production release is required. |
| Environment sample | Done | `.env.example` exists. | Keep secrets out of examples. |
| Real secrets excluded | Done | `.gitignore` excludes `.env`, `.env.*`, keys, logs, uploads. Git does not list `.env` as tracked. | Continue checking before every release. |
| Generated/private storage excluded | Done | `.gitignore` excludes `api/storage/events/*.log`, `api/storage/logs/*.log`, `api/storage/keys/`, `uploads/`. | Good. |
| Release readiness script | Done | `tools/release_readiness_check.php` checks required docs, local `.env`, private artifacts, obvious secrets, raw error exposure patterns, missing page assets and sanitized schema availability. Facility JSON content is treated as local deployment data and is not flagged automatically. | Run before release and resolve/review warnings. |
| API documentation | Done | `docs/api/openapi.yaml`, `swagger-ui.html`, Postman collection, and guide exist. | Keep synchronized with API changes. |
| Testing documentation | Done | Test plan, black-box/white-box, VAPT, load testing, WCAG docs exist under `docs/testing`. | Keep results updated with each release. |
| Accessibility statement | Done | `docs/testing/saqshi_wcag_web_platform_compliance.md` exists. | Add manual screen-reader/keyboard results when completed. |
| Security review notes | Done | `docs/security/sql_injection_security_review.md` exists. | Keep remediation status current. |
| Release security scan | Done for current source pass | `docs/security/release_security_scan_2026_07_16.md` documents secret scan, PHP/JS syntax checks, raw SQL review, upload/auth/session review and code hardening. | Run final active VAPT in UAT before production/public release. |
| Database migration/install docs | Done / clean-schema validation recorded | `api/sql/schema/001_base_schema.sql`, `api/sql/schema/README.md`, `docs/database/database_setup_and_migration.md` and `docs/database/data_dictionary_erd.md` exist; fresh-schema application check is recorded. | Repeat validation for final UAT/production-like release and keep seed/sample strategy documented. |
| Public issue templates | Done | `.github/ISSUE_TEMPLATE/bug_report.md`, `feature_request.md`, `security_advisory.md`, `config.yml` and `.github/PULL_REQUEST_TEMPLATE.md` exist. | Keep labels/contact links synchronized with repository governance. |
| Release/versioning policy | Done | `docs/compliance/release_versioning_policy.md` defines semantic versioning, dev/RC suffixes, branch/tag rules, changelog sections, release gates, hotfix handling and approval roles. | Apply the policy when tagging the first public release. |
| Changelog | Done | `CHANGELOG.md` exists. | Update for every release. |
| Governance/maintainers | Done for current public package | `docs/compliance/governance_and_ownership.md` exists and `MAINTAINERS.md` records Tech4Gov Team, Piramal Swasthya, `tech4gov@piramalswasthya.org` for maintainer, security, release, issue-triage, data-owner and legal/compliance contact roles. | Keep contacts monitored and update when ownership changes. |
| Trademark/branding policy | Missing | SaQshi name/logo usage is not defined. | Add `TRADEMARK.md` or branding section if public reuse matters. |
| Data/privacy guidance | Done | `docs/compliance/data_privacy_policy.md`, `docs/compliance/privacy_data_protection.md`, `docs/compliance/non_pii_data_export_import.md`, `docs/security/production_hardening.md`, `docs/security/evidence_upload_and_log_retention.md` and `docs/security/role_access_matrix.md` document data categories, DPDP-oriented controls, access scope, deployment safeguards, non-PII exports, evidence retention, log retention and operational controls. | Keep guidance synchronized with new modules and deployment patterns. |
| Legal/privacy confirmation | Partial | `docs/compliance/legal_privacy_confirmation.md` exists and records required sign-off gates. | Replace Pending entries with official project-owner/data-owner/legal/security decisions. |
| Public data audit | Done for current public source package | `docs/compliance/public_data_audit.md` exists; local `.env`, runtime logs and generated keys may exist in the developer/testing copy and are flagged by the release checker for exclusion before publication; local real facility JSON is treated as authorized deployment data and ignored as a readiness blocker, framework/checklist/action-plan plus outcome indicator JSON files are recorded as approved NQAS-aligned core configuration files, and map/boundary data is attributed to DataMeet public data under CC BY 4.0. | Remove/exclude local runtime files before public packaging and keep attribution current if boundary files are replaced later. |
| Data redistribution approval | Done for current public source package | `docs/compliance/data_redistribution_approval.md` records local facility master data as deployment/runtime data, public redistribution as requiring data-owner approval or sample/template packaging, framework/checklist/action-plan and outcome indicator configuration as approved core configuration, and map/boundary data as public DataMeet-sourced data under CC BY 4.0 attribution. | Re-review only if new redistributable master/config/boundary data is added. |

## Application-Specific Open Source Checklist

| Area | Status | Notes |
|---|---|---|
| Facility assessment workflow source available | Done | UI/API files are present. |
| CQI workflow source available | Done | Gap analysis, action plan, closure modules have been built. |
| Performance monitoring source available | Done | KPI/outcome/dashboard/trend modules exist. |
| State monitoring source available | Done | State dashboard, map, certification, CQI, performance, reports, drill-down, user admin modules exist. |
| API event abstraction | Done | `api/core/Event.php` exists for future event-driven/Kafka migration. |
| Friendly error handling | Improved / source-guarded | `api/core/ErrorHandler.php` and `api/core/Response.php` return friendly JSON errors with request IDs for server errors. Legacy array-returning services now sanitize low-level database/system messages, and `tools/release_readiness_check.php` flags direct raw exception/database output patterns. | Continue active endpoint testing in UAT and keep new APIs on `Response::serverError()` for server-side failures. |
| Secrets handling | Done / automated guard | `api/assets/conn/db.php` reads database settings from `Env`, `.env.example` contains placeholders only, `.gitignore` excludes `.env`, logs, uploads and generated keys, and `tools/release_readiness_check.php` scans for obvious secrets/private artifacts before release. Local `.env`, logs and generated keys are allowed for testing but must not be published. |
| Large config data | Done / approved core configuration | `api/config/frameworks/saqshi-nqas.json` and `api/config/performance/outcome.json` intentionally remain unchanged because they are approved NQAS-aligned core configuration files. The release checker whitelists only these two known files while continuing to flag any other unexpected large release file. |
| Healthcare data caution | Done | SaQshi is documented as facility quality/monitoring software and not a patient record system. Patient-level personal health information must not be entered, uploaded or committed. Local real facility JSON used for authorized testing/deployment is not a readiness blocker, but public redistribution still needs data-owner approval or sample/template packaging. | Keep patient/person data, unauthorized facility data, uploads, logs, keys and database dumps out of public releases. |
| Evidence upload controls | Done / production hardening noted | Upload/delete APIs require authentication, validate extension/MIME/size, sanitize file names/categories and block delete path traversal. `docs/security/evidence_upload_and_log_retention.md` defines default evidence retention: draft/wrong upload immediate or within 30 days, assessment/CQI evidence 3 years and certification evidence validity plus 1 year or 3 years minimum. | For stricter deployments, add authenticated download endpoint, non-public storage and malware scanning. |
| Audit/event logs | Done / retention documented | `api/core/Event.php` redacts sensitive keys before event logging. Runtime logs are excluded by `.gitignore`. `docs/security/evidence_upload_and_log_retention.md` defines default retention: app error logs 90 days, API/event logs 180 days and security incident logs 1 year after closure. | Configure actual server/SIEM retention and access controls in deployment. |

## DPG and Public-Good Evidence Added

| Evidence | Status | Document |
|---|---|---|
| SDG relevance | Added | `docs/compliance/sdg_mapping.md` |
| Data privacy policy | Added | `docs/compliance/data_privacy_policy.md` |
| Legal/privacy confirmation | Added | `docs/compliance/legal_privacy_confirmation.md` |
| Evidence upload and log retention | Added | `docs/security/evidence_upload_and_log_retention.md` |
| Public data audit | Added | `docs/compliance/public_data_audit.md` |
| Non-PII data export/import guidance | Added | `docs/compliance/non_pii_data_export_import.md` |
| Governance and ownership guidance | Added | `docs/compliance/governance_and_ownership.md` |
| Maintainer/contact register | Added | `MAINTAINERS.md` |
| Open standards mapping | Added | `docs/compliance/open_standards_mapping.md` |
| Production hardening | Added | `docs/security/production_hardening.md` |
| Role/access matrix | Added | `docs/security/role_access_matrix.md` |
| Use cases | Added | `docs/architecture/use_cases.md` |

## Release Evidence and Publication Conditions

### 1. License Consistency

Final selected license: **GPL-3.0**, matching `LICENSE`.

Already aligned:

- SaQshi-owned source headers in `ui/`
- `ui/config/app.json`
- `ui/components/footer/footer.js`
- `ui/components/footer/footer.html`
- `ui/pages/login/login.html`

Release evidence:

- Do not rewrite third-party/vendor license notices.
- Keep README, footer metadata and source headers aligned to GPL-3.0.

### 2. Security Contact

`SECURITY.md` and `MAINTAINERS.md` record the security contact. Public-release
evidence includes:

- Security reporting contact is recorded as `tech4gov@piramalswasthya.org` in `MAINTAINERS.md`.
- Confirm supported versions.
- Confirm vulnerability triage owner.

### 3. Third-Party Attribution

Created:

- `NOTICE`
- `docs/compliance/third_party_licenses.md`

Publication evidence includes:

- Verify the source-scanned dependency inventory remains current.
- Keep Leaflet pinned to the documented version or vendor the exact same files for offline releases.
- Decide whether to add an SBOM.
- Confirm CDN dependencies or vendor exact local copies.
- Keep OpenStreetMap attribution visible where maps are shown.

### 4. README Coverage

`README.md` includes the following release-relevant areas:

- API paths change.
- Setup requirements change.
- Database setup changes.
- New modules are added.
- Release process changes.

### 5. Release Safety Evidence

Created:

- `docs/compliance/release_checklist.md`

The release checklist records verification of:

- Confirm `.env` is not tracked.
- Run secret scan.
- Remove test credentials and sample private data.
- Verify database dumps do not contain live data.
- Verify upload/log/key directories are ignored.
- Run syntax checks and smoke tests.
- Update OpenAPI/Postman docs.
- Update changelog.

### 6. Database Release Package

Created:

- `docs/database/database_setup_and_migration.md`

Current status:

- Sanitized base schema added at `api/sql/schema/001_base_schema.sql`.
- Seed/master data strategy documented as approved/sanitized import after schema setup.

Remaining database evidence:

- Validate clean install on a fresh database.
- Confirm migration tracking strategy.
- Confirm no production data is committed.

## Final Recommendation

SaQshi should be treated as **open-source eligible and close to public-release ready**.

The remaining publication evidence consists of maintainer/contact confirmation,
formal legal/privacy sign-off and final UAT/security validation.
