# Non-PII Data Export and Import

Version: 1.1  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This document describes how SaQshi should support privacy-safe data portability for DPG readiness.

## Non-PII Export Principle

SaQshi exports are intended for authorized organization/governance use by facility, block, district, division/regional and state users. They are not public reports by default.

Public or externally shared exports should avoid direct personal identifiers unless explicitly authorized. Exports should prefer aggregated facility, district, division, state or programme-level data and require organization/data-owner approval before public release.

## Recommended Non-PII Export Datasets

| Dataset | Recommended Format | Notes |
| --- | --- | --- |
| Facility assessment summary | CSV/XLSX/JSON | Facility ID/NIN may be included for authorized governance users only. Public release requires approval. |
| Department progress summary | CSV/XLSX/JSON | Completed/pending checkpoint counts and scores. |
| CQI summary | CSV/XLSX/JSON | Open, closed, pending and overdue gap counts. |
| Performance month summary | CSV/XLSX/JSON | KPI/outcome month completion and result values. |
| Certification summary | CSV/XLSX/JSON | Status, type, score, valid from and expiry date for authorized state/division/district/block/organization governance use. Not public by default. |
| State monitoring summary | CSV/XLSX/JSON | Aggregated counts by geography and facility type. |

## Stable Public Sample Exports

Stable, privacy-safe sample export files are included under:

```text
docs/compliance/sample_exports/
```

These files use fictional/sample values only and are intended for DPG review, integration testing, documentation and downstream implementer orientation.

Download the complete package:

[Download Non-PII Sample Exports ZIP](sample_exports/non_pii_sample_exports.zip?download=1)

| Sample File | Dataset | Download |
|---|---|---|
| `sample_exports/facility_assessment_summary_sample.csv` | Facility assessment summary | [CSV](sample_exports/facility_assessment_summary_sample.csv?download=1) |
| `sample_exports/department_progress_summary_sample.csv` | Department progress summary | [CSV](sample_exports/department_progress_summary_sample.csv?download=1) |
| `sample_exports/cqi_summary_sample.csv` | CQI summary | [CSV](sample_exports/cqi_summary_sample.csv?download=1) |
| `sample_exports/performance_month_summary_sample.csv` | Performance month summary | [CSV](sample_exports/performance_month_summary_sample.csv?download=1) |
| `sample_exports/certification_summary_sample.csv` | Certification summary | [CSV](sample_exports/certification_summary_sample.csv?download=1) |
| `sample_exports/state_monitoring_summary_sample.json` | State monitoring summary | [JSON](sample_exports/state_monitoring_summary_sample.json?download=1) |
| `sample_exports/export_field_dictionary.csv` | Stable field names and privacy classification | [CSV](sample_exports/export_field_dictionary.csv?download=1) |

## Data That Should Not Be in Public Exports

- Password hashes.
- Session tokens or CSRF tokens.
- Raw user mobile/email unless authorized.
- Uploaded evidence files unless explicitly approved.
- Patient-level data.
- Raw error logs or server paths.

## Import Guidance

Recommended import files:

- Facility master data for authorized local deployments only. Real facility master data must not be redistributed in the public source package.
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

Existing report/download endpoints should be reviewed before production/public release to confirm:

- Role scope is applied.
- Only allowed fields are exported.
- Large exports are paginated or streamed.
- Exported files do not leak server paths or internal errors.
- Public publication is disabled unless explicitly approved by the organization/data owner.

## Public Sample Data Confirmation

The public sample export package contains no real personal data, patient data, credentials, uploads, evidence URLs, logs or production database extracts. Facility names and facility codes use fictional `Sample ...` values only.

## DPG Readiness Status

SaQshi has report/download capabilities and now includes stable public sample export files with privacy-safe field definitions. Production deployments must still apply role scope and local data sharing approvals before exporting real facility-level, certification-level, assessment, CQI or performance information. Public publication is not enabled by default.
