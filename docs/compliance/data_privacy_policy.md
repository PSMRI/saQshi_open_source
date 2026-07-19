# Data Privacy and Protection Policy

Version: 1.0  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This document defines privacy expectations for SaQshi deployments. It is a project-level guide and must be adapted to the legal, administrative and health-data requirements of the deploying organization.

## Patient-Level PHI Position

SaQshi is not designed to use, process, or store patient-level personal health information. The default application scope is facility quality assessment and monitoring, not patient care documentation.

Users and deployment owners must not enter or upload:

- patient names,
- patient identifiers or registration numbers,
- case-sheet extracts,
- diagnosis or treatment details linked to an individual,
- patient photographs,
- prescriptions or lab reports linked to an individual,
- any other patient-identifiable clinical information.

If a deployment owner intentionally extends SaQshi to handle patient-level information, that extension is outside the default open-source privacy position and requires separate legal approval, patient-data governance, retention rules, access control, security review and deployment-specific documentation.

## Data Categories

| Data Type | Examples | Privacy Position |
| --- | --- | --- |
| User account data | Name, mobile number, email, role, login identifier | Sensitive administrative data; password values must be hashed and profile identity fields should be encrypted at rest. |
| Facility master data | Facility name, NIN, type, location, administrative geography | State-specific operational data. Do not encrypt by default because it is required for scoped dashboards, search, maps, joins and reports. Protect through role-scoped access, export controls and auditability. |
| Assessment data | Checklist responses, scores, status, operational remarks | State-specific operational data. Do not encrypt score/status fields by default; restrict to authorized facility/block/district/division/state roles and keep auditability. |
| Personal assessment fields | Assessor name, assessee name, assessor/assessee mobile and email, individual names entered in remarks | Personal/sensitive administrative data. Encrypt structured assessor/assessee name/contact fields at rest; avoid entering unnecessary personal details in free-text remarks. |
| Evidence files | Images, PDFs, documents, certificates | Potentially sensitive operational evidence. Validate uploads, keep access authenticated and role-scoped, store outside public execution paths where possible, and apply file encryption if required by deployment policy. |
| CQI data | Action plans, target dates, closure status, revised scores | State-specific operational data. Do not encrypt status/date/score by default; protect through role scope, audit logs and export controls. |
| CQI responsibility field | Responsible post/designation such as CHO, RM, MOIC, Staff Nurse | Operational role/post data. Do not encrypt by default when it stores designation only. If deployment starts storing actual person name/mobile/email, classify it as Personal / Sensitive Administrative Data and encrypt structured fields. |
| Performance data | KPI/outcome numerator, denominator, result, remarks | Programme monitoring data. Do not encrypt numeric/monthly indicators by default; publish only aggregated/non-sensitive outputs unless authorized. |
| Certification data | Status, type, score, validity, renewal history | Programme monitoring data. Protect through role scope, history/audit controls and deployment-specific publication approval. |

## Data Protection Classification

SaQshi uses three practical data-protection levels:

| Classification | What It Covers | Default Handling |
| --- | --- | --- |
| Personal / Sensitive Administrative Data | User name, mobile, email, assessor/assessee identity/contact details and any direct personal identifiers. | Encrypt structured fields at rest, hash passwords, restrict access by role, avoid logging sensitive values. |
| Restricted State Operational Data | Facility master details, NIN, geo coordinates, assessment scores, CQI status, KPI/outcome entries, certification status and state monitoring views. | Do not encrypt by default. Use role/geography scope, authenticated APIs, pagination/search, export approval and audit logs. |
| Public / Sample / Aggregated Data | Open-source sample data, non-PII templates, approved framework configuration and approved aggregate public reports. | May be published only after source/licensing and data-owner approval. |

Encryption is not applied to every state operational field because SaQshi must aggregate, filter, map and report this data across large facility counts. The primary protection for state monitoring data is role-scoped API access and controlled export. Encryption is reserved for personal/contact fields and can be extended to uploads or sensitive free-text fields if a deployment policy requires it.

## Privacy Principles

- Collect only data needed for assessment, CQI, performance, certification and monitoring.
- Do not store patient-level personal health information in SaQshi.
- Keep credentials and secrets outside source code using `.env`.
- Set a stable `SAQSHI_FIELD_ENCRYPTION_KEY` before production use so user profile fields can be encrypted/decrypted consistently.
- Use role-based access control for facility, block, district, division and state data.
- Use aggregated or non-PII exports for public reporting.
- Keep logs free from passwords, tokens and sensitive payloads.
- Define data retention and deletion rules for each deployment.

