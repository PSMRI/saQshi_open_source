# API Endpoint Inventory

This is the source-of-truth checklist for endpoint documentation. It contains
all endpoint implementation files under `api/*/v1`. Files prefixed with `_` are
internal helpers, not callable endpoints.

## Assessment (31)

`action_plan`, `action_plan_closure`, `action_plan_save`, `action_plan_update`,
`active_assessment`, `assessor_info_get`, `assessor_info_save`,
`cancel_assessment`, `complete_assessment`, `complete-cycle`,
`complete-department`, `create_assessment`, `dashboard_insights`,
`department/list`, `department/save`, `department-status/list`,
`department-status/save`, `gap_analysis`, `get_checkpoint`, `get-cycle`,
`list`, `next_checkpoint`, `previous_checkpoint`, `progress`, `resume`,
`resume_department`, `save-response`, `score`, `start`, `start_cycle`,
`start_department`.

Source directory: `api/assessment/v1/`

## Authentication (9)

`captcha`, `csrf`, `login`, `login_key`, `login1`, `logout`, `logout1`, `me`,
`validate`.

Source directory: `api/auth/v1/`

## Certification (8 endpoints, 1 helper)

Endpoints: `current`, `dashboard`, `history`, `list`, `renewal_status`, `save`,
`update`, `validate`.

Internal helper: `_common`.

Source directory: `api/certification/v1/`

## Chat (3 endpoints, 1 helper)

Endpoints: `clear`, `history`, `send`.

Internal helper: `_common`.

Source directory: `api/chat/v1/`

## Files (2)

`delete`, `upload`.

Source directory: `api/files/v1/`

## Framework (9)

`assessment_methods`, `checkpoints`, `concerns`, `departments`, `facility-types`,
`load`, `my_departments`, `my_facility`, `subtypes`.

Source directory: `api/framework/v1/`

## Performance (11)

`dashboard`, `indicator_history`, `indicator_list`, `indicator_save`,
`kpi_history`, `kpi_list`, `kpi_save`, `outcome_history`, `outcome_list`,
`outcome_save`, `trend`.

Source directory: `api/performance/v1/`

## Reports (2)

`checkpoint_progress_report`, `checkpoint_scorecard`.

Source directory: `api/reports/v1/`

## State (17 endpoints, 1 helper)

Endpoints: `assessment_history`, `assessment_progress`, `boundary`,
`certification_summary`, `certification_update`, `cqi_summary`, `dashboard`,
`facility_category`, `facility_detail`, `facility_progress`,
`indicator_analytics`, `map`, `performance_summary`, `reports`, `user_save`,
`user_status`, `users`.

Internal helper: `_bootstrap`.

Source directory: `api/state/v1/`

## Admin (2)

`facilities`, `users`.

Source directory: `api/admin/v1/`

## Notes

- Endpoint names shown here are file stems. For example,
  `api/assessment/v1/active_assessment.php` maps to
  `/api/assessment/v1/active_assessment.php` when PHP files are exposed directly.
- `api/routes.php` also supports a dispatcher form using `?route=<module/v1/name>`.
- The inventory will be expanded into endpoint pages with methods, request fields,
  responses, functions, services, database effects and extension guidance.
