# Non-PII Sample Export Package

Version: 1.0  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This folder provides stable sample export files for DPG and open-source review. The files are intentionally small and use fictional/sample values only.

## Privacy Rule

These sample exports must not contain:

- real user names,
- real mobile numbers,
- real email addresses,
- passwords, hashes, sessions or CSRF tokens,
- uploaded evidence links,
- patient-level data,
- real facility-sensitive operational data unless separately approved.

## Files

| File | Purpose | Download |
|---|---|---|
| `non_pii_sample_exports.zip` | Complete sample export package. | [Download ZIP](non_pii_sample_exports.zip?download=1) |
| `facility_assessment_summary_sample.csv` | Facility assessment score/status summary. | [Download CSV](facility_assessment_summary_sample.csv?download=1) |
| `department_progress_summary_sample.csv` | Department checkpoint completion summary. | [Download CSV](department_progress_summary_sample.csv?download=1) |
| `cqi_summary_sample.csv` | CQI gap/action/closure summary. | [Download CSV](cqi_summary_sample.csv?download=1) |
| `performance_month_summary_sample.csv` | KPI/outcome month completion summary. | [Download CSV](performance_month_summary_sample.csv?download=1) |
| `certification_summary_sample.csv` | Facility certification status summary. | [Download CSV](certification_summary_sample.csv?download=1) |
| `state_monitoring_summary_sample.json` | Aggregated state monitoring dashboard sample. | [Download JSON](state_monitoring_summary_sample.json?download=1) |
| `export_field_dictionary.csv` | Stable field names and privacy classification. | [Download CSV](export_field_dictionary.csv?download=1) |

## Sample Data Convention

All sample records use fictional placeholders such as:

```text
Sample State
Sample Division
Sample District
Sample Block
Sample Health Facility
SAMPLE-NIN-0001
```

Real deployments may export the same schema with authorized local data according to role scope, privacy approval and applicable policy.
