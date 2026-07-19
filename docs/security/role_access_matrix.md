# Role Access Matrix

Version: 1.0  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This document describes expected role-level access for SaQshi. Actual deployments should verify role IDs and permissions against the active database and sidebar/router configuration.

## Role Scope

| Role | Data Scope |
| --- | --- |
| Facility User | Assigned facility only. |
| Block User | Facilities within assigned block. |
| District User | Facilities within assigned district. |
| Division / Regional User | Facilities within assigned division/region. |
| State User / State Admin | Facilities within assigned state or state-level scope. |
| Assessor | Facilities explicitly mapped by state administration. |
| System Admin | Configuration and administration scope as assigned by deployment policy. |

## Module Access Matrix

| Module / Page Area | Facility | Assessor | Block | District | Division | State | System Admin |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Facility assessment entry | Yes | Mapped facilities | View only | View only | View only | View only | Configurable |
| Department activation | Yes | Mapped facilities | No | No | No | No | Configurable |
| Assessor information | Yes | Mapped facilities | View only | View only | View only | View only | Configurable |
| Checklist scoring | Yes | Mapped facilities | View only | View only | View only | View only | Configurable |
| Gap analysis | Yes | Mapped facilities | View only | View only | View only | View only | Configurable |
| Action plan | Yes | Mapped facilities | View only | View only | View only | View only | Configurable |
| Gap closure | Yes | Mapped facilities | View only | View only | View only | View only | Configurable |
| KPI/outcome entry | Yes | Optional by deployment | View only | View only | View only | View only | Configurable |
| Facility reports | Yes | Mapped facilities | Yes | Yes | Yes | Yes | Configurable |
| Certification status | View | Mapped facilities view | View | View | View | Manage if authorized | Configurable |
| Certification update | No | No | No | No | No | Authorized only | Configurable |
| State monitoring dashboard | No | No | Scoped | Scoped | Scoped | Yes | Configurable |
| Facility drill-down | No | No | Scoped | Scoped | Scoped | Yes | Configurable |
| Assessor management | No | No | No | No | No | Authorized only | Configurable |
| User administration | No | No | No | No | No | Authorized only | Configurable |
| Developer/GitBook docs | Yes | Yes | Yes | Yes | Yes | Yes | Yes |

## Implementation Rules

- UI menus must hide unauthorized pages.
- APIs must enforce role scope even if a user manually calls an endpoint.
- Large state-level lists should use search and pagination.
- Report downloads must apply the same scope as dashboards.
- Administrative updates should be auditable.
- Facility, assessment, CQI, performance and certification data are treated as restricted operational data and must be filtered by the user's geography/role scope.
- Assessor/assessee personal/contact fields should be encrypted or minimized according to the privacy policy.
- CQI responsible fields should store designation/post values such as CHO/RM/MOIC. If actual person name/mobile/email is added later, encrypt those structured fields.

## Data Handling by Role

| Data Group | Facility | Assessor | Block | District | Division | State |
| --- | --- | --- | --- | --- | --- | --- |
| Facility master and NIN | Own facility | Mapped facilities | Block scoped | District scoped | Division scoped | State scoped |
| Assessment/checklist scores | Own facility | Mapped facilities | Block scoped view | District scoped view | Division scoped view | State scoped view |
| CQI action plan/closure status | Own facility | Mapped facilities | Block scoped view | District scoped view | Division scoped view | State scoped view |
| Performance KPI/outcome data | Own facility entry/view | Optional by deployment | Block scoped view | District scoped view | Division scoped view | State scoped view |
| Certification status/history | View own facility | Mapped facilities view | Scoped view | Scoped view | Scoped view | State-authorized management |
| Assessor/assessee personal/contact fields | Minimum required access; encrypted at rest | Own assessor data plus assigned assessment data | Minimum required access; encrypted at rest | Minimum required access; encrypted at rest | Minimum required access; encrypted at rest | Authorized monitoring/admin access only; encrypted at rest |
| CQI responsible post/designation | Own facility | Mapped facilities | Block scoped view | District scoped view | Division scoped view | State scoped view |

## Related Documents

- [Use Cases](../architecture/use_cases.md)
- [Technical Architecture Overview](../architecture/technical_architecture.md)
- [Data Privacy and Protection Policy](../compliance/data_privacy_policy.md)
