# Privacy and Data Protection Note

SaQshi handles operational health facility quality data. Deployments should treat user identity, facility details, assessment evidence and uploaded documents as sensitive operational data.

## Data Categories

| Data Type | Examples | Protection Need |
| --- | --- | --- |
| User identity | Name, mobile, email, role, login status | Access controlled, encrypted/hashed where applicable |
| Facility data | Facility name, NIN, location, type, state/district/block | Role-scoped access |
| Assessment data | Checklist responses, scores, remarks | Role-scoped access and auditability |
| Evidence files | Images, PDFs, documents, reports | Upload validation and access control |
| CQI data | Action plans, responsible person, target dates, closure remarks | Role-scoped access |
| Certification data | Certification status, score, validity, history | Audit/history protection |

## Recommended Controls

- Use HTTPS for all deployments.
- Store secrets in `.env`.
- Do not commit `.env` to source control.
- Hash passwords using secure password hashing.
- Encrypt sensitive user profile fields where implemented.
- Validate uploaded file types and paths.
- Restrict access by role and geography scope.
- Avoid exposing raw PHP, database or stack errors to users.
- Keep audit logs for important state changes.
- Back up database and uploaded evidence securely.

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

## Local Policy

Each deployment should align with local government, health department and institutional data protection rules. This document is a starting point, not a substitute for legal or policy review.

Before public release or public-sector/health deployment, complete:

```text
docs/compliance/legal_privacy_confirmation.md
docs/compliance/data_redistribution_approval.md
```
