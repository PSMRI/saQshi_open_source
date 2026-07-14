# SaQshi DPG Readiness Assessment

Version: 1.0  
Assessed: 2026-07-14  
Project license: GPL-3.0  

## Verdict

SaQshi is **DPG-aligned but not yet fully DPG-ready**.

It has a strong foundation: open-source license, source code, API/UI documentation, security documentation, release checklist, accessibility documentation and healthcare quality use case. However, to be ready for Digital Public Goods Alliance review, SaQshi still needs clearer evidence for privacy/legal compliance, non-PII data import/export, platform independence, open standards, ownership, dependency licensing and do-no-harm safeguards.

## DPG Standard Checklist

| DPGA Indicator | Current Status | Evidence | Gap / Action |
|---|---|---|---|
| 1. SDG Relevance | Partial | SaQshi supports healthcare quality assessment and monitoring, strongly aligned with health system improvement. | Explicitly document SDG mapping, likely SDG 3: Good Health and Well-being, and describe public-sector use cases. |
| 2. Open Licensing | Mostly Ready | `LICENSE` uses GPL-3.0; SaQshi-owned UI/config license text aligned to GPL-3.0. | Confirm third-party licenses and add final dependency/version review. |
| 3. Clear Ownership | Partial | `LICENSE`, `NOTICE`, README and copyright line exist. | Add maintainer/governance file and confirm ownership of framework JSON, facility master data and map boundary data. |
| 4. Platform Independence | Partial | PHP/MySQL/HTML/CSS/JS stack can run on common web servers. | Document open alternatives and avoid hard dependency on proprietary services; provide clean install guide and container/server examples. |
| 5. Documentation | Good but not complete | README, API docs, Postman guide, database guide, release checklist, security docs, WCAG docs exist. | Add full functional requirements/use cases, administrator guide and deployment guide. |
| 6. Non-PII Data Extraction | Not Ready | Reports/downloads exist, but DPG-specific non-PII import/export is not documented. | Add documented non-PII export/import mechanism using CSV/JSON, with fields and privacy rules. |
| 7. Privacy & Applicable Laws | Partial | Security policy, env handling, encryption/password hashing work and VAPT docs exist. | Add privacy policy, data retention policy, consent/legal basis, DPIA-style notes and India health data compliance mapping if applicable. |
| 8. Open Standards & Best Practices | Partial | OpenAPI/Postman docs exist; WCAG and security docs exist. | Document standards used: OpenAPI, CSV/JSON, WCAG 2.2, HTTPS, OWASP, accessible web practices. |
| 9A. Data Privacy & Security | Partial | CSRF/session/security work is documented; `.env` pattern exists. | Complete manual security review, secret scan, role-access matrix, audit logging policy and production hardening guide. |
| 9B. Inappropriate & Illegal Content | Not Applicable / Partial | SaQshi is not primarily a public content platform, but file/evidence upload exists. | Add upload acceptable-use policy, file scanning/validation note and evidence data handling rule. |
| 9C. Protection from Harassment | Not Applicable / Partial | SaQshi does not appear to support public social interaction. | Add statement that the application does not provide public user-to-user interaction; if chat/collaboration expands, add abuse reporting/moderation. |

## DPG Readiness Score

| Category | Result |
|---|---|
| Open-source readiness | Good |
| DPG submission readiness | Partial |
| Privacy readiness | Partial |
| Documentation readiness | Good but still missing DPG-specific docs |
| Data portability readiness | Not ready |
| Final status | **Not DPG-ready yet** |

## Minimum Work Before DPG Nomination

1. Add `docs/compliance/sdg_mapping.md`.
2. Add `docs/compliance/data_privacy_policy.md`.
3. Add `docs/compliance/non_pii_data_export_import.md`.
4. Add `docs/compliance/governance_and_ownership.md`.
5. Add `docs/compliance/open_standards_mapping.md`.
6. Add `docs/security/production_hardening.md`.
7. Add role/access matrix for facility, block, district, division and state users.
8. Finalize third-party dependency license inventory under `ui/` and `api/`.
9. Confirm no real facility/user/patient data is shipped in public repository.
10. Confirm database install can be reproduced from public files.

## Practical Recommendation

SaQshi should first target **DPG-ready documentation and release hygiene**, then apply for DPG recognition after privacy, data portability, governance and dependency evidence are complete.

Current best label:

```text
Open-source ready foundation, DPG preparation in progress.
```
