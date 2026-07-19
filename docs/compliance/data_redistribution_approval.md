# Data Redistribution Approval Record

Version: 1.0  
Created: 2026-07-16  
Status: Resolved for current public source package

## Purpose

This record is used to confirm whether SaQshi public releases may include master/configuration data that looks like real operational data.

## Data Files Requiring Owner Review

| File | Data Type | Current Release Decision | Required Confirmation |
|---|---|---|---|
| `api/config/masters/facilities.json` | Facility hierarchy data | Deployment/local data; ignored as a public-release blocker | Local testing and live deployments may use authorized real facility data in this file. Real facility names/NIN numbers/hierarchy data must not be redistributed in a public source release unless separately approved by the data owner. |
| `api/config/masters/map.json` | Map/boundary configuration | Approved public source | Boundary data is sourced from the public DataMeet maps repository and should retain DataMeet/CC BY 4.0 attribution. |
| `api/config/masters/biharmap.json` | State/district/block boundary-style data | Approved public source | Boundary data is sourced from the public DataMeet maps repository and should retain DataMeet/CC BY 4.0 attribution. |
| `api/config/masters/upmap.json` | State/district/block boundary-style data | Approved public source | Boundary data is sourced from the public DataMeet maps repository and should retain DataMeet/CC BY 4.0 attribution. |
| `api/config/frameworks/saqshi-nqas.json` | NQAS framework/checklist/checkpoint/action-plan configuration | Approved core configuration | Retain unchanged. This is core configurable master data based on NHSRC/QPS NQAS checklist and assessment tools. |
| `api/config/performance/outcome.json` | Outcome indicator definitions | Approved core configuration | Retain unchanged. Outcome/performance configuration is part of the SaQshi quality monitoring master configuration and should be attributed/reviewed with NQAS/QPS source context where applicable. |

## Approval Options

Before public release, choose one:

1. **Approved for public release**: Keep the file in the public repository and document source/license.
2. **Sample only**: Replace with a small sample/template file and instruct deployments to load local data.
3. **External download**: Keep the file outside the repository and document how authorized users can obtain it.

## Sample Replacement Decision

For open-source and DPG readiness review, `api/config/masters/facilities.json` is treated as deployment/runtime master data. A local testing copy may contain real authorized facility data and should not be counted as an open-source or DPG blocker during development review.

For a final public source release, real facility names, NINs and hierarchy data must not be redistributed unless the data owner gives explicit approval. A public distribution package may either include a sample/template facility master or document how implementers should load their own authorized facility master.

`api/config/frameworks/saqshi-nqas.json` and `api/config/performance/outcome.json` remain unchanged because they are approved core project configuration files. The NQAS checklist/framework source context is the NHSRC Quality and Patient Safety/NQAS public standards and tools repository.

The facility master sample follows this hierarchy:

```text
state -> division -> district -> block -> facilities
```

Each implementing organization/state is responsible for using authorized facility master data in its own deployment and for deciding whether any facility master can be published.

## Governance Export Decision

SaQshi report/export features are not treated as open public data publication by default.

| Data / Export Type | Decision |
|---|---|
| Real facility master data | Allowed for authorized local testing/deployment; not publicly redistributable unless approved by the data owner. |
| Certification status/history | May be exported for governance by the organization/state/division/district/block using SaQshi, according to role scope and authorization. Not open public data by default. |
| Assessment/CQI/performance reports | May be exported by authorized users within the implementing organization for monitoring and governance. Not open public data by default. |
| Public reports | Not publicly available by default. Public release requires organization/data-owner approval and non-PII/aggregate review. |

## NQAS / NHSRC Source Context

The framework/checklist/checkpoint/action-plan configuration is maintained as SaQshi core configurable master data aligned to NQAS assessment content.

Reference sources:

- NHSRC National Quality Assurance Standards overview: `https://nhsrcindia.org/national-quality-assurance-standards`
- NHSRC/QPS Revised National Quality Assurance Standards archive: `https://qps.nhsrcindia.org/national-quality-assurance-standards/quality-RNQAS`
- NHSRC/QPS NQAS Guidelines and assessment tools/checklists: `https://qps.nhsrcindia.org/hi/node/2105`

Any future checklist, outcome or action-plan configuration update should record the source/version/date of the NQAS/NHSRC reference used.

## Boundary Data Source

Map and boundary configuration files are based on the public DataMeet maps repository:

```text
https://github.com/datameet/maps
```

DataMeet's maps repository states that, unless explicitly stated, datasets are shared under CC BY 4.0. SaQshi public releases should keep attribution to the relevant DataMeet dataset and DataMeet India community when boundary data is included or displayed.

## Approval Sign-off

| Role | Name | Decision | Date |
|---|---|---|---|
| Project/Data Source | DataMeet India community | Public boundary data source recorded; CC BY 4.0 attribution required. | 2026-07-16 |
| SaQshi Release Maintainer | SaQshi project maintainer | Local facility master data is treated as deployment data and ignored as an open-source/DPG blocker; public redistribution still requires data-owner approval or sample/template packaging; approved NQAS-aligned framework/outcome files retained; DataMeet boundary source recorded. | 2026-07-16 |
| Legal/Compliance Reviewer | Release reviewer | No additional redistribution blocker recorded for current map/boundary files beyond attribution. | 2026-07-16 |

## Notes

Facility master data is treated as deployment/runtime data for readiness review. The presence of real authorized facility JSON in a local testing copy is ignored for open-source and DPG assessment. Public redistribution of real facility names, NINs and hierarchy data still requires explicit data-owner approval or replacement with sample/template data. Framework/checklist/action-plan and outcome indicator configuration are retained unchanged as approved core configuration files aligned to NHSRC/QPS NQAS references. Certification, assessment, CQI and performance exports are governance-scoped for authorized organizations/users and are not public reports by default. Boundary/map configuration is recorded as public DataMeet-sourced data under CC BY 4.0 attribution.
