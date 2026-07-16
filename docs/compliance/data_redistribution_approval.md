# Data Redistribution Approval Record

Version: 1.0  
Created: 2026-07-16  
Status: Pending project-owner confirmation

## Purpose

This record is used to confirm whether SaQshi public releases may include master/configuration data that looks like real operational data.

## Data Files Requiring Owner Review

| File | Data Type | Current Release Decision | Required Confirmation |
|---|---|---|---|
| `api/config/masters/facilities.json` | Facility names, hierarchy, facility IDs and NIN numbers | Pending | Confirm whether this is public/open government master data and may be redistributed. |
| `api/config/masters/map.json` | Map/boundary configuration | Pending | Confirm source and license of boundary/configuration data. |
| `api/config/masters/biharmap.json` | State/district/block boundary-style data | Pending | Confirm source and license of boundary data. |
| `api/config/masters/upmap.json` | State/district/block boundary-style data | Pending | Confirm source and license of boundary data. |
| `api/config/frameworks/saqshi-nqas.json` | Framework/checklist/action-plan content | Pending | Confirm publication rights and source attribution. |
| `api/config/performance/outcome.json` | Outcome indicator definitions | Pending | Confirm publication rights and source attribution. |

## Approval Options

Before public release, choose one:

1. **Approved for public release**: Keep the file in the public repository and document source/license.
2. **Sample only**: Replace with a small sample/template file and instruct deployments to load local data.
3. **External download**: Keep the file outside the repository and document how authorized users can obtain it.

## Approval Sign-off

| Role | Name | Decision | Date |
|---|---|---|---|
| Project Owner | Pending | Pending | Pending |
| Data Owner | Pending | Pending | Pending |
| Legal/Compliance Reviewer | Pending | Pending | Pending |

## Notes

Until this record is completed, SaQshi should not be treated as fully cleared for public redistribution of real facility master or boundary/configuration data.
