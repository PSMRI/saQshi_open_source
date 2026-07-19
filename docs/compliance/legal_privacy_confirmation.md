# Legal and Privacy Confirmation

Version: 1.0  
Created: 2026-07-16  
Status: Pending legal/privacy sign-off

## Purpose

This document records the legal and privacy confirmation required before SaQshi is published publicly or deployed in a public-sector/health environment.

It is not legal advice. It is a release-control document to make sure the project owner, data owner and legal/compliance reviewer explicitly confirm the required items.

## Review Scope

| Area | Review Question | Current Status | Required Action |
|---|---|---|---|
| Privacy policy | Does SaQshi explain what data is handled and how it should be protected? | Documented | Review `data_privacy_policy.md` and adapt to deployment policy. |
| Data protection note | Are operational data risks documented for users/deployers? | Documented | Review `privacy_data_protection.md`. |
| User identity data | Are names, mobile numbers, emails, roles and login IDs treated as sensitive? | Documented | Confirm encryption/hashing and retention rules in deployment. |
| State operational data | Are facility, assessment, CQI, performance and certification monitoring data treated as restricted operational data instead of blanket-encrypted data? | Documented | Confirm role/geography scope, export approval, retention and audit requirements. |
| Personal fields inside workflows | Are assessor/assessee names/contact fields and personal details in remarks protected? | Documented | Assessor/assessee structured personal fields are encrypted; define policy for free-text remarks. |
| CQI responsibility field | Does CQI store designation/post only rather than personal contact details? | Documented | Keep CQI responsible field as designation/post such as CHO/RM/MOIC. If actual person name/mobile/email is added later, encrypt structured fields. |
| Facility master data | Can real facility names, NINs and hierarchy data be used or redistributed? | Local use allowed; public redistribution requires approval | Authorized real facility JSON may be used for testing and deployment. Do not publish or redistribute real facility names, NINs and hierarchy data publicly unless the data owner approves it; otherwise use sample/template data in public packages. |
| Map/boundary data | Can map/boundary/config data be redistributed? | Confirmed public source | `map.json`, `biharmap.json` and `upmap.json` are recorded as DataMeet public boundary data under CC BY 4.0 attribution. |
| Framework/checklist data | Can NQAS/framework/checkpoint/action-plan content be redistributed? | Yes, as core configuration | Retain as SaQshi core configurable master data aligned to NHSRC/QPS NQAS checklist/tools. Record NHSRC/QPS source references and version/date when updated. |
| Certification data | Can certification status/history be published or exported? | Governance export only | May be exported for state/division/district/block/organization governance according to role scope and authorization. Not public by default. |
| Uploaded evidence | Are uploads protected and retention rules defined? | Protected with application controls; default retention defined | Upload/delete APIs require authentication, validate file size/type/MIME/content, sanitize names/categories and block delete path traversal. Runtime uploads are excluded from public source. Default retention: draft/wrong upload immediate or within 30 days; assessment/CQI evidence 3 years; certification evidence validity plus 1 year or 3 years minimum. Deployment owner may override by approved local policy. |
| Patient-level PHI exclusion | Does the deployment confirm SaQshi will not store patient-level personal health information in the default workflow? | Documented | Confirm user training, evidence redaction and monitoring of free-text fields/uploads. |
| Logs/audit events | Are logs free from secrets and governed by retention policy? | Redaction implemented; default retention defined | Event logs redact sensitive keys and runtime logs are excluded from public source. Default retention: application error logs 90 days; API/event logs 180 days; security incident logs 1 year after incident closure. Deployment owner must configure rotation, backup and access restrictions. |
| Public exports | Are public reports non-PII and authorized? | Not public by default | SaQshi exports are for the implementing organization/governance users. Public release of reports requires separate organization/data-owner approval and non-PII/aggregate review. |

## Public-Sector / Health Deployment Checklist

Before deployment, confirm:

- The deploying organization is authorized to collect and process SaQshi data.
- The data controller/owner is identified.
- The security contact and incident-response owner are identified in `MAINTAINERS.md`.
- Role-based access is configured for facility, block, district, division/regional and state users.
- Facility/state operational data is accessed through scoped APIs and is not publicly exported by default.
- Local facility master JSON used for testing/deployment is controlled deployment data and is not assessed as an open-source/DPG blocker.
- Structured personal/contact fields are encrypted or otherwise protected according to deployment policy.
- Free-text remarks are reviewed so users do not enter personal or patient-level details.
- HTTPS is used in production.
- `.env`, logs, backups, uploads and generated keys are protected from public web access.
- Uploaded evidence does not contain patient-level personal health information. Redact or exclude any patient-identifiable file before upload.
- Upload folder directory listing is disabled; stricter deployments should use an authenticated download endpoint or store uploads outside public web root.
- Evidence and log retention follow `docs/security/evidence_upload_and_log_retention.md` or an approved local policy.
- Log rotation and retention are configured outside the application according to the default schedule or approved deployment policy.
- Report downloads are for authorized organization/governance use. Public release requires explicit approval and non-PII/aggregate review.
- Data retention, backup and deletion rules are documented by the deployment owner.

## Restricted Data Redistribution Gate

The public repository must not redistribute restricted data without approval.

Files requiring explicit approval are recorded in:

```text
docs/compliance/data_redistribution_approval.md
```

If approval is not granted, replace real data with sample/template files before publication.

## Sign-off

| Role | Name | Decision | Date | Notes |
|---|---|---|---|---|
| Project Owner | Pending | Pending | Pending | Pending |
| Data Owner | Pending | Pending | Pending | Pending |
| Legal/Compliance Reviewer | Pending | Pending | Pending | Pending |
| Security Contact | Pending | Pending | Pending | Pending |

## Release Decision

Current decision: **Partially confirmed; final sign-off pending**.

The data redistribution decisions above are documented, but SaQshi should not be marked as fully production/public-release ready until the sign-off table is completed by the appropriate owners.

## Related Documents

- [Data Privacy and Protection Policy](data_privacy_policy.md)
- [Privacy and Data Protection Note](privacy_data_protection.md)
- [Data Redistribution Approval](data_redistribution_approval.md)
- [Public Data Audit](public_data_audit.md)
- [Non-PII Export and Import](non_pii_data_export_import.md)
- [Production Hardening](../security/production_hardening.md)
- [Role Access Matrix](../security/role_access_matrix.md)
