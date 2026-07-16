# Data Dictionary and ER Diagram

This document provides a first-pass data dictionary and ERD for SaQshi. Table and column names should be verified against the active migration scripts before production release.

## Core Entity Relationship Diagram

```mermaid
erDiagram
    FACILITIES ||--o{ S_USER : maps_to
    U_ROLE ||--o{ S_USER : assigns
    FACILITIES ||--o{ ASSESSMENT_MASTER : has
    ASSESSMENT_MASTER ||--o{ ASSESSMENT_DEPARTMENT : activates
    ASSESSMENT_MASTER ||--o{ ASSESSMENT_ASSESSOR_INFO : records
    ASSESSMENT_MASTER ||--o{ ASSESSMENT_CYCLE_RESPONSE : stores
    ASSESSMENT_DEPARTMENT ||--o{ ASSESSMENT_CYCLE_RESPONSE : contains
    ASSESSMENT_CYCLE_RESPONSE ||--o{ ASSESSMENT_ACTION_PLAN : creates_gap
    ASSESSMENT_ACTION_PLAN ||--o{ GAP_CLOSURE : closes
    FACILITIES ||--o{ PERFORMANCE_MONTHLY_HEADER : submits
    PERFORMANCE_MONTHLY_HEADER ||--o{ PERFORMANCE_MONTHLY_DETAIL : has
    FACILITIES ||--o{ CERTIFICATION_HISTORY : tracks

    FACILITIES {
        int fac_id PK
        string fac_name
        bigint NIN_no
        string state_name
        string Dist_Name
        string Block_Name
        int Health_facilty_type
    }

    S_USER {
        int user_id PK
        int role_id FK
        int fac_id_fk FK
        string mail_id
        string mob_no
        int status
    }

    U_ROLE {
        int role_id PK
        string role_name
        int role_status
    }

    ASSESSMENT_MASTER {
        bigint assessment_id PK
        int fac_id_fk FK
        string assessment_name
        string framework_code
        string status
    }

    ASSESSMENT_DEPARTMENT {
        bigint id PK
        bigint assessment_id FK
        int dept_id
        int is_active
        string status
    }

    ASSESSMENT_CYCLE_RESPONSE {
        bigint response_id PK
        bigint cycle_id FK
        int dept_id
        int checkpoint_id
        decimal score
    }

    ASSESSMENT_ACTION_PLAN {
        bigint id PK
        bigint assessment_id FK
        int checkpoint_id
        string status
        decimal revised_score
    }

    PERFORMANCE_MONTHLY_HEADER {
        bigint entry_id PK
        int fac_id_fk FK
        int entry_month
        int entry_year
    }

    PERFORMANCE_MONTHLY_DETAIL {
        bigint detail_id PK
        bigint entry_id FK
        string indicator_id
        decimal result_value
    }

    CERTIFICATION_HISTORY {
        bigint history_id PK
        int fac_id_fk FK
        bigint fac_nin
        string action_type
    }
```

## Main Tables

| Table | Purpose |
| --- | --- |
| `facilities` | Facility master data, geography, NIN, type and coordinates. |
| `s_user` | Application users, role mapping and facility mapping. |
| `u_role` | Role definitions such as facility, block, district, division and state. |
| `assessment_master` | Main assessment record for a facility. |
| `assessment_department` | Activated departments and department-level assessment status. |
| `assessment_assessor_info` | Assessor and assessee details by assessment and department. |
| `assessment_cycle_response` | Checklist checkpoint responses and baseline scores. |
| `assessment_action_plan` | CQI action plans, target dates, responsible person and revised score. |
| `performance_monthly_header` | Monthly KPI/outcome submission header. |
| `performance_monthly_detail` | Indicator-level monthly performance values. |
| `certification_history` | Certification change history and audit data. |

## Key Relationships

| Relationship | Meaning |
| --- | --- |
| Facility to user | A facility user is mapped to one facility through `fac_id_fk`. |
| Facility to assessment | A facility may have many assessments over time. |
| Assessment to department | An assessment activates one or more departments. |
| Assessment to response | Checkpoint responses are stored against the assessment cycle. |
| Response to action plan | Score 0/1 responses can become CQI gaps with action plans. |
| Action plan to closure | Closure records revised score and completion evidence/status. |
| Facility to performance | Facilities submit monthly KPI/outcome data. |
| Facility to certification | Certification history is linked by facility id and/or NIN. |

## Scoring Notes

| Score | Meaning |
| --- | --- |
| 0 | Non-compliance |
| 1 | Partial compliance |
| 2 | Full compliance |

Baseline score comes from `assessment_cycle_response.score`. Improved score uses `assessment_action_plan.revised_score` when available.
