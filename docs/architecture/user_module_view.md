# User and Module View

Version: 1.0  
Updated: 2026-07-18  
License: GPL-3.0

## Purpose

This page explains which users access which SaQshi modules and how the major
workflows move from one module to another. It is a functional architecture
view, useful for programme teams, developers, testers and implementers.

## User to Module Access View

```mermaid
flowchart LR
    Login["Login and role check"]

    Facility["Facility User"]
    Assessor["External Assessor"]
    Monitoring["Block / District / Division Users"]
    State["State Admin / State User"]
    Maintainer["Maintainer / Developer"]

    FacilityModules["Facility modules<br/>Assessment, departments, assessor info,<br/>checklist, CQI, performance, certification,<br/>facility reports and facility profile"]
    AssessorModules["Assessor modules<br/>Assigned facilities, start/reuse assessment,<br/>department activation, checklist entry,<br/>assessment report and read-only summaries"]
    MonitoringModules["Monitoring modules<br/>Dashboard, certification status, map,<br/>assessment progress, CQI/performance monitoring,<br/>facility drill-down and scoped reports"]
    StateModules["State admin modules<br/>State monitoring, user administration,<br/>assessor management, certification update,<br/>indicator analytics and state reports"]
    DeveloperModules["Developer modules<br/>GitBook, API docs, Swagger, Postman,<br/>configuration, deployment and release docs"]

    Login --> Facility --> FacilityModules
    Login --> Assessor --> AssessorModules
    Login --> Monitoring --> MonitoringModules
    Login --> State --> StateModules
    Login --> Maintainer --> DeveloperModules
```

The exact page-by-page permissions are listed in the matrix below. This diagram
is intentionally high level so implementers can quickly understand which menu
group belongs to each user type.

## Role and Module Matrix

| Module | Facility User | Assessor | Block | District | Division | State |
| --- | --- | --- | --- | --- | --- | --- |
| Dashboard | Facility dashboard | Assessor dashboard | Scoped monitoring | Scoped monitoring | Scoped monitoring | State monitoring |
| Create assessment | Yes | Via mapped facility start | View only | View only | View only | View only |
| Department activation | Yes | Mapped facility only | No | No | No | No |
| Assessor info | Yes | Mapped facility only | View only | View only | View only | View only |
| Checklist | Yes | Mapped facility only | View only | View only | View only | View only |
| Gap analysis | Yes | View only for mapped facility | View only | View only | View only | View only |
| Action plan | Yes | View only for mapped facility | View only | View only | View only | View only |
| Gap closure | Yes | View only for mapped facility | View only | View only | View only | View only |
| KPI/outcome entry | Yes | View only for mapped facility | View only | View only | View only | View only |
| Certification | Facility view | Mapped facility view | Scoped view | Scoped view | Scoped view | Authorized update |
| State reports | No | No | Scoped downloads | Scoped downloads | Scoped downloads | State downloads |
| User administration | No | No | No | No | No | Authorized state users |
| Assessor management | No | No | No | No | No | Authorized state users |

## Main End-to-End Flow

```mermaid
flowchart LR
    Login["Login"]
    Role{"Role"}
    FacilityFlow["Facility Assessment Flow"]
    AssessorFlow["External Assessor Flow"]
    MonitoringFlow["Monitoring Flow"]
    AdminFlow["Administration Flow"]

    Login --> Role
    Role -- Facility --> FacilityFlow
    Role -- Assessor --> AssessorFlow
    Role -- Block / District / Division / State --> MonitoringFlow
    Role -- Authorized State Admin --> AdminFlow
```

## Facility Assessment Module Flow

```mermaid
flowchart LR
    Create["Create / reuse active assessment"]
    Activate["Activate departments"]
    Info["Fill assessor info"]
    Checklist["Score checkpoints 0 / 1 / 2"]
    Progress["Progress and score"]
    Gap["Gap analysis"]
    Plan["Action plan"]
    Closure["Gap closure"]
    Reports["Reports"]

    Create --> Activate --> Info --> Checklist --> Progress --> Gap --> Plan --> Closure --> Reports
```

## State Admin Assessor Setup Flow

```mermaid
flowchart LR
    Admin["State admin"]
    AssessorProfile["Create assessor profile"]
    Map["Map facilities"]
    Notify["Send temporary login by SMS/email"]

    Admin --> AssessorProfile --> Map --> Notify
```

## External Assessor Module Flow

```mermaid
flowchart LR
    AssessorLogin["External assessor login"]
    Facility["Select assigned facility"]
    Summary["View facility summary"]
    Start["Create / reuse active assessment"]
    Dept{"Single or multiple departments?"}
    Auto["Auto activate single department"]
    Manual["Open department activation"]
    Info["Confirm assessor info"]
    Checklist["Complete checklist"]
    Report["View assessment report"]

    AssessorLogin --> Facility --> Summary --> Start --> Dept
    Dept -- Single --> Auto --> Info
    Dept -- Multiple --> Manual --> Info
    Info --> Checklist --> Report
```

## Monitoring Module Flow

```mermaid
flowchart LR
    Login["Monitoring user login"]
    Scope["Apply role scope"]
    Cards["Dashboard cards"]
    Search["Search facility / NIN"]
    Drill["Drill-down"]
    Detail["Facility detail"]
    Download["Download reports"]

    Login --> Scope --> Cards
    Cards --> Search
    Cards --> Drill --> Detail
    Cards --> Download
```

## Performance Module Flow

```mermaid
flowchart LR
    Month["Select month"]
    Department["Select activated department"]
    Indicator["Load KPI / outcome"]
    Entry["Enter numerator, denominator, result, remarks"]
    Save["Save / update"]
    Trend["Trend view"]
    Export["Download report"]

    Month --> Department --> Indicator --> Entry --> Save --> Trend --> Export
```

## Certification Module Flow

```mermaid
flowchart LR
    List["Facility certification list"]
    History["Certification history"]
    Update["Authorized update"]
    Validate["Validate status, dates, score"]
    Save["Save history"]
    Map["Map / dashboard update"]

    List --> History
    History --> Update --> Validate --> Save --> Map
```

## Developer View

Every UI module generally follows this structure:

```text
ui/pages/<module>/<page>.html
ui/pages/<module>/<page>.js
ui/pages/<module>/<page>.css
ui/pages/<module>/<page>.json
```

Every API module generally follows this structure:

```text
api/<module>/v1/<endpoint>.php
api/service/<ModuleService>.php
api/config/<module>/*.json
```

The page JSON manifest connects the route to the page assets. Page JavaScript
calls versioned API endpoints through `SQ.api`. APIs validate session/CSRF and
delegate business logic to services.
