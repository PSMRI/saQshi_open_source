# SaQshi SQL Query Inventory

Version: 1.0  
Updated: 2026-07-19

## Purpose

This page describes the SQL query types used by SaQshi and their source
modules. It is an implementation inventory, not a database dump or production
data record. The PHP source remains the authoritative current query text.

## Query Execution Standard

SaQshi uses MySQL/MariaDB through PHP `mysqli`.

- Application reads and writes use `prepare()`, `bind_param()` and `execute()`
  wherever values vary by user, facility or assessment.
- Static schema/compatibility checks and controlled migrations may use
  `query()` for trusted SQL.
- Dynamic table and column identifiers are application-controlled and
  validated before use.
- Request values must never be interpolated into SQL; use placeholders,
  including for `IN` lists.

See the [SQL Injection and Security Review](../security/sql_injection_security_review.md)
for the security controls and hardening history.

## Query Inventory by Module

| Module | Main query operations | Principal tables / objects | Main source locations |
| --- | --- | --- | --- |
| Authentication and users | Select user by username/id; create/update users; password-change check; activate/deactivate account | `s_user`, `u_role` | `api/core/Auth.php`, `api/service/AuthService.php`, `api/service/StateDashboardService.php` |
| Facilities and assessors | Read/create/update assessor profiles and map assessors to facilities | `assessor_master`, `assessor_facility_mapping`, facility master tables | `api/service/AssessorService.php`, `api/assessment/v1/assessor_info_*.php` |
| Assessment lifecycle | Create/list/read active assessment; start/resume/complete/cancel assessment and department | `assessment_master`, `assessment_department_status` | `api/assessment/v1/create_assessment.php`, `list.php`, `active_assessment.php`, `start_department.php`, `resume_department.php`, `complete_assessment.php`, `complete-department.php`, `cancel_assessment.php` |
| Checklist and responses | Load checkpoint scope; retrieve saved response; insert/update answer; calculate response counts | `assessment_response`, checklist/framework tables | `api/assessment/v1/get_checkpoint.php`, `next_checkpoint.php`, `previous_checkpoint.php`, `save-response.php`, `api/framework/v1/checkpoints.php` |
| Assessment reporting | Aggregate score/progress; read checkpoints, action plans and assessor data for reports | assessment, department, response and action-plan tables | `api/assessment/v1/score.php`, `progress.php`, `api/reports/v1/checkpoint_progress_report.php` |
| CQI | Read gaps; update action plans; save closure data; load suggestions | response and CQI action-plan tables | `api/assessment/v1/gap_analysis.php`, `action_plan.php`, `action_plan_update.php`, `api/service/DepartmentStatusService.php` |
| Performance | Read/write KPI, outcome, indicator and trend data; create compatible support tables when needed | performance KPI/outcome tables | `api/service/PerformanceService.php`, `api/service/KPIService.php` |
| Certification | Read/write certification records/history; dashboard aggregation; schema compatibility | `cert_details`, certification history/configuration tables | `api/service/CertificationService.php`, `api/service/StateDashboardService.php` |
| State analytics and reports | Filtered lists and aggregate counts, indicators and export datasets | assessment, facility, certification and performance tables | `api/service/StateDashboardService.php`, `StateReportService.php`, `StateIndicatorAnalyticsService.php` |
| AI chat | Create chat storage; insert/read/delete messages; check columns | `ai_chat_messages` | `api/service/ChatAssistantService.php` |

## API Page-by-page Query Inventory

This section is the API-page view of the inventory. Each row identifies the
queries performed by that endpoint; query details can change with authorised
framework configuration, so the linked PHP page remains the exact source.

### Assessment API (`api/assessment/v1/`)

| API page | Query operations | Main records involved |
| --- | --- | --- |
| `active_assessment.php` | Select current active assessment for the authenticated facility/user scope | `assessment_master` |
| `create_assessment.php` | Select framework/facility validity and existing assessment; insert assessment master row | framework/facility tables, `assessment_master` |
| `list.php` | Select assessments with status filtering; select active department details for each result | `assessment_master`, `assessment_department_status` |
| `start_department.php` | Select assessment/department/assessor status; insert or reactivate department status; update assessment start state; count completion records | assessment, department and assessor tables |
| `resume_department.php` | Select assessment/department/assessor; update or insert resumed department state; count checkpoints | assessment and department status tables |
| `complete_department.php` | Select assessment, department, assessor and response count; update department completion state | assessment, department status, response tables |
| `complete_assessment.php` | Select assessment and completion/pending counts; update assessment completion state | `assessment_master`, department status tables |
| `cancel_assessment.php` | Select owned assessment; update cancellation status | `assessment_master` |
| `department/list.php` | Select assessment scope and active departments | `assessment_master`, `assessment_department_status` |
| `department/save.php` | Select assessment and duplicate department; insert or update department status | assessment and department status tables |
| `assessor_info_get.php` | Select assessor information, assessment and department details | `assessment_assessor_info`, assessment and department tables |
| `assessor_info_save.php` | Select existing information/assessment/department; insert or update assessor information | `assessment_assessor_info`, assessment and department tables |
| `get_checkpoint.php` | Select assessment/department/assessor scope; select checkpoint and saved response | framework/checklist tables, `assessment_response` |
| `next_checkpoint.php` | Select assessment and department scope before loading next checkpoint | assessment and department status tables |
| `previous_checkpoint.php` | Select assessment and department scope before loading previous checkpoint | assessment and department status tables |
| `save-response.php` | Select assessment, department and existing response; insert/update response; update department state; count responses | `assessment_response`, assessment and department tables |
| `score.php` | Select assessment, department and response data; aggregate score summary | assessment, department and response tables |
| `progress.php` | Select assessment; aggregate department/gap progress; select department list | assessment, department, response and CQI tables |
| `dashboard_insights.php` | Aggregate assessment, completion and score insight datasets | assessment, department and response tables |
| `gap_analysis.php` | Select assessment, departments and non-compliant/partial responses for gap analysis | assessment, department and response tables |
| `action_plan.php` | Select response/gap data and suggested action plans; uses bound placeholders for checkpoint `IN` list | response and action-plan tables |
| `action_plan_save.php` | Insert/update action-plan record after scope validation | CQI action-plan tables |
| `action_plan_update.php` | Select action-plan scope and update plan fields/status | CQI action-plan tables |
| `action_plan_closure.php` | Select action-plan scope and update closure fields/status | CQI action-plan tables |

