# Non-PII Data Export and Import

Version: 1.0  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This document describes how SaQshi should support privacy-safe data portability for DPG readiness.

## Non-PII Export Principle

Public or shared exports should avoid direct personal identifiers unless explicitly authorized. Exports should prefer aggregated facility, district, division, state or programme-level data.

## Recommended Non-PII Export Datasets

| Dataset | Recommended Format | Notes |
| --- | --- | --- |
| Facility assessment summary | CSV/XLSX/JSON | Facility ID/NIN may be included only if authorized. |
| Department progress summary | CSV/XLSX/JSON | Completed/pending checkpoint counts and scores. |
| CQI summary | CSV/XLSX/JSON | Open, closed, pending and overdue gap counts. |
| Performance month summary | CSV/XLSX/JSON | KPI/outcome month completion and result values. |
| Certification summary | CSV/XLSX/JSON | Status, type, score, valid from and expiry date. |
| State monitoring summary | CSV/XLSX/JSON | Aggregated counts by geography and facility type. |

## Data That Should Not Be in Public Exports

- Password hashes.
- Session tokens or CSRF tokens.
- Raw user mobile/email unless authorized.
- Uploaded evidence files unless explicitly approved.
- Patient-level data.
- Raw error logs or server paths.

## Import Guidance

Recommended import files:

- Facility master data.
- Department/facility type mappings.
- Framework/checklist JSON.
- KPI/outcome indicator JSON.
- Map boundary configuration.

Imports should validate:

- Required fields.
- Duplicate NIN/facility identifiers.
- JSON syntax.
- Facility type mappings.
- Numeric ranges for scores and indicators.

## API and Report Alignment

Existing report/download endpoints should be reviewed before public release to confirm:

- Role scope is applied.
- Only allowed fields are exported.
- Large exports are paginated or streamed.
- Exported files do not leak server paths or internal errors.

## DPG Readiness Status

SaQshi has report/download capabilities today. A formal DPG-ready export/import package should be finalized with stable schemas, sample files and privacy-safe field definitions before nomination.

