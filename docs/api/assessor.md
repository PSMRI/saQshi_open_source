# Assessor API Guide

Version: 1.0  
Updated: 2026-07-18  
License: GPL-3.0

## Purpose

The assessor APIs support state-led assessment assignment. State users can
create assessor profiles, map facilities, and assessors can select assigned
facilities to start or continue assessment.

## Endpoints

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `/api/assessor/v1/list.php` | GET | List/search assessor profiles. |
| `/api/assessor/v1/save.php` | POST/PATCH | Create or update assessor profile. |
| `/api/assessor/v1/facility_search.php` | GET | Search facilities before mapping. |
| `/api/assessor/v1/mapping_list.php` | GET | List mapped facilities for one assessor. |
| `/api/assessor/v1/mapping_save.php` | POST/PATCH | Assign/update assessor facility mapping. |
| `/api/assessor/v1/dashboard.php` | GET | Load logged-in assessor dashboard. |
| `/api/assessor/v1/facility_summary.php` | GET | Load assessment, CQI and performance summary for a mapped facility. |
| `/api/assessor/v1/my_facilities.php` | GET | Load logged-in assessor mapped facilities. |
| `/api/assessor/v1/start_assessment.php` | POST | Select facility and create/reuse active assessment. |

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
