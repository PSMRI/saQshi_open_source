# Public Data Audit

Version: 1.0  
Reviewed: 2026-07-16

## Purpose

This document records the public-release review for real/private data in the SaQshi source package.

The goal is to ensure the public repository does not include live user data, passwords, uploaded evidence, logs, generated certificates, generated keys, database dumps, or data that cannot be redistributed.

## Current Result

| Area | Status | Notes |
|---|---|---|
| Local `.env` | Local runtime file; release-gated | Use `.env.example` publicly. Local `.env` may exist for testing/deployment but must remain untracked and unpublished. |
| Upload files | Runtime-only; release-gated | `uploads/README.md` documents runtime-only usage. Uploads must not be published. |
| Event logs | Runtime-only; release-gated | `api/storage/README.md` documents runtime-only usage. Event logs may exist locally and must not be published. |
| Generated key files | Runtime-only; release-gated | Deployments generate keys locally. Generated keys may exist locally and must not be published. |
| Database dumps | Not found in current scan | Sanitized schema is still pending separately. |
| Certificates/private keys | Runtime-only; release-gated | Generated keys/certs must stay outside version control and public packages. |
| Facility master data | Deployment/local data; not a readiness blocker | `api/config/masters/facilities.json` may contain authorized real facility data in local testing or live deployments. Public redistribution of real facility names, NIN numbers and hierarchy data still requires explicit data-owner approval or sample/template packaging. |
| Framework/checklist JSON | Approved core configuration | `api/config/frameworks/saqshi-nqas.json` intentionally remains unchanged and is treated as approved NQAS-aligned core configuration. Source context: NHSRC/QPS NQAS standards, guidelines and assessment tools. |
| Outcome indicator JSON | Approved core configuration | `api/config/performance/outcome.json` intentionally remains unchanged and is treated as approved project quality-monitoring configuration. |
| Map/boundary configuration | Approved public source with attribution | `api/config/masters/map.json`, `biharmap.json` and `upmap.json` are recorded as DataMeet public boundary data. DataMeet maps are public and use CC BY 4.0 unless explicitly stated. |
| Data approval record | Done for current public source package | `docs/compliance/data_redistribution_approval.md` records the current data source decisions and attribution requirements. |

## Facility Master Data Review

`api/config/masters/facilities.json` is treated as deployment/runtime data for review purposes.

For real deployments, the project owner should choose one of the following:

1. Use authorized real facility master data locally for testing or deployment.
2. For public distribution, include only sample/template data unless the data owner approves redistribution.
3. Document the JSON schema/template and instruct deployments to load their own authorized facility master.

Recommended safest release model:

- Keep sample/template facility data in the public release package unless redistribution is approved.
- Keep full local facility masters controlled by the implementing organization/state.
- Document the expected facility JSON format in the GitBook.

## Report and Certification Export Review

SaQshi reports and certification exports are not public data by default.

- Certification status/history may be exported by authorized state/division/district/block/organization users for governance.
- Assessment, CQI and performance reports may be exported by authorized users for monitoring and governance.
- Public report publication requires explicit organization/data-owner approval and non-PII/aggregate review.

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
- all remaining master/config data has redistribution permission or has been replaced with samples,
- any report intended for public publication has explicit organization/data-owner approval.
