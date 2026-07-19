# Privacy and Data Protection Note

SaQshi handles operational health facility quality data. It is not designed to collect or store patient-level personal health information. Deployments should treat user identity, facility details, assessment evidence and uploaded documents as sensitive operational data.

## Patient-Level PHI Exclusion

The default SaQshi application must not be used to store:

- patient names,
- patient identifiers,
- patient photographs,
- case-sheet or prescription details linked to a person,
- diagnosis/treatment histories linked to a person,
- lab reports linked to a person,
- any patient-identifiable clinical record.

Evidence uploads and free-text remarks should be checked before submission. If a file or remark contains patient-identifiable information, it should be redacted or excluded according to the deployment owner's policy.

## Data Categories

| Data Type | Examples | Protection Need |
| --- | --- | --- |
| User identity | Name, mobile, email, role, login status | Access controlled; profile name/email/mobile encrypted at rest; password hashed |
| Facility data | Facility name, NIN, location, type, state/district/block | State-specific operational data; role-scoped access, export control and auditability |
| Assessment data | Checklist responses, scores, status, operational remarks | State-specific operational data; role-scoped access and auditability |
| Personal assessment fields | Assessor/assessee names, mobile, email, individual names entered in remarks | Encrypt structured assessor/assessee name/contact fields where stored; avoid unnecessary personal details in free text |
| Evidence files | Images, PDFs, documents, reports | Upload validation, authenticated access, role scope, retention policy and optional file encryption if required |
| CQI data | Action plans, responsible post/designation, target dates, closure status, revised score | State-specific operational data; role-scoped access and auditability |
| CQI personal fields | Actual responsible person name, mobile, email, individual names in closure/action remarks | Not expected in current CQI design. If added later, encrypt structured personal/contact fields where stored. |
| Certification data | Certification status, score, validity, history | Audit/history protection |

## Recommended Controls

- Use HTTPS for all deployments.
- Store secrets in `.env`.
- Do not commit `.env` to source control.
- Hash passwords using secure password hashing.
- Encrypt sensitive user profile fields using `SAQSHI_FIELD_ENCRYPTION_KEY`.
- Encrypt structured assessor/assessee personal fields using the same field-encryption helper.
- Validate uploaded file types and paths.
- Keep uploaded evidence behind authenticated/controlled access and apply the default retention schedule: draft/wrong upload immediate or within 30 days, assessment/CQI evidence 3 years, certification evidence validity plus 1 year or 3 years minimum.
- Restrict access by role and geography scope.
- Avoid exposing raw PHP, database or stack errors to users.
- Keep audit logs for important state changes and redact secrets from logs.
- Back up database and uploaded evidence securely.

## Legal and Standards Baseline for SaQshi

For SaQshi deployments in India, the governing privacy baseline is the **Digital Personal Data Protection Act, 2023** and the related **Digital Personal Data Protection Rules, 2025**.

SaQshi follows DPDP-oriented design controls for the type of data it handles:

| SaQshi Control | DPDP-Oriented Purpose |
| --- | --- |
| No patient-level PHI in the default workflow | Data minimisation and purpose limitation |
| User profile and assessor/assessee personal fields encrypted at rest | Reasonable security safeguards for personal data |
| Passwords hashed, not encrypted/decrypted | Credential protection |
| Role/geography-scoped APIs and menus | Access control and accountability |
| Non-PII/sample public data policy | Restricted publication of personal/sensitive data |
| `.env` secret handling | Protection of credentials and system secrets |
| Friendly error handling | Avoids exposing internal system details |

OECD Privacy Guidelines and GDPR Article 32 are only international design references for privacy and security principles. They are not stated as the governing law for SaQshi unless a specific deployment is legally subject to them.

## Encrypt vs Role-Scope Decision

| Data Group | Default Decision | Reason |
| --- | --- | --- |
| Name, mobile, email and login identity | Encrypt/hash | These can identify an individual and are not needed for state-level aggregation. |
| Assessor/assessee name, mobile and email | Encrypt | These are personal assessment fields and are not required in plaintext for scoring, state monitoring or trend analytics. |
| Facility name, NIN, geography, facility type and coordinates | Do not encrypt by default | These are used for filtering, maps, joins, monitoring and state/district/block reports. Protect with role scope and export approval. |
| Checklist scores, status, counts and KPI/outcome numeric data | Do not encrypt by default | These are operational monitoring values needed for analytics and dashboards. Protect through scoped APIs and auditability. |
| Remarks and free text | Deployment decision | If the field may contain personal names/contact details, avoid collection or encrypt/sanitize according to local policy. |
| Evidence uploads | Restricted access by default; optional encryption | Files may contain sensitive operational content. Keep behind authenticated access and apply file encryption if deployment policy requires. |

