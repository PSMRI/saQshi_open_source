# SaQshi DPG Readiness Assessment

Version: 1.5  
Assessed: 2026-07-19  
Project license: GPL-3.0  

## Verdict

SaQshi is **DPG-aligned and close to nomination readiness**. Reproducible
database release evidence is confirmed; formal owner, legal and final security
evidence remain part of the pre-nomination review.

This assessment applies to the current healthcare/NQAS release of SaQshi. Future module expansion, if any, should be assessed separately before it is included in public release material.

The project now has strong evidence for open licensing, healthcare public-good relevance, architecture, API/UI documentation, testing, accessibility, privacy, non-PII data handling, evidence/log retention, data redistribution decisions, governance guidance, open standards, and production hardening.

The remaining work is mostly formal release assurance:

- continued monitoring of maintainer/release ownership recorded in `MAINTAINERS.md`,
- clean database/schema reproducibility through `api/sql/schema/001_base_schema.sql`,
- legal/privacy sign-off through `legal_privacy_confirmation.md`,
- final deployment security validation and release checklist completion,
- owner review of large documentation images.

Current automated release check:

```text
php tools/release_readiness_check.php
```

Latest local result: `PASSED_WITH_REVIEW`.

Current review warnings:

- sanitized base schema and clean-install validation are recorded at `api/sql/schema/001_base_schema.sql` and in the database documentation,
- legal/privacy confirmation is still pending in `docs/compliance/legal_privacy_confirmation.md`,
- local `.env`, runtime event logs and generated login keys exist in this testing copy and must remain untracked/unpublished,
- large architecture/journey images require owner review before final public release.

The 2026-07-19 result confirms the same review posture. Its local findings are
the untracked `.env`, runtime event logs, generated login keys, pending
legal/privacy confirmation and three large visual assets. The check completed
successfully and did not report a release-blocking source-code failure.

Closed since the earlier review:

- local `.env`, runtime logs and generated keys are treated as testing/deployment artifacts and are flagged by the release checker for exclusion before public packaging,
- local facility master JSON is treated as deployment/runtime data and is ignored as an open-source/DPG blocker when used for authorized testing or deployment,
- framework/checklist/action-plan and outcome indicator JSON files are retained unchanged as approved NQAS-aligned core configuration files,
- map/boundary data is recorded as public DataMeet-sourced data under CC BY 4.0 attribution,
- Leaflet is documented as a pinned BSD-2-Clause CDN dependency,
- stable non-PII sample exports are available under `docs/compliance/sample_exports/`,
- default evidence and log retention schedules are documented,
- event log redaction is implemented in `api/core/Event.php`,
- SDG alignment now includes target-level mapping.

## DPG Standard Checklist

