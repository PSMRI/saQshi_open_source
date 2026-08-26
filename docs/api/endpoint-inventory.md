# API Endpoint Inventory

Version: 1.1
Updated: 2026-08-26

This is the source-of-truth checklist for endpoint documentation. It contains
all endpoint implementation files under `api/*/v1`. Files prefixed with `_` are
internal helpers, not callable endpoints.

## Assessment (33)

`action_plan`, `action_plan_closure`, `action_plan_save`, `action_plan_update`,
`active_assessment`, `assessor_info_get`, `assessor_info_save`,
`cancel_assessment`, `complete_assessment`, `complete-cycle`,
`complete-department`, `create_assessment`, `dashboard_insights`,
`department/list`, `department/save`, `department-status/list`,
`department-status/save`, `gap_analysis`, `get_checkpoint`, `get-cycle`,
`fhir_measure_reports`, `list`, `next_checkpoint`, `previous_checkpoint`, `progress`, `resume`,
`resume_department`, `save-response`, `save-responses-bulk`, `score`, `start`, `start_cycle`,
`start_department`.

Source directory: `api/assessment/v1/`

## Authentication (7)

`captcha`, `csrf`, `login`, `login_key`, `logout`, `me`, `validate`.

Source directory: `api/auth/v1/`

## Deployment Configuration (4)

`assessment_policy`, `deployment`, `profile_apply`, `public_deployment`.

Source directory: `api/config/v1/`

`public_deployment` is the deliberately unauthenticated, presentation-only endpoint used by the public landing page. It exposes the active profile code/name plus public labels, branding and content. It must not expose credentials, user/session data, infrastructure settings, storage paths or operational configuration.

`deployment`, `assessment_policy` and `profile_apply` are configuration-management endpoints and require the appropriate authenticated role. Do not treat their presence in this inventory as permission to call them anonymously.

## Assessor (8)

`dashboard`, `facility_search`, `list`, `mapping_list`, `mapping_save`,
`my_facilities`, `save`, `start_assessment`.

Source directory: `api/assessor/v1/`

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

## State (22 endpoints, 2 helpers)

Endpoints: `assessment_history`, `assessment_progress`, `boundary`,
`certification_summary`, `certification_update`, `cqi_summary`, `dashboard`,
`facility_category`, `facility_detail`, `facility_progress`,
`facility_reference`, `indicator_analytics`, `map`, `performance_summary`,
`reports`, `credential_delivery_log`, `user_create`, `user_password_reset`,
`user_save`, `user_scope_options`, `user_status`, `users`.

Internal helpers: `_bootstrap`, `_management_bootstrap`.

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
- The list is generated from the currently present `api/*/v1` source files. Update it whenever endpoint files are added, removed or renamed; do not leave stale entries such as deleted compatibility endpoints.
- Most endpoints are protected. Authentication-related endpoints may be called before login only as required by the login flow; public availability must be confirmed from the endpoint implementation and security review, not inferred from its module name.
- For public-route verification, `GET /api/config/v1/public_deployment.php` should return public branding data and `GET /api/auth/v1/captcha.php` should return a captcha challenge. Unsupported captcha methods should be rejected.
