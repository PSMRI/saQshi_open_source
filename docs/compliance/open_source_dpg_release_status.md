# Open Source and DPG Release Status

Version: 1.0  
Updated: 2026-07-16

## Current Status

SaQshi is **open-source capable and DPG-aligned**, with the main technical release hygiene now documented and partly automated.

Current release posture:

```text
PASSED_WITH_REVIEW
```

Run the local release check from the `open_source/` directory:

```text
php tools/release_readiness_check.php
```

## Completed Release-Readiness Work

| Area | Status | Evidence |
|---|---|---|
| GPL-3.0 license alignment | Done | `LICENSE`, `LICENSE.txt`, `docs/compliance/license_consistency_before_after.md` |
| Contribution guidance | Done | `CONTRIBUTING.md` |
| Security policy | Done | `SECURITY.md` |
| Maintainer/contact structure | Created, pending real contacts | `MAINTAINERS.md` |
| Public data audit | Done, pending data-owner decisions | `docs/compliance/public_data_audit.md` |
| Runtime private data cleanup | Done | `.env`, uploads, logs and generated keys removed from public source folder |
| Data redistribution record | Created, pending sign-off | `docs/compliance/data_redistribution_approval.md` |
| Legal/privacy confirmation record | Created, pending sign-off | `docs/compliance/legal_privacy_confirmation.md` |
| Release security source scan | Done for current source pass | `docs/security/release_security_scan_2026_07_16.md` |
| Third-party dependency inventory | Source-scanned, one map asset gap open | `docs/compliance/third_party_licenses.md` |
| SQL injection review | Updated | `docs/security/sql_injection_security_review.md` |
| VAPT report | Updated | `docs/testing/saqshi_vapt_report.md` |
| Open standards mapping | Done | `docs/compliance/open_standards_mapping.md` |
| SDG mapping | Done | `docs/compliance/sdg_mapping.md` |
| Non-PII export/import guidance | Done | `docs/compliance/non_pii_data_export_import.md` |
| Production hardening guidance | Done | `docs/security/production_hardening.md` |
| Role access matrix | Done | `docs/security/role_access_matrix.md` |

## Remaining Release Gates

| Gate | Current Status | How to Close |
|---|---|---|
| Sanitized base schema | Open | Add and verify `api/sql/schema/001_base_schema.sql`. |
| Maintainer/security/release contacts | Pending | Replace `Pending` rows in `MAINTAINERS.md`. |
| Legal/privacy sign-off | Pending | Complete `docs/compliance/legal_privacy_confirmation.md`. |
| Data redistribution approval | Pending | Complete `docs/compliance/data_redistribution_approval.md`. |
| Facility/config data publication rights | Pending | Confirm whether real master/config JSON can be public, or replace with sample/template data. |
| Leaflet map dependency | Open | Add/vendored Leaflet files, document an exact CDN version, or remove the missing `/assets/datatables/leaflet.*` references. |
| Large asset/config review | Pending | Owner review for large images/framework/outcome JSON files before public release. |
| Final active VAPT | Pending UAT environment | Run active VAPT/security tests in a controlled UAT environment. |
| Production evidence upload controls | Recommended | Add antivirus/malware scanning and consider authenticated evidence downloads. |

## DPG Readiness Position

SaQshi should be considered:

```text
DPG preparation in progress; not final nomination-ready yet.
```

Reason:

- Functional and technical documentation is now strong.
- Open-source governance structure exists.
- Privacy/security/data handling controls are documented.
- Remaining items require owner/legal/security sign-off and reproducible database packaging.

## Reviewer Path

Recommended review order:

1. [Open Source Readiness Checklist](open_source_readiness_checklist.md)
2. [DPG Readiness Assessment](dpg_readiness_assessment.md)
3. [Release Checklist](release_checklist.md)
4. [Release Security Scan](../security/release_security_scan_2026_07_16.md)
5. [Public Data Audit](public_data_audit.md)
6. [Data Redistribution Approval](data_redistribution_approval.md)
7. [Legal and Privacy Confirmation](legal_privacy_confirmation.md)
8. [Maintainers and Release Contacts](../../MAINTAINERS.md)

## Final Publication Rule

Do not publish the repository as final public release until:

- release checker warnings are reviewed,
- legal/privacy and data redistribution approvals are completed,
- maintainer/security contacts are official,
- sanitized schema exists,
- no private runtime data is present,
- final UAT security testing is complete.
