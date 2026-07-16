# Governance and Ownership

Version: 1.0  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This document records the governance information expected for open-source and DPG readiness. Deployment teams should replace placeholders with official ownership details before public release.

## Ownership Areas

| Area | Ownership Requirement |
| --- | --- |
| Source code | Confirm copyright holder and public repository owner. |
| SaQshi name/branding | Define permitted use of name, logo and public references. |
| Framework JSON | Confirm rights to publish checklist/framework configuration. |
| Facility master data | Confirm source, license and redistribution permissions. |
| Map boundary data | Confirm source, license and redistribution permissions. |
| Documentation | Confirm docs are released under the project license or documented license. |

## Maintainer Roles

| Role | Responsibility |
| --- | --- |
| Product Maintainer | Reviews roadmap, use cases and release priorities. |
| Technical Maintainer | Reviews architecture, API, database and security changes. |
| Documentation Maintainer | Keeps GitBook, README, API docs and release notes current. |
| Security Contact | Receives and triages vulnerability reports. |
| Release Manager | Confirms release checklist, versioning, changelog and distribution package. |

## Decision Process

Recommended process:

1. Open an issue or change request.
2. Discuss impact on users, APIs, data model and security.
3. Review code/documentation changes.
4. Run relevant tests and lint checks.
5. Update changelog and release notes.
6. Merge and tag release after approval.

## Public Release Requirements

- Confirm official maintainers.
- Confirm security contact.
- Confirm third-party licenses.
- Confirm no secrets or sensitive data are included.
- Confirm database setup can be reproduced.
- Confirm public data redistribution permissions.

## Official Contact Record

Official maintainer, security, release, issue-triage, data-owner and legal/compliance contacts must be recorded in:

```text
MAINTAINERS.md
```

Public release should remain blocked while any required row in `MAINTAINERS.md` is marked `Pending`.

## Related Documents

- [Contributing](../../CONTRIBUTING.md)
- [Maintainers and Release Contacts](../../MAINTAINERS.md)
- [Security Policy](../../SECURITY.md)
- [Release Checklist](release_checklist.md)
- [Open Source Readiness Checklist](open_source_readiness_checklist.md)
