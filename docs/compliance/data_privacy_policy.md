# Data Privacy and Protection Policy

Version: 1.0  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This document defines privacy expectations for SaQshi deployments. It is a project-level guide and must be adapted to the legal, administrative and health-data requirements of the deploying organization.

## Data Categories

| Data Type | Examples | Privacy Position |
| --- | --- | --- |
| User account data | Name, mobile number, email, role, login identifier | Sensitive administrative data; protect with access control and encryption/hashing where applicable. |
| Facility master data | Facility name, NIN, type, location, administrative geography | Public or restricted depending on source licensing and government policy. Confirm before public release. |
| Assessment data | Checklist responses, scores, remarks, assessor details | Programme operational data; restrict to authorized roles. |
| Evidence files | Images, PDFs, documents, certificates | Potentially sensitive; validate uploads and restrict access. |
| Performance data | KPI/outcome numerator, denominator, result, remarks | Programme monitoring data; publish only aggregated/non-sensitive outputs unless authorized. |
| Certification data | Status, type, score, validity, renewal history | Programme monitoring data; publish only as permitted. |

## Privacy Principles

- Collect only data needed for assessment, CQI, performance, certification and monitoring.
- Avoid storing patient-level personal health information in SaQshi.
- Keep credentials and secrets outside source code using `.env`.
- Use role-based access control for facility, block, district, division and state data.
- Use aggregated or non-PII exports for public reporting.
- Keep logs free from passwords, tokens and sensitive payloads.
- Define data retention and deletion rules for each deployment.

## Access Control Expectations

- Facility users access only their assigned facility.
- Block users access only block-level facilities.
- District users access only district-level facilities.
- Division/regional users access assigned division/regional data.
- State users access state-level monitoring data.
- Administrative actions should be auditable.

## Deployment Requirements

- Serve production deployments over HTTPS.
- Protect `.env`, upload folders, logs and backup files from public web access.
- Back up database and uploaded evidence securely.
- Rotate credentials after suspected exposure.
- Use production hardening guidance before public deployment.

## Open Items Before DPG Submission

- Confirm applicable legal basis and consent/authorization model.
- Confirm whether facility master data can be redistributed publicly.
- Confirm retention period for evidence files and audit logs.
- Confirm official data controller and security contact.
- Complete legal/privacy sign-off in `legal_privacy_confirmation.md`.

## Related Documents

- [Privacy and Data Protection](privacy_data_protection.md)
- [Legal and Privacy Confirmation](legal_privacy_confirmation.md)
- [Role Access Matrix](../security/role_access_matrix.md)
- [Production Hardening](../security/production_hardening.md)
- [Security Policy](../../SECURITY.md)
