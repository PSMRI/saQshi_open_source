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
| Facility master data | Can real facility names, NINs and hierarchy data be redistributed? | Pending | Complete `data_redistribution_approval.md`. |
| Map/boundary data | Can map/boundary/config data be redistributed? | Pending | Confirm source/license for `map.json`, `biharmap.json`, `upmap.json`. |
| Framework/checklist data | Can NQAS/framework/checkpoint/action-plan content be redistributed? | Pending | Confirm publication rights/source attribution. |
| Certification data | Can certification status/history be published or exported? | Pending deployment decision | Confirm whether outputs must be aggregate, scoped, or restricted. |
| Uploaded evidence | Are uploads protected and retention rules defined? | Pending deployment decision | Define evidence retention/deletion and authenticated access requirements. |
| Logs/audit events | Are logs free from secrets and governed by retention policy? | Pending deployment decision | Define retention, access and rotation policy. |
| Public exports | Are public reports non-PII and authorized? | Pending deployment decision | Use `non_pii_data_export_import.md` and deployment approval. |

## Public-Sector / Health Deployment Checklist

Before deployment, confirm:

- The deploying organization is authorized to collect and process SaQshi data.
- The data controller/owner is identified.
- The security contact and incident-response owner are identified in `MAINTAINERS.md`.
- Role-based access is configured for facility, block, district, division/regional and state users.
- HTTPS is used in production.
- `.env`, logs, backups, uploads and generated keys are protected from public web access.
- Uploaded evidence does not contain patient-level personal health information unless the deployment has explicit authorization and retention rules.
- Public exports are aggregated or non-sensitive unless publication is explicitly approved.
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

Current decision: **Pending**.

SaQshi should not be marked as fully public-release ready until this document and `data_redistribution_approval.md` are completed by the appropriate owners.

## Related Documents

- [Data Privacy and Protection Policy](data_privacy_policy.md)
- [Privacy and Data Protection Note](privacy_data_protection.md)
- [Data Redistribution Approval](data_redistribution_approval.md)
- [Public Data Audit](public_data_audit.md)
- [Non-PII Export and Import](non_pii_data_export_import.md)
- [Production Hardening](../security/production_hardening.md)
- [Role Access Matrix](../security/role_access_matrix.md)
