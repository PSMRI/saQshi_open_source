# Assessor API Guide

Version: 1.1
Updated: 2026-08-26
License: GPL-3.0

## Purpose

The assessor APIs support state-led assessment assignment. State users can
create assessor profiles, map facilities, and assessors can select assigned
facilities to start or continue assessment.

This guide uses both profile vocabularies. In the Education profile, an assigned unit is normally shown as a **School** identified by **UDISE Code**, and the work area may be a **Class**. In the Healthcare profile, it is a **Facility** identified by **NIN**, and the work area may be a **Department/service area**. API fields such as `fac_id` remain stable compatibility fields in both profiles.

Management endpoints are protected by the assessor-management access gate. The current gate permits only configured management roles (role IDs `4`, `5`, `8`, `9`, and `11`); deployments must confirm the role mapping before assigning access. An assessor's own dashboard and mapped-unit endpoints must remain scoped to that assessor's active assignment.

## Endpoints

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `/api/assessor/v1/list.php` | GET | List/search assessor profiles. |
| `/api/assessor/v1/save.php` | POST/PATCH | Create or update assessor profile. |
| `/api/assessor/v1/facility_search.php` | GET | Search Schools/Facilities before mapping. |
| `/api/assessor/v1/mapping_list.php` | GET | List mapped Schools/Facilities for one assessor. |
| `/api/assessor/v1/mapping_save.php` | POST/PATCH | Assign/update assessor School/Facility mapping. |
| `/api/assessor/v1/dashboard.php` | GET | Load logged-in assessor dashboard. |
| `/api/assessor/v1/facility_summary.php` | GET | Load assessment, CQI and performance summary for a mapped School/Facility. |
| `/api/assessor/v1/my_facilities.php` | GET | Load logged-in assessor mapped Schools/Facilities. |
| `/api/assessor/v1/start_assessment.php` | POST | Select a mapped School/Facility and create/reuse active assessment. |

## Main Payloads

### Save Assessor

```json
{
  "assessor_id": 1,
  "assessor_code": "ASM001",
  "assessor_name": "Assessor Name",
  "user_id": 25,
  "designation": "Quality Assessor",
  "mobile_no": "9999999999",
  "mail_id": "assessor@example.org",
  "is_active": 1
}
```

If `user_id` is blank during new assessor creation, SaQshi creates a login user
automatically:

- username: `assessor_code`
- role: existing role with name containing `assessor`, or role ID `10`
- password: generated temporary password
- stored password: hash only
- first login: `password_must_change = 1`
- delivery: email/SMS notification service hooks

`assessor_name`, `mobile_no` and `mail_id` are encrypted at rest through
`api/core/Crypto.php`. The temporary password is not returned to the browser.

Do not write real personal data, temporary passwords, tokens or credentials to screenshots, logs, test fixtures or public documentation. Use the approved notification delivery process and the deployment's privacy policy for assessor account details.

### Save Mapping

```json
{
  "assessor_id": 1,
  "fac_id": 101,
  "assignment_status": "ACTIVE",
  "assigned_from": "2026-07-18",
  "assigned_to": "2026-08-18",
  "remarks": "State assessment assignment"
}
```

### Start Assessment

```json
{
  "fac_id": 101,
  "framework_code": "saqshi-nqas"
}
```

The API validates that the facility is mapped to the logged-in assessor. It
then sets the selected facility in session and creates or reuses the active
assessment.

The client-provided `fac_id` is a selection request, not an authorization grant. The server must reject an unmapped, ended or inactive assignment and must not allow an assessor to switch to another School/Facility by modifying browser JSON.

The response includes `next_action`, which tells the UI where to send the
assessor next:

```json
{
  "next_action": {
    "type": "route",
    "label": "Continue Checklist",
    "route": "assessment/checklist",
    "params": {
      "assessment_id": 4,
      "dept_id": 41
    },
    "state": "checklist_ready"
  }
}
```

### Facility Summary

```text
GET /api/assessor/v1/facility_summary.php?fac_id=101
```

The endpoint returns summary data only after validating that the facility is
mapped to the logged-in assessor.

```json
{
  "facility": {},
  "modules": {},
  "assessments": [],
  "cqi": {
    "open_gaps": 0,
    "closed_gaps": 0,
    "action_plans": 0
  },
  "performance": {
    "kpi_months": 0,
    "outcome_months": 0,
    "latest_period": null
  }
}
```

Assessor UI uses this summary for read-only facility visibility. KPI/outcome
charts can be opened for the mapped facility, but KPI/outcome write operations
are not part of the assessor role.

Assessment writes remain limited to the selected mapped unit and the allowed assigned Class/Department scope. A shared unit does not authorize two assessors to claim the same configured work area at the same time.

Protected save endpoints:

| Endpoint | Rule |
| --- | --- |
| `/api/performance/v1/kpi_save.php` | Rejects assessor role with HTTP 403. |
| `/api/performance/v1/outcome_save.php` | Rejects assessor role with HTTP 403. |
| Assessment checklist save | Allowed for mapped/selected assessor facility. |

## Linked Service

```text
api/service/AssessorService.php
```

This service manages:

- assessor profile list/save,
- facility search,
- assessor-facility mapping,
- logged-in assessor facility list,
- next workflow action for each mapped facility,
- mapped facility history/summary,
- active assessment creation/reuse,
- single-department auto activation.

## Linked UI

| UI Page | Purpose |
| --- | --- |
| `ui/pages/state/assessors.*` | State admin assessor profile and mapping. |
| `ui/pages/assessor/dashboard.*` | Assessor assigned facility dashboard. |
| `ui/pages/assessor/facilities.*` | Assessor assigned facility list. |

## Notification Services

| Service | Purpose |
| --- | --- |
| `api/service/EmailService.php` | Sends/logs email notifications such as assessor temporary password delivery. |
| `api/service/SmsService.php` | Sends/logs SMS notifications such as assessor temporary password delivery. |

Configuration:

```text
api/config/notifications/email.json
api/config/notifications/sms.json
```

Detailed gateway setup is documented in
[SMS and Email Notification Configuration](../deployment/notification_configuration.md).

## Security and Test Expectations

For any assessor API change, test in an approved non-production environment with management, assessor and facility-user accounts:

1. A management role can create/update an assessor and assignment only within its approved scope.
2. An assessor can list and start only active mapped Schools/Facilities.
3. An altered `fac_id`, `assessor_id`, mapping status or Class/Department selection cannot cross scope.
4. An assessor cannot save KPI/outcome values or alter another assessor's claimed work area.
5. Expired session, invalid method and invalid payload return safe errors without account, SQL or session detail.
6. Temporary-password, contact-field encryption and notification behavior are validated without exposing secret values.

Update the endpoint inventory, assessment/user guides, role-access matrix and testing evidence whenever these contracts or role mappings change.