## Legal and Standards Baseline for SaQshi

For SaQshi deployments in India, the governing privacy baseline is the **Digital Personal Data Protection Act, 2023** and the related **Digital Personal Data Protection Rules, 2025** as notified/applicable by the Government of India.

SaQshi is designed to support DPDP-aligned handling of digital personal data, but deployment compliance is not automatic. The deploying organization must still confirm its Data Fiduciary role, lawful purpose, notice/authorization model, retention period, user training, incident response, and public export rules.

| Governing / Reference Item | SaQshi Position | SaQshi Evidence |
| --- | --- | --- |
| DPDP Act, 2023, India | Governing privacy baseline for SaQshi deployments in India where digital personal data is processed. | SaQshi classifies user profile and assessor/assessee identity/contact fields as personal data and protects them through encryption, hashing, access control and minimisation. |
| DPDP Rules, 2025, India | Operational safeguard reference for Indian deployments. | SaQshi documents encryption, role-scoped access, non-PII exports, `.env` secret handling, friendly error handling, audit/event direction and production hardening. |
| SaQshi patient-level PHI position | SaQshi is not a patient record system and does not use or store patient-level personal health information in the default workflow. | User guide, project overview, privacy policy and release docs instruct users not to enter patient names, identifiers, case-sheet details or patient-identifiable evidence. |
| OECD Privacy Guidelines | Non-governing international design reference only. | SaQshi uses similar principles such as minimisation, purpose limitation, access control and accountability, but OECD is not stated as the governing law. |
| GDPR Article 32 | Non-governing international security design reference only, unless a specific deployment is legally subject to GDPR. | SaQshi uses encryption and access control as appropriate technical safeguards, but GDPR compliance must be separately assessed if applicable. |

Primary official references:

- India Code DPDP Act, 2023: `https://www.indiacode.nic.in/handle/123456789/22037`
- MeitY DPDP Rules page: `https://www.meity.gov.in/documents/act-and-policies/digital-personal-data-protection-rules-2025-gDOxUjMtQWa`
- PIB DPDP Rules notification summary: `https://www.pib.gov.in/PressReleasePage.aspx?PRID=2190014`

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

## User Profile Field Encryption

The facility user profile API encrypts the following `s_user` fields when profile details are saved or updated:

- `f_name`
- `m_name`
- `l_name`
- `mail_id`
- `mob_no`

Before using this in an existing deployment, apply `api/sql/schema/user_profile_encryption_columns.sql` so the encrypted values fit in the database columns, then run:

```text
php scripts/encrypt_existing_user_profile_fields.php
```

The script is idempotent and skips values already stored with the `enc:v1:` prefix.

## Assessor / Assessee Field Encryption

The assessor information API encrypts structured assessor/assessee personal fields in `assessment_assessor_info`:

- `assessor_name`
- `assessor_mobile`
- `assessor_email`
- `assessee_name`
- `assessee_mobile`
- `assessee_email`

Designation fields remain plain because they represent post/role values rather than a personal identity.

Before using encryption on an existing deployment, apply `api/sql/schema/assessor_info_encryption_columns.sql`, then run:

```text
php scripts/encrypt_existing_assessor_info_fields.php
```

The script is idempotent and skips values already stored with the `enc:v1:` prefix.

## Encryption Methods Used

SaQshi uses field-level encryption for structured personal identity/contact fields and password hashing for credentials.

| Area | Method | Implementation Evidence | Notes |
| --- | --- | --- | --- |
| User profile name/email/mobile | Field-level encryption using `Crypto::encrypt()` | `api/admin/v1/users.php`, `api/core/Crypto.php` | Stored with `enc:v1:` prefix. Decrypted only when returned to authorized UI/API flows. |
| Assessor/assessee name/mobile/email | Field-level encryption using `Crypto::encrypt()` | `api/assessment/v1/assessor_info_save.php`, `api/assessment/v1/assessor_info_get.php`, `api/core/Crypto.php` | Designation/post fields remain plain because they are role values, not personal identifiers. |
| Existing plaintext profile rows | One-time idempotent migration | `scripts/encrypt_existing_user_profile_fields.php` | Skips fields already starting with `enc:v1:`. |
| Existing plaintext assessor rows | One-time idempotent migration | `scripts/encrypt_existing_assessor_info_fields.php` | Skips fields already starting with `enc:v1:`. |
| Passwords | Secure password hashing | `api/core/Auth.php` | Passwords are not decrypted. Legacy plain passwords are upgraded to hashes on successful login/update where configured. |
| Database connection secrets | Environment variables | `.env.example`, `api/assets/conn/db.php` | Real `.env` must not be committed. |

