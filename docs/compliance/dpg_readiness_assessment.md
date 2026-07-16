# SaQshi DPG Readiness Assessment

Version: 1.1  
Assessed: 2026-07-16  
Project license: GPL-3.0  

## Verdict

SaQshi is **DPG-aligned and materially closer to DPG readiness**, but it should still be treated as **pre-nomination** until final owner, legal, security and release evidence is confirmed.

The project now has stronger evidence for open licensing, healthcare public-good relevance, architecture, API/UI documentation, testing, accessibility, privacy, non-PII data handling, governance, open standards and production hardening. The remaining work is mostly release assurance: resolving/documenting the Leaflet map reference, official maintainer ownership through `MAINTAINERS.md`, public data rights, clean database/schema reproducibility, deployment security validation and legal/privacy sign-off through `legal_privacy_confirmation.md`.

Current automated release check:

```text
php tools/release_readiness_check.php
```

Latest local result: `PASSED_WITH_REVIEW`. Runtime uploads, event logs, generated keys and local `.env` were removed from the public source folder. The remaining review items are the missing sanitized base schema, legal/privacy sign-off and owner/legal review for real facility master, boundary/configuration and large framework/config/image assets that may be published.

## DPG Standard Checklist

| DPGA Indicator | Current Status | Evidence | Remaining Action |
|---|---|---|---|
| 1. SDG Relevance | Documented | `docs/compliance/sdg_mapping.md`, `docs/architecture/use_cases.md`, project overview | Confirm final SDG wording for public submission. |
| 2. Open Licensing | Mostly ready | `LICENSE`, `NOTICE`, `docs/compliance/license_consistency_before_after.md`, `docs/compliance/third_party_licenses.md` | Resolve or vendor the missing Leaflet reference and complete final owner/legal sign-off. |
| 3. Clear Ownership | Improved / partial | `docs/compliance/governance_and_ownership.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md` | Add named official maintainers, data owners and escalation contacts before release. |
| 4. Platform Independence | Mostly ready | PHP/MySQL/HTML/CSS/JS stack, deployment guide, cloud/on-prem guidance, `docs/security/production_hardening.md` | Validate clean install on at least one fresh server environment. |
| 5. Documentation | Good | GitBook, API docs, use cases, architecture, deployment, testing, accessibility and release docs | Keep docs updated with each module change. |
| 6. Non-PII Data Extraction | Improved / partial | `docs/compliance/non_pii_data_export_import.md`, report/export features | Add stable sample exports and confirm no public sample contains real personal/facility-sensitive data. |
| 7. Privacy & Applicable Laws | Improved / partial | `docs/compliance/data_privacy_policy.md`, `docs/compliance/privacy_data_protection.md`, security docs | Complete legal/privacy review for the intended deployment jurisdiction. |
| 8. Open Standards & Best Practices | Documented | `docs/compliance/open_standards_mapping.md`, OpenAPI, Postman, JSON/CSV/XLSX docs, WCAG docs | Keep OpenAPI and Postman collection synchronized with API changes. |
| 9A. Data Privacy & Security | Improved / partial | `SECURITY.md`, SQL injection review, VAPT notes, role matrix, production hardening | Complete final secret scan, VAPT retest and audit-log verification before public release. |
| 9B. Inappropriate & Illegal Content | Low risk / documented | File upload/evidence rules, production hardening guide | Add antivirus/malware scanning in production if evidence uploads are enabled. |
| 9C. Protection from Harassment | Not applicable / documented | SaQshi has no public social/user-to-user interaction workflow | Reassess if public comments, chat or public collaboration features are added. |

## Readiness Score

| Category | Result |
|---|---|
| Open-source readiness | Good, with final release verification pending |
| DPG submission readiness | Improved but still pre-nomination |
| Privacy readiness | Improved; legal review still pending |
| Documentation readiness | Good |
| Data portability readiness | Improved; sample non-PII exports still needed |
| Final status | **DPG preparation in progress; not final nomination-ready yet** |

## Evidence Added on 2026-07-16

- `docs/compliance/sdg_mapping.md`
- `docs/compliance/data_privacy_policy.md`
- `docs/compliance/legal_privacy_confirmation.md`
- `docs/compliance/non_pii_data_export_import.md`
- `docs/compliance/public_data_audit.md`
- `docs/compliance/data_redistribution_approval.md`
- `docs/compliance/governance_and_ownership.md`
- `docs/compliance/open_standards_mapping.md`
- `docs/security/production_hardening.md`
- `docs/security/role_access_matrix.md`
- `docs/architecture/use_cases.md`
- `MAINTAINERS.md`

## Minimum Work Before DPG Nomination

1. Confirm official maintainer, governance and data-owner names.
2. Resolve or vendor the Leaflet map reference identified in the third-party dependency inventory.
3. Confirm no real user, patient or sensitive facility data is shipped in the public repository.
4. Publish or verify a sanitized reproducible database schema and setup path.
5. Add sample non-PII export/import files for public review.
6. Complete legal/privacy review for India/public-sector health deployment use.
7. Run final secret scan, dependency review and VAPT retest.
8. Tag a release version and complete the release checklist.

## Practical Recommendation

SaQshi should continue as a DPG-oriented open-source project and prepare a nomination package only after the remaining release-assurance items are completed. The technical and documentation foundation is now strong enough to support that next step.
