# SaQshi Open Source Readiness Checklist

Version: 1.0  
Reviewed: 2026-07-13  
Repository path: `open_source/`

## Executive Summary

SaQshi is **open-source capable**, but the repository is **not yet fully release-ready as a clean public open-source distribution**.

The project includes a GPL-3.0 open-source license at the repository root, contribution guidance, a code of conduct, API documentation, testing documents, VAPT notes, WCAG notes, and a sample environment file. These are strong open-source foundations.

The main gaps before public release are:

- Local `.env` exists and must stay untracked and never be committed.
- Third-party dependency/license inventory exists, but exact versions/licenses under `ui/` and `api/` still need final owner verification before a public release.
- Database setup/migration documentation exists, but a full sanitized base schema file still needs to be added if it is not already managed elsewhere.

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
| Is it safe to publish immediately? | **Partial / Not recommended until gaps are fixed** |
| Biggest blocker | Security policy, third-party attribution, README, release docs |
| Security-publication status | `.env` is ignored and not tracked; `SECURITY.md` now exists |

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
| Contribution guide | Partial | `CONTRIBUTING.md` exists but is very short. | Add branch workflow, coding standards, test expectations, issue/PR process. |
| Code of conduct | Done | `CODE_OF_CONDUCT.md` exists. | Keep updated with contact/escalation details. |
| Security policy | Done | `SECURITY.md` exists. | Replace placeholder security contact before public release. |
| Notice/attribution file | Done | `NOTICE` exists and points to third-party attribution inventory. | Keep updated before releases. |
| Third-party dependency inventory | Partial | `docs/compliance/third_party_licenses.md` exists; exact versions/licenses under `ui/` and `api/` still need final owner verification. | Verify bundled/CDN dependencies before public release. |
| Environment sample | Done | `.env.example` exists. | Keep secrets out of examples. |
| Real secrets excluded | Done | `.gitignore` excludes `.env`, `.env.*`, keys, logs, uploads. Git does not list `.env` as tracked. | Continue checking before every release. |
| Generated/private storage excluded | Done | `.gitignore` excludes `api/storage/events/*.log`, `api/storage/logs/*.log`, `api/storage/keys/`, `uploads/`. | Good. |
| API documentation | Done | `docs/api/openapi.yaml`, `swagger-ui.html`, Postman collection, and guide exist. | Keep synchronized with API changes. |
| Testing documentation | Done | Test plan, black-box/white-box, VAPT, load testing, WCAG docs exist under `docs/testing`. | Keep results updated with each release. |
| Accessibility statement | Done | `docs/testing/saqshi_wcag_web_platform_compliance.md` exists. | Add manual screen-reader/keyboard results when completed. |
| Security review notes | Done | `docs/security/sql_injection_security_review.md` exists. | Keep remediation status current. |
| Database migration/install docs | Partial | `docs/database/database_setup_and_migration.md` exists; full sanitized base schema file still needs confirmation. | Add/confirm base schema and seed strategy. |
| Public issue templates | Missing / Unknown | `.github` directory not found in quick scan. | Add bug report, feature request, security advisory templates. |
| Release/versioning policy | Partial | UI/footer mentions version, docs contain versions, but no release policy found. | Add semantic versioning and changelog policy. |
| Changelog | Done | `CHANGELOG.md` exists. | Update for every release. |
| Governance/maintainers | Missing / Unknown | No maintainers/governance file found. | Add maintainers, review rules, decision process. |
| Trademark/branding policy | Missing | SaQshi name/logo usage is not defined. | Add `TRADEMARK.md` or branding section if public reuse matters. |
| Data/privacy guidance | Partial | App handles facility/user/health quality data; security docs exist, but public privacy guidance not found. | Add privacy and deployment hardening guide. |

## Application-Specific Open Source Checklist

| Area | Status | Notes |
|---|---|---|
| Facility assessment workflow source available | Done | UI/API files are present. |
| CQI workflow source available | Done | Gap analysis, action plan, closure modules have been built. |
| Performance monitoring source available | Done | KPI/outcome/dashboard/trend modules exist. |
| State monitoring source available | Done | State dashboard, map, certification, CQI, performance, reports, drill-down, user admin modules exist. |
| API event abstraction | Done | `api/core/Event.php` exists for future event-driven/Kafka migration. |
| Friendly error handling | Partial | Error handling exists, but legacy endpoints may still need review. |
| Secrets handling | Partial | `.env` pattern exists and DB config is env-based, but release needs secret scanning before publication. |
| Large config data | Partial | Large JSON config files are present; public data ownership/licensing should be confirmed. |
| Healthcare data caution | Partial | No real patient data should be included in public repo. Facility master data licensing should be confirmed. |

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

- Verify exact versions/licenses for bundled files under `ui/` and `api/`.
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