Field encryption details from `api/core/Crypto.php`:

- Storage format: `enc:v1:<base64(mode + nonce/iv + tag + ciphertext)>`
- Primary cipher: AES-256-GCM through PHP OpenSSL (`aes-256-gcm`)
- IV length: 12 bytes
- Authentication tag length: 16 bytes
- Key source: `SAQSHI_FIELD_ENCRYPTION_KEY` from `.env`
- Key derivation in current helper: SHA-256 hash of `SAQSHI_FIELD_ENCRYPTION_KEY` to produce a 256-bit binary key
- Empty values are left empty
- Already encrypted values are not encrypted again
- Fallback mode exists only for runtimes without OpenSSL and uses HMAC-based authenticated XOR keystream storage with `H1` mode marker

Production requirement:

- Enable PHP OpenSSL in production so AES-256-GCM is used.
- Set a long random `SAQSHI_FIELD_ENCRYPTION_KEY` before encrypting real data.
- Keep `SAQSHI_FIELD_ENCRYPTION_KEY` stable. Changing it without a planned decrypt/re-encrypt migration will make existing encrypted values unreadable.
- Run column-widening SQL before encrypting existing plaintext rows:
  - `api/sql/schema/user_profile_encryption_columns.sql`
  - `api/sql/schema/assessor_info_encryption_columns.sql`

Current encryption scope:

| Encrypted | Not Encrypted by Default |
| --- | --- |
| User name, mobile, email | Facility master, NIN, geography and facility type |
| Assessor/assessee name, mobile, email | Assessment scores/status/counts |
| Passwords are hashed, not encrypted | CQI action plan/status/target date/revised score |
| Optional future: actual personal names in CQI fields if added | KPI/outcome numeric and month-wise monitoring data |

## Legal and Policy Evidence

This evidence is specific to SaQshi. It records why SaQshi treats some fields as encrypted personal data and other fields as restricted operational monitoring data.

| Evidence Area | SaQshi Evidence | DPDP Alignment |
| --- | --- | --- |
| No patient-level PHI by default | SaQshi is documented as facility quality/monitoring software, not a patient record system. Users are instructed not to enter patient names, identifiers, case-sheet details or patient-identifiable evidence. | Data minimisation and purpose limitation. |
| Personal data identification | User profile name/mobile/email and assessor/assessee name/mobile/email are classified as personal/sensitive administrative data. | Personal data protection and reasonable safeguards. |
| Encryption at rest | `api/admin/v1/users.php` encrypts profile identity fields; `api/assessment/v1/assessor_info_save.php` encrypts assessor/assessee personal fields; `api/core/Crypto.php` implements the encryption helper. | Security safeguards for digital personal data. |
| Password protection | `api/core/Auth.php` hashes passwords; passwords are not decrypted. | Credential protection and access security. |
| Existing plaintext migration | `scripts/encrypt_existing_user_profile_fields.php` and `scripts/encrypt_existing_assessor_info_fields.php` migrate existing plaintext personal fields. | Remediation and security hardening. |
| Restricted operational data | Facility, assessment, CQI, performance and certification monitoring data are protected through role/geography scope rather than blanket encryption. | Purpose-based access and accountability. |
| Deployment sign-off | `docs/compliance/legal_privacy_confirmation.md` records required legal/privacy confirmation before production/public release. | Data Fiduciary/deployment-owner accountability. |

SaQshi follows DPDP-oriented design controls for the data it handles, but final DPDP compliance must be confirmed by the deployment owner/legal reviewer for the actual deployment context.

## Open Items Before DPG Submission

- Confirm applicable legal basis and consent/authorization model.
- Real facility master data must not be redistributed publicly in the source package; use sample/template data publicly and authorized local facility master data in deployment.
- Public report publication is not enabled by default; report downloads are for authorized organization/governance use unless separately approved for public release.
- Confirm retention period for evidence files and audit logs.
- Confirm official data controller and security contact.
- Complete legal/privacy sign-off in `legal_privacy_confirmation.md`.

## Related Documents

- [Privacy and Data Protection](privacy_data_protection.md)
- [Legal and Privacy Confirmation](legal_privacy_confirmation.md)
- [Role Access Matrix](../security/role_access_matrix.md)
- [Production Hardening](../security/production_hardening.md)
- [Security Policy](../../SECURITY.md)