| DPGA Indicator | Current Status | Evidence | Remaining Action |
|---|---|---|---|
| 1. SDG Relevance | Done / target-level documented | `docs/compliance/sdg_mapping.md`, `docs/architecture/use_cases.md`, project overview | Keep SDG wording synchronized with future module changes and final nomination language. |
| 2. Open Licensing | Mostly ready | `LICENSE`, `NOTICE`, `docs/compliance/license_consistency_before_after.md`, `docs/compliance/third_party_licenses.md`, `docs/compliance/data_redistribution_approval.md` | Complete final owner/legal sign-off for release governance. |
| 3. Clear Ownership | Done for current public package | `docs/compliance/governance_and_ownership.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `MAINTAINERS.md` | Keep Tech4Gov Team contact current and monitored. |
| 4. Platform Independence | Mostly ready | PHP/MySQL/HTML/CSS/JS stack, deployment guide, IIS/Apache/Nginx/cloud guidance, sanitized base schema and clean-schema validation note, `docs/security/production_hardening.md` | Repeat validation in final UAT/production-like environment before release tagging. |
| 5. Documentation | Good | GitBook, API docs, use cases, architecture, deployment, testing, accessibility and release docs | Keep docs updated with each module change. |
| 6. Non-PII Data Extraction | Done for current public package | `docs/compliance/non_pii_data_export_import.md`, `docs/compliance/sample_exports/`, report/export features, facility master redistribution policy | Keep sample exports synchronized with future report schema changes. |
| 7. Privacy & Applicable Laws | Guidance done / formal sign-off pending | `docs/compliance/data_privacy_policy.md`, `docs/compliance/privacy_data_protection.md`, `docs/compliance/non_pii_data_export_import.md`, `docs/compliance/legal_privacy_confirmation.md`, `docs/security/evidence_upload_and_log_retention.md`, security docs | Complete legal/privacy review for the intended deployment jurisdiction. |
| 8. Open Standards & Best Practices | Documented | `docs/compliance/open_standards_mapping.md`, OpenAPI, Postman, JSON/CSV/XLSX sample exports, WCAG docs | Keep OpenAPI and Postman collection synchronized with API changes. |
| 9A. Data Privacy & Security | Improved / source-guarded | `SECURITY.md`, SQL injection review, VAPT notes, role matrix, production hardening, evidence/log retention policy, event log redaction, release readiness checker | Complete final active VAPT retest and audit-log verification before public release. |
| 9B. Inappropriate & Illegal Content | Low risk / documented | File upload/evidence rules, patient-level PHI exclusion, production hardening guide, evidence retention policy | Add antivirus/malware scanning and authenticated evidence download in production if sensitive evidence uploads are enabled. |
| 9C. Protection from Harassment | Not applicable / documented | SaQshi has no public social/user-to-user interaction workflow | Reassess if public comments, chat or public collaboration features are added. |

## Readiness Score

| Category | Result |
|---|---|
| Open-source readiness | Good, with final release verification pending |
| DPG submission readiness | Close to nomination readiness; formal sign-offs and final UAT/security validation still pending |
| Privacy readiness | Guidance and default retention documented; legal/privacy sign-off still pending |
| Documentation readiness | Strong |
| Data portability readiness | Good; stable non-PII sample exports included |
| Final status | **DPG-aligned, pre-nomination, final release evidence pending** |

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
- `docs/compliance/sample_exports/`
- `docs/compliance/third_party_licenses.md`
- `docs/compliance/release_versioning_policy.md`
- `docs/security/evidence_upload_and_log_retention.md`

## Current DPG Strengths

- GPL-3.0 license is present and project-owned license metadata is aligned.
- Public documentation exists through GitBook, Markdown, OpenAPI, Postman and architecture guides.
- Healthcare quality use case is clear and aligned to SDG 3, with supporting alignment to SDG 9, SDG 10, SDG 16 and SDG 17.
- Data redistribution decisions are recorded: local facility master JSON is deployment/runtime data and not a readiness blocker; public redistribution of real facility names/NINs/hierarchy requires data-owner approval or sample/template packaging; framework/checklist/action-plan and outcome configuration is approved NQAS-aligned core content; map boundaries are DataMeet public data with CC BY 4.0 attribution.
- Stable non-PII sample exports are included for interoperability and public review.
- Security, privacy, role access, production hardening, VAPT notes and SQL injection review are documented.
- Evidence upload retention, log retention and event redaction are documented.
- Release readiness checker automates checks for required docs, secret hygiene, missing page assets, raw error exposure patterns, private artifacts and large release files.

## Remaining Gaps Blocking Final Nomination

| Gap | Why It Matters | Current Evidence | Closure Needed |
|---|---|---|---|
| Governance contact monitoring | Public releases need accountable maintainers, release approvers and escalation contacts to stay current. | `MAINTAINERS.md`, `docs/compliance/governance_and_ownership.md` | Confirm Tech4Gov Team mailbox monitoring before each release. |
| Legal/privacy sign-off | Public-sector health deployments need confirmed data/legal ownership and handling approval. | `docs/compliance/legal_privacy_confirmation.md` | Complete sign-off rows for project owner, data owner, legal/compliance and security contact. |
| Final security/UAT evidence | Source-level scan is not the same as active deployment validation. | `docs/security/release_security_scan_2026_07_16.md`, VAPT docs, `docs/security/evidence_upload_and_log_retention.md` | Run final UAT VAPT/security retest, confirm upload/log retention configuration and record results. |
| Large release image review | Large image files are allowed but should be intentionally retained and source-approved. | Release checker large-file warnings | Confirm owner approval or compress/replace images. |

## Evidence Pending for DPG Nomination

| Evidence area | Current position |
| --- | --- |
| Reproducible database installation | Confirmed: sanitized base schema, clean-install validation and seed/sample strategy are recorded. |
| Maintainer and release ownership | Contact roles are documented in `MAINTAINERS.md`; mailbox monitoring and release approval confirmation remain pending. |
| Legal and privacy review | Documentation is present; formal deployment-jurisdiction sign-off remains pending. |
| UAT security evidence | Source-level security review is recorded; final active VAPT/security retest and retention-configuration evidence remain pending. |
| Release assets and attribution | Large visual-asset owner review, Leaflet version/attribution record, DataMeet attribution and sample-export currency are release evidence items. |
| Versioned release record | A tagged release and completed release-checklist record remain pending. |

## Practical Recommendation

SaQshi is a DPG-oriented open-source project with a strong technical,
documentation, licensing, data-attribution, base-schema and non-PII-portability
foundation. Formal governance/legal sign-off and final UAT/security validation
remain the main nomination evidence gaps.
