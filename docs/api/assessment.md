# Assessment API

## Why this module exists

The assessment module manages the facility assessment lifecycle: create one active assessment, activate and assess departments, save checkpoint evidence and scores, then complete or cancel the assessment. It also supplies progress, navigation, action-plan and analysis data for the assessment user interface.

All endpoints use facility and user identity from the session. A caller must not operate on another facility merely by submitting a different `fac_id`.

## Lifecycle

```text
Create ACTIVE assessment
  -> activate department
  -> save assessor information
  -> start department
  -> save/update checkpoint responses
  -> complete each activated department
  -> complete assessment
```

One facility can have only one `ACTIVE` assessment. An assessment may alternatively be cancelled.

## Shared implementation pattern

Endpoint files include `api/auth_api.php` and `api/assets/conn/db.php`. `Security::requireMethod()` enforces the HTTP method. `Security::jsonInput()` reads the request body. `SessionManager::facilityId()` and `SessionManager::userId()` establish authorization and the actor. `Response` methods produce the JSON response.

## Create assessment

**Source:** `api/assessment/v1/create_assessment.php`  
**Method:** `POST`  
**Why:** Starts a new assessment for the logged-in user's facility when no active assessment exists.

### Request body

```json
{
  "assessment_name": "Internal Assessment",
  "framework_code": "saqshi-nqas",
  "start_date": "2026-06-25",
  "end_date": "2026-07-25"
}
```

The values have defaults, but name and framework must be non-empty. Dates must be valid and `end_date` cannot be earlier than `start_date`.

### How it works

1. Reads JSON and gets facility and user from the session.
2. Validates name, framework and date ordering.
3. Searches `assessment_master` for an `ACTIVE` assessment for the facility.
4. If found, returns it with `created: false`; it never creates a duplicate.
5. Otherwise inserts an `ACTIVE` row with the session user as `created_by`.
6. Dispatches `assessment.created` using `Event::dispatch` and returns `created: true`.

| Table | Effect |
|---|---|
| `assessment_master` | Inserts one active assessment. |

**Extension note:** Validate new attributes before the active-row check, add them to the prepared `INSERT`, and return them from both response paths. Consider a database constraint or transaction strategy if simultaneous creates are possible.

## Get active assessment

**Source:** `api/assessment/v1/active_assessment.php`  
**Method:** `GET`  
**Why:** Lets the client decide whether the current facility has an in-progress assessment and retrieve its metadata.

### How it works

1. Obtains facility and user from the session; missing context produces an error.
2. Selects the newest `ACTIVE` `assessment_master` row for that facility.
3. Returns `has_active: false` and `assessment: null` when no row exists.
4. Otherwise maps database fields into an assessment object and returns `has_active: true`.

| Element | Function |
|---|---|
| `Security::requireMethod('GET')` | Guards the endpoint contract. |
| `SessionManager::facilityId()` | Establishes the facility access boundary. |
| `SessionManager::userId()` | Ensures an authenticated actor is present. |
| `$con->prepare()` / `bind_param()` | Safely queries facility-scoped data. |
| `Response::success()` | Sends the standard success response. |

## Save checkpoint response

**Source:** `api/assessment/v1/save-response.php`  
**Method:** `POST`  
**Why:** Creates or updates one department checkpoint response and advances the current checkpoint marker.

### Request body

```json
{
  "assessment_id": 1,
  "dept_id": 25,
  "checkpoint_id": 21070,
  "response_value": "2",
  "response_json": { "value": "2" },
  "remarks": "Optional assessor notes",
  "evidence_url": "https://example.org/evidence.pdf"
}
```

`assessment_id`, `dept_id`, `checkpoint_id`, and a valid response are required. The browser should not decide the score. `save-response.php` loads the checkpoint response definition from framework JSON and calculates `score`, `max_score` and `score_status` on the server.

Supported response styles:

| Type | Payload |
|---|---|
| `radio`, `yes_no`, `dropdown` | `response_value` plus optional `response_json.value`. |
| `number`, `text` | `response_value` and `response_json.value`. |
| `form` | `response_json.fields` containing key/value pairs. |

### How it works

1. Validates required fields plus facility/user session context.
2. Confirms that the assessment is `ACTIVE` and owned by the current facility.
3. Confirms the department is active, not completed and `IN_PROGRESS`.
4. Requires saved assessor information for the assessment, facility and department.
5. Loads the checkpoint response type from framework JSON.
6. Validates the submitted value and calculates server-owned scoring metadata.
7. Upserts `assessment_response` on `(assessment_id, dept_id, checkpoint_id)`.
8. Indexes structured/non-scored fields in `assessment_response_field_index`.
9. Updates `assessment_department.current_checkpoint_id`.
10. Counts saved responses, dispatches `checklist.response.saved`, and returns progress.

| Table | Effect |
|---|---|
| `assessment_response` | Inserts or updates response, response type, structured JSON, score, max score, score status, remarks, evidence and actor. |
| `assessment_response_field_index` | Stores searchable field values for text/number/form analytics. |
| `assessment_department` | Sets `current_checkpoint_id`. |

**Extension note:** Preserve every ownership/state check before a write. If evidence becomes a managed upload, store a file identifier from the files API instead of accepting an unverified URL.

## Complete assessment

**Source:** `api/assessment/v1/complete_assessment.php`  
**Method:** `POST`  
**Why:** Closes an assessment only when every activated department is complete.

### Request body

```json
{ "assessment_id": 1 }
```

### How it works

1. Validates the ID and loads an active assessment owned by the current facility.
2. Counts active, complete and pending departments.
3. Rejects completion if no department is activated.
4. Rejects completion if any active department is not `COMPLETED`; the error data contains the pending department list.
5. Updates `assessment_master.status` to `COMPLETED` and sets `completed_on`.
6. Dispatches `assessment.completed` with a completion summary.

| Table | Effect |
|---|---|
| `assessment_master` | Sets `status` to `COMPLETED` and timestamps completion. |

## Remaining assessment endpoints

The module also contains the following endpoint families. They are listed here so future additions retain the same documentation structure: method, request/response contract, functions, database effects, dependencies and extension guidance.

| Area | Endpoint files |
|---|---|
| Department setup | `department/list`, `department/save`, `department-status/list`, `department-status/save`, `start_department`, `resume_department`, `complete-department` |
| Assessment lifecycle | `list`, `start`, `start_cycle`, `get-cycle`, `complete-cycle`, `cancel_assessment` |
| Checkpoint navigation | `get_checkpoint`, `next_checkpoint`, `previous_checkpoint`, `progress`, `resume`, `score` |
| Assessor data | `assessor_info_get`, `assessor_info_save` |
| Analysis and action plans | `gap_analysis`, `dashboard_insights`, `action_plan`, `action_plan_save`, `action_plan_update`, `action_plan_closure` |