### Other Endpoint Pages

| API page | Query operations | Main records involved |
| --- | --- | --- |
| `api/framework/v1/checkpoints.php` | Select framework, area, subtype, method and checkpoint configuration; select saved checkpoint state where needed | framework/checklist configuration and response tables |
| `api/reports/v1/checkpoint_progress_report.php` | Select assessment, active departments, responses, legacy compatibility data, action plans and assessor details for report export | assessment, department, response, action-plan and assessor tables |
| `api/reports/v1/checkpoint_scorecard.php` | Select and aggregate checkpoint/scorecard data for report output | assessment and response tables |
| `api/admin/v1/users.php` | Select, insert and update user profile/account data | `s_user`, `u_role` |
| `api/admin/v1/facilities.php` | Select, insert and update facility data and related master values | facility master tables |

### Shared Service Query Pages

These services are called by API endpoints; their queries are therefore part of
the endpoint behaviour even though they are not directly browsed as pages.

| Service page | Query responsibility |
| --- | --- |
| `api/core/Auth.php` | Login, session user, password and role lookups/updates. |
| `api/service/AssessorService.php` | Assessor/facility mapping and assessor-login support. |
| `api/service/DepartmentStatusService.php` | Department-status lookup and update helpers. |
| `api/service/DynamicAssessmentService.php` | Dynamic assessment configuration, response and checkpoint helpers. |
| `api/service/PerformanceService.php`, `KPIService.php` | KPI, outcome, indicator, trend and performance aggregation queries. |
| `api/service/CertificationService.php` | Certification details, history and controlled schema-compatibility queries. |
| `api/service/StateDashboardService.php`, `StateReportService.php`, `StateIndicatorAnalyticsService.php` | State dashboard, report and analytics aggregations. |
| `api/service/ChatAssistantService.php` | Chat message create/read/delete and storage compatibility checks. |
| `api/service/ResponseTypeService.php` | Configurable response-type reads/writes and controlled support-table checks. |

## Representative Prepared-query Patterns

### Single-record lookup

```sql
SELECT password_must_change
FROM s_user
WHERE u_id = ?
LIMIT 1;
```

Source: `api/core/Auth.php`.

### State-changing update

```sql
UPDATE s_user
SET is_active = ?
WHERE u_id = ?;
```

Source: `api/service/StateDashboardService.php`.

### Scoped delete

```sql
DELETE FROM ai_chat_messages
WHERE user_id = ? AND fac_id = ?;
```

Source: `api/service/ChatAssistantService.php`.

### Dynamic `IN` list (safe form)

```sql
SELECT ...
FROM ...
WHERE checkpoint_id IN (?, ?, ...)
  AND framework_code = ?;
```

The number of placeholders is generated from validated checkpoint IDs and all
values are bound. Source: `api/assessment/v1/action_plan.php`.

## Schema and Metadata Queries

Some services issue trusted metadata or schema-maintenance queries for
compatibility:

- `SHOW TABLES LIKE ...` and `SHOW COLUMNS ...`.
- `INFORMATION_SCHEMA.TABLES` and `INFORMATION_SCHEMA.COLUMNS` checks.
- `CREATE TABLE` / `ALTER TABLE` for supported migrations and idempotent setup.

Deployment migrations are stored under `api/sql/`; see the
[Database Setup and Migration Guide](database_setup_and_migration.md).

## How to Locate Every Current Query

Run from the `open_source` directory:

```text
rg -n --glob '*.php' '(->query\\(|mysqli_query\\(|mysqli_prepare\\(|->prepare\\()' api
```

Review each new `query()` call to ensure it is static/trusted SQL. Bind all
new user-supplied values through a prepared statement.

## Related Documentation

- [Data Dictionary and ER Diagram](data_dictionary_erd.md)
- [Database Setup and Migration Guide](database_setup_and_migration.md)
- [SQL Injection and Security Review](../security/sql_injection_security_review.md)
- [API Source Reference](../api/source-reference.md)
