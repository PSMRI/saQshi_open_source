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
| System Admin | Configuration and administration scope as assigned by deployment policy. |

## Module Access Matrix

| Module / Page Area | Facility | Block | District | Division | State | System Admin |
| --- | --- | --- | --- | --- | --- | --- |
| Facility assessment entry | Yes | View only | View only | View only | View only | Configurable |
| Department activation | Yes | No | No | No | No | Configurable |
| Assessor information | Yes | View only | View only | View only | View only | Configurable |
| Checklist scoring | Yes | View only | View only | View only | View only | Configurable |
| Gap analysis | Yes | View only | View only | View only | View only | Configurable |
| Action plan | Yes | View only | View only | View only | View only | Configurable |
| Gap closure | Yes | View only | View only | View only | View only | Configurable |
| KPI/outcome entry | Yes | View only | View only | View only | View only | Configurable |
| Facility reports | Yes | Yes | Yes | Yes | Yes | Configurable |
| Certification status | View | View | View | View | Manage if authorized | Configurable |
| Certification update | No | No | No | No | Authorized only | Configurable |
| State monitoring dashboard | No | Scoped | Scoped | Scoped | Yes | Configurable |
| Facility drill-down | No | Scoped | Scoped | Scoped | Yes | Configurable |
| User administration | No | No | No | No | Authorized only | Configurable |
| Developer/GitBook docs | Yes | Yes | Yes | Yes | Yes | Yes |

## Implementation Rules

- UI menus must hide unauthorized pages.
- APIs must enforce role scope even if a user manually calls an endpoint.
- Large state-level lists should use search and pagination.
- Report downloads must apply the same scope as dashboards.
- Administrative updates should be auditable.

## Related Documents

- [Use Cases](../architecture/use_cases.md)
- [Technical Architecture Overview](../architecture/technical_architecture.md)
- [Data Privacy and Protection Policy](../compliance/data_privacy_policy.md)