## Evidence Upload and Log Handling

Current upload controls:

- Upload/delete APIs require authenticated user and facility session.
- Files are limited to 10 MB.
- Allowed extensions are images, PDF, Word, Excel and CSV.
- MIME type is checked with `finfo`; image content is validated with `getimagesize()`.
- Stored names are sanitized and randomized.
- Delete API only deletes paths under local `uploads/` and blocks traversal.
- Runtime uploads are ignored by git.

Current log/audit controls:

- Event logs are written under `api/storage/events/`.
- Event payloads/meta/query strings redact sensitive keys such as password, token, CSRF, captcha, secret, API key, authorization, cookie and session.
- Runtime logs are ignored by git.
- Friendly API errors avoid raw SQL/PHP details in user responses.

Deployment owner responsibilities:

- Apply evidence retention/deletion schedule from `docs/security/evidence_upload_and_log_retention.md`.
- Disable directory listing for uploads.
- Use authenticated download endpoint or non-public storage for stricter deployments.
- Add malware scanning if required.
- Configure log rotation, retention, backup and access restrictions. Default retention: application error logs 90 days, API/event logs 180 days, security incident logs 1 year after incident closure.
- Keep evidence uploads and logs out of the public repository.

## Access Scope

| Role | Access Principle |
| --- | --- |
| Facility user | Own assigned facility data only |
| Block user | Facilities within assigned block |
| District user | Facilities within assigned district |
| Division/regional user | Facilities within assigned division/region |
| State admin | State-wide monitoring and administration |

## Open-source Note

The source code is open, but deployment data is not. Public repositories must not include:

- Real `.env` files
- Real database dumps
- Real user passwords
- Real personal contact data
- Private certificates or uploaded evidence
- Production API keys or mail/SMS credentials

## Operational Encryption Note

User profile updates through `api/admin/v1/users.php` encrypt `f_name`, `m_name`, `l_name`, `mail_id` and `mob_no`. Existing plaintext rows should be migrated once with:

```text
php scripts/encrypt_existing_user_profile_fields.php
```

Run `api/sql/schema/user_profile_encryption_columns.sql` first if the current `s_user` columns are still short `varchar` columns.

Assessor information saves through `api/assessment/v1/assessor_info_save.php` encrypt assessor/assessee name, mobile and email. Existing plaintext assessor rows should be migrated once with:

```text
php scripts/encrypt_existing_assessor_info_fields.php
```

Run `api/sql/schema/assessor_info_encryption_columns.sql` first if the current `assessment_assessor_info` columns are still short `varchar` columns.

## Encryption Method Summary

| Control | Method |
| --- | --- |
| Field encryption format | `enc:v1:<base64(mode + nonce/iv + tag + ciphertext)>` |
| Primary algorithm | AES-256-GCM through PHP OpenSSL |
| Key source | `SAQSHI_FIELD_ENCRYPTION_KEY` from `.env` |
| Key derivation | SHA-256 hash of the configured key to a 256-bit binary key |
| Integrity/authentication | AES-GCM authentication tag for primary mode |
| Passwords | One-way password hash; never decrypted |
| Idempotency | Values already starting with `enc:v1:` are skipped by migration scripts |
| Production requirement | PHP OpenSSL enabled and stable encryption key retained |

## Evidence

SaQshi-specific evidence:

- Governing baseline for India: DPDP Act, 2023, India Code: `https://www.indiacode.nic.in/handle/123456789/22037`
- Operational rules reference: MeitY DPDP Rules page: `https://www.meity.gov.in/documents/act-and-policies/digital-personal-data-protection-rules-2025-gDOxUjMtQWa`
- Government summary of responsible digital personal data use and core principles: `https://www.pib.gov.in/PressReleasePage.aspx?PRID=2190014`
- User profile identity fields are encrypted through `api/admin/v1/users.php`.
- Assessor/assessee identity fields are encrypted through `api/assessment/v1/assessor_info_save.php`.
- Field encryption is implemented through `api/core/Crypto.php`.
- Old plaintext rows can be migrated through `scripts/encrypt_existing_user_profile_fields.php` and `scripts/encrypt_existing_assessor_info_fields.php`.
- Patient-level personal health information is excluded by the default SaQshi workflow and documented in `README.md`, `docs/user/user_guide.md`, `docs/architecture/project_overview.md` and this privacy documentation.

## Local Policy

Each deployment should align with local government, health department and institutional data protection rules. This document is a starting point, not a substitute for legal or policy review.

Before public release or public-sector/health deployment, complete:

```text
docs/compliance/legal_privacy_confirmation.md
docs/compliance/data_redistribution_approval.md
```
