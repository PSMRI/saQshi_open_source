# Open Source and DPG Release Status

Version: 1.2  
Updated: 2026-07-19

## Current Status

SaQshi is **open-source capable and DPG-aligned**, with the main technical release hygiene now documented and partly automated.

The current documentation and release review are for the healthcare/NQAS implementation of SaQshi.

Current release posture:

```text
PASSED_WITH_REVIEW
```

Latest local release-check command:

```text
php tools/release_readiness_check.php
```

## Completed Release-Readiness Work

| Area | Status | Evidence |
|---|---|---|
| GPL-3.0 license alignment | Done | `LICENSE`, `LICENSE.txt`, `docs/compliance/license_consistency_before_after.md` |
| Contribution guidance | Done | `CONTRIBUTING.md` |
| Security policy | Done | `SECURITY.md` |
| Maintainer/contact structure | Done for current public package | `MAINTAINERS.md` records Tech4Gov Team, Piramal Swasthya, `tech4gov@piramalswasthya.org`. |
| Public issue and PR templates | Done | `.github/ISSUE_TEMPLATE/`, `.github/PULL_REQUEST_TEMPLATE.md` |
| Release/versioning policy | Done | `docs/compliance/release_versioning_policy.md` |
| Public data audit | Done for current public source package | `docs/compliance/public_data_audit.md` |
| Runtime private data cleanup | Guarded / release-gated | Local `.env`, runtime logs and generated keys may exist in testing/deployment copies. `.gitignore` and `tools/release_readiness_check.php` flag them so they are excluded before public packaging. |
| Secrets handling | Done with automated guard | `.env.example`, `.gitignore`, env-based DB config and `tools/release_readiness_check.php` |
| Data redistribution record | Done for current public source package | Local facility master JSON is treated as deployment data and ignored as a readiness blocker; public redistribution of real facility names/NINs/hierarchy requires data-owner approval or sample/template packaging; NQAS-aligned framework/checklist/action-plan and outcome configs are approved core configuration files; map boundaries are sourced from DataMeet CC BY 4.0. |
| Data/privacy guidance | Done | `docs/compliance/data_privacy_policy.md`, `docs/compliance/privacy_data_protection.md`, `docs/compliance/non_pii_data_export_import.md` |
| Evidence/log retention | Done / deployment configuration required | `docs/security/evidence_upload_and_log_retention.md` defines default retention: evidence 30 days/3 years/validity plus 1 year depending on type; application logs 90 days; API/event logs 180 days; security incident logs 1 year after closure. |
| Patient-level PHI exclusion | Documented | SaQshi is documented as facility quality/monitoring software and not a patient record system. Patient-level personal health information must not be entered or uploaded in the default workflow. |
| Legal/privacy confirmation record | Created, pending sign-off | `docs/compliance/legal_privacy_confirmation.md` |
| Release security source scan | Done for current source pass | `docs/security/release_security_scan_2026_07_16.md` |
| Third-party dependency inventory | Source-scanned and Leaflet documented | `docs/compliance/third_party_licenses.md` |
| SQL injection review | Updated | `docs/security/sql_injection_security_review.md` |
| VAPT report | Updated | `docs/testing/saqshi_vapt_report.md` |
| Open standards mapping | Done | `docs/compliance/open_standards_mapping.md` |
| SDG mapping | Done | `docs/compliance/sdg_mapping.md` |
| Non-PII export/import guidance | Done | `docs/compliance/non_pii_data_export_import.md`, `docs/compliance/sample_exports/` |
| Production hardening guidance | Done | `docs/security/production_hardening.md` |
| Role access matrix | Done | `docs/security/role_access_matrix.md` |
| Sanitized base schema | Added / clean-schema validation recorded | `api/sql/schema/001_base_schema.sql`, `api/sql/schema/README.md`, `docs/database/database_setup_and_migration.md`, `docs/database/data_dictionary_erd.md` |

## Remaining Release Evidence

| Evidence area | Current Status | Publication position |
|---|---|---|
| Sanitized base schema | Added / clean-schema validation recorded | Fresh-schema application check has been recorded in `docs/database/database_setup_and_migration.md`. Keep deployment-specific seed data documented for each installation. |
| Maintainer/security/release contacts | Done for current public package | Tech4Gov Team / Piramal Swasthya / `tech4gov@piramalswasthya.org` recorded in `MAINTAINERS.md`. |
| Legal/privacy sign-off | Pending | Complete `docs/compliance/legal_privacy_confirmation.md`. |
| Data redistribution approval | Done for current public source package | Local facility master JSON is deployment/runtime data and ignored as an open-source/DPG blocker; public redistribution of real facility master data requires data-owner approval or sample/template packaging; framework/checklist/action-plan and outcome indicator JSON files are approved NQAS-aligned core configuration files; map/boundary data is recorded as public DataMeet-sourced data under CC BY 4.0 attribution. |
| Facility master publication rights | Not a local testing blocker | Authorized real facility JSON may be used locally for testing/deployment. Public source releases must not redistribute real facility names, NINs and hierarchy data unless the data owner approves it. |
| Leaflet map dependency | Done / pinned CDN | `ui/pages/state/map.json` loads `leaflet@1.9.4` from `cdn.jsdelivr.net`; license/source recorded in `docs/compliance/third_party_licenses.md`. |
| Large asset/config review | Pending for images only | Owner review for large architecture and journey images before public release. Large framework/outcome JSON files are approved NQAS-aligned core configuration files and are whitelisted by the release checker. |
| Final active VAPT | Pending UAT environment | Run active VAPT/security tests in a controlled UAT environment. |
| Production evidence upload controls | Documented / production hardening recommended | Upload/delete controls and retention schedule are documented. Add antivirus/malware scanning and consider authenticated evidence downloads/non-public storage for stricter deployments. |

## DPG Readiness Position

SaQshi should be considered:

```text
DPG preparation in progress; not final nomination-ready yet.
```

Reason:

- Functional and technical documentation is now strong.
- Open-source governance structure exists.
- Privacy/security/data handling controls, evidence retention and log retention are documented.
- Remaining items require owner/legal/security sign-off and final UAT/security validation.

## Reviewer References

The release evidence is available in the following order:

1. [Open Source Readiness Checklist](open_source_readiness_checklist.md)
2. [DPG Readiness Assessment](dpg_readiness_assessment.md)
3. [Release Checklist](release_checklist.md)
4. [Release Security Scan](../security/release_security_scan_2026_07_16.md)
5. [Public Data Audit](public_data_audit.md)
6. [Data Redistribution Approval](data_redistribution_approval.md)
7. [Legal and Privacy Confirmation](legal_privacy_confirmation.md)
8. [Maintainers and Release Contacts](../../MAINTAINERS.md)

## Final Publication Position

Final public-release status depends on reviewed release-check warnings,
completed legal/privacy confirmation, current maintainer/security contacts,
sanitized-schema and clean-install evidence, exclusion of private runtime data,
deployment retention configuration and final UAT security evidence.
