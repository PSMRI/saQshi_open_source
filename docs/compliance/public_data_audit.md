# Public Data Audit

Version: 1.0  
Reviewed: 2026-07-16

## Purpose

This document records the public-release review for real/private data in the SaQshi source package.

The goal is to ensure the public repository does not include live user data, passwords, uploaded evidence, logs, generated certificates, generated keys, database dumps, or data that cannot be redistributed.

## Current Result

| Area | Status | Notes |
|---|---|---|
| Local `.env` | Removed from public source folder | Use `.env.example` only. |
| Upload files | Removed from public source folder | `uploads/README.md` now documents runtime-only usage. |
| Event logs | Removed from public source folder | `api/storage/README.md` now documents runtime-only usage. |
| Generated key files | Removed from public source folder | Deployments must generate keys locally. |
| Database dumps | Not found in current scan | Sanitized schema is still pending separately. |
| Certificates/private keys | Runtime key files removed | Future generated keys/certs must stay outside version control. |
| Facility master data | Requires owner review | `api/config/masters/facilities.json` contains facility names and NIN numbers. Confirm redistribution permission before public release. |
| Framework/checklist JSON | Requires owner review | `api/config/frameworks/saqshi-nqas.json` is large and should be verified for publication rights. |
| Data approval record | Pending | `docs/compliance/data_redistribution_approval.md` records files requiring owner/legal confirmation. |

## Facility Master Data Review

`api/config/masters/facilities.json` appears to contain real facility master data, including facility names and NIN numbers.

Before public release, the project owner should confirm one of the following:

1. The facility master data is public/open government data and may be redistributed with SaQshi.
2. The public repository should include a reduced sample file instead of the full facility master.
3. The public repository should include only the JSON schema/template and instruct deployments to load their own facility master.

Recommended safest release model:

- Keep `api/config/masters/facilities.sample.json` as a small example.
- Keep the full facility master outside the public repository unless redistribution rights are confirmed.
- Document the expected facility JSON format in the GitBook.

## Release Rule

Before every public release, run:

```text
php tools/release_readiness_check.php
```

Then manually confirm:

- no runtime uploads exist,
- no logs exist,
- no generated keys/certificates exist,
- no real credentials exist,
- no database dumps exist,
- all master/config data has redistribution permission.
