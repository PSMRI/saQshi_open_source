# SaQshi API Source Reference

> Generated from the current files by `tools/generate-api-documentation.php`.
> Regenerate this file whenever API source, services, core classes, or configuration change.

## Scope

This reference covers every PHP file and JSON configuration file under `api/`. For each PHP file it records its role, HTTP-method guards, includes, declared classes/functions, request fields detected in code, database tables referenced, and emitted events. Source comments and endpoint-specific guides add the business explanation where present.

## API routing and common execution path

- `api/routes.php` reads `route` from the query string, protects non-public routes with `AuthMiddleware::check()`, and loads `api/<route>.php`.
- Endpoint files commonly load `auth_api.php`, database connection code, and then use `Security`, `SessionManager`, and `Response` helpers.
- Public endpoints are files under `api/<module>/v1/` that do not begin with `_`.

## HTTP endpoint

### `api/admin/v1/facilities.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** facilities.php  Logged-in user's assigned facility profile.  GET  /api/admin/v1/facilities.php POST /api/admin/v1/facilities.php

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

- `function adminFacilityRequest`
- `function adminFacilityColumns`
- `function adminFacilityColumn`
- `function adminFacilityJsonMaster`
- `function adminFacilityTypeName`
- `function adminFacilityFind`
- `function adminFacilityDbExists`
- `function adminFacilityNinExistsForOtherFacility`
- `function adminFacilityValidateCoordinate`

**Request fields read from `$request`**

- `fac_name`
- `facility_name`
- `nin_no`
- `Health_facilty_type`
- `facility_type`
- `state_id`
- `division_id`
- `dist_id`
- `district_id`
- `block_id`
- `latitude`
- `longitude`
- `is_active`

**Database tables referenced**

- `facilities`
- `columns`
- `prepare`
- `failed`

**Events dispatched**

None detected.

### `api/admin/v1/users.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** users.php  Logged-in facility user profile/password update.  GET  /api/admin/v1/users.php POST /api/admin/v1/users.php

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../core/Auth.php`
- `/../../core/Crypto.php`

**Declared classes and functions**

- `function adminUsersRequest`
- `function adminUsersPasswordErrors`
- `function adminUsersRow`
- `function adminUsersEncryptedProfilePayload`
- `function adminUsersFind`

**Request fields read from `$request`**

- `f_name`
- `m_name`
- `l_name`
- `mail_id`
- `mob_no`
- `password`
- `confirm_password`

**Database tables referenced**

- `s_user`
- `u_role`
- `prepare`
- `failed`

**Events dispatched**

None detected.

### `api/assessment/v1/action_plan.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** action_plan.php  Generate action plan from gap analysis.  Gap condition: - score = 0 - score = 1  It returns: - system suggested action_plan from framework JSON - existing saved user action plan, if available - achievability - responsible person - priority - target date - tracking status  Method: GET  URL: /api/assessment/v1/action_plan.php?assessment_id=1  Department-wise: /api/assessment/v1/action_plan.php?assessment_id=1&dept_id=25

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `gap`
- `framework`
- `assessment_master`
- `facilities`
- `assessment_department`
- `JSON`
- `assessment_response`
- `assessment_action_plan`
- `IF`
- `assessment_action_plan_library`

**Events dispatched**

None detected.

### `api/assessment/v1/action_plan_closure.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** action_plan_closure.php  Update action plan closure status.  Rule: - If gap is closed:      status = COMPLETED      revised_score can be updated      evidence is optional  - If gap is not closed:      status = IN_PROGRESS or OPEN      original score remains unchanged  Method: POST  Body: {   "assessment_id": 1,   "dept_id": 25,   "checkpoint_id": 21070,   "is_gap_closed": true,   "revised_score": 2,   "closure_remarks": "Training completed and equipment arranged",   "closure_evidence_url": "" } POST /api/assessment/v1/action_plan_closure.php

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`
- `dept_id`
- `checkpoint_id`
- `is_gap_closed`
- `revised_score`
- `closure_remarks`
- `closure_evidence_url`

**Database tables referenced**

- `action`
- `assessment_master`
- `assessment_department`
- `assessment_response`
- `assessment_action_plan`
- `prepare`
- `failed`

**Events dispatched**

None detected.

### `api/assessment/v1/action_plan_save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** action_plan_save.php  Save or update user action plan for gap checkpoint.  Method: POST  Body: {   "assessment_id": 1,   "dept_id": 25,   "checkpoint_id": 21070,   "system_action_plan": "Suggested plan from JSON",   "user_action_plan": "User custom plan",   "achievability": "ACHIEVABLE",   "responsible_person": "Dr. Amit",   "priority": "HIGH",   "target_date": "2026-07-25",   "status": "OPEN" }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`
- `dept_id`
- `checkpoint_id`
- `system_action_plan`
- `user_action_plan`
- `achievability`
- `responsible_person`
- `priority`
- `target_date`
- `status`

**Database tables referenced**

- `user`
- `JSON`
- `assessment_master`
- `assessment_department`
- `assessment_response`
- `action`
- `assessment_action_plan`
- `system_action_plan`
- `IF`
- `assessment_action_plan_library`

**Events dispatched**

- `gap.action_plan.saved`

### `api/assessment/v1/action_plan_update.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** action_plan_update.php  Update existing action plan.  Method : POST  Body {   "id":15,   "user_action_plan":"Arrange training",   "achievability":"ACHIEVABLE",   "responsible_person":"Dr Kumar",   "priority":"HIGH",   "target_date":"2026-08-15",   "status":"IN_PROGRESS" }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `id`
- `user_action_plan`
- `achievability`
- `priority`
- `status`
- `responsible_person`
- `target_date`

**Database tables referenced**

- `existing`
- `assessment_action_plan`

**Events dispatched**

None detected.

### `api/assessment/v1/active_assessment.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** active_assessment.php  Returns active assessment for logged-in user's facility.  Rule: - One facility can have only one ACTIVE assessment. - If no active assessment exists, return has_active = false.  Method: GET  URL: /api/assessment/v1/active_assessment.php

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`

**Events dispatched**

None detected.

### `api/assessment/v1/assessor_info_get.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** assessor_info_get.php  Get assessor / assessee information department-wise.  Method: GET  URL: /api/assessment/v1/assessor_info_get.php?assessment_id=1&dept_id=25

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department_status`
- `assessment_assessor_info`

**Events dispatched**

None detected.

### `api/assessment/v1/assessor_info_save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** assessor_info_save.php  Save assessor / assessee information department-wise.  Rule: - One assessor info per assessment + department. - If already saved, return existing data and do not overwrite.  Method: POST  Body: {   "assessment_id": 1,   "dept_id": 25,   "assessment_date": "2026-06-25",   "assessment_type": "INTERNAL",    "assessor_name": "Dr. Manish",   "assessor_designation": "Medical Officer",   "assessor_mobile": "8294386969",   "assessor_email": "manish@example.com",    "assessee_name": "Dr. Manish Kumar",   "assessee_designation": "Department In-charge",   "assessee_mobile": "9876543211",   "assessee_email": "rManish_kumar@example.com",    "remarks": "Optional remarks" }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`
- `dept_id`
- `assessment_date`
- `assessment_type`
- `assessor_name`
- `assessor_designation`
- `assessor_mobile`
- `assessor_email`
- `assessee_name`
- `assessee_designation`
- `assessee_mobile`
- `assessee_email`
- `remarks`

**Database tables referenced**

- `assessment_master`
- `assessment_department_status`
- `assessment_assessor_info`

**Events dispatched**

None detected.

### `api/assessment/v1/cancel_assessment.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** cancel_assessment.php  Cancels the ACTIVE assessment for the logged-in facility.  Method: POST  URL: /api/assessment/v1/cancel_assessment.php  Body: {   "assessment_id": 1 }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`

**Database tables referenced**

- `assessment_master`

**Events dispatched**

None detected.

### `api/assessment/v1/complete-cycle.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** complete-cycle.php  Mark full assessment cycle as completed.  Rule: Cycle can be completed only when all active departments inside the cycle are completed.  URL: /api/assessment/v1/complete-cycle.php  Method: POST  Body: {   "cycle_id": 1 }

**Dependencies included**

- `/../../core/Response.php`
- `/../../service/DynamicAssessmentService.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `cycle_id`

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/assessment/v1/complete-department.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** complete_department.php  Complete department assessment.  Rule: - Assessment must be ACTIVE. - Department must be activated. - Department must be IN_PROGRESS. - Assessor information must be saved. - At least one response must be saved.  Method: POST  Body: {   "assessment_id": 1,   "dept_id": 25,   "force_complete": false }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`
- `dept_id`
- `force_complete`

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `assessment_assessor_info`
- `assessment_response`

**Events dispatched**

None detected.

### `api/assessment/v1/complete_assessment.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** complete_assessment.php  Complete full assessment.  Rule: - Assessment must be ACTIVE. - At least one department must be activated. - All activated departments must be COMPLETED.  Method: POST  Body: {   "assessment_id": 1 }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`

**Database tables referenced**

- `assessment_master`
- `assessment_department`

**Events dispatched**

- `assessment.completed`

### `api/assessment/v1/create_assessment.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** create_assessment.php  Creates a new assessment for logged-in user's facility.  Rule: - One facility can have only one ACTIVE assessment. - New assessment can be created only after previous one is   COMPLETED or CANCELLED.  Method: POST  URL: /api/assessment/v1/create_assessment.php  Body: {   "assessment_name": "Internal Assessment",   "framework_code": "saqshi-nqas",   "start_date": "2026-06-25",   "end_date": "2026-07-25" }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_name`
- `framework_code`
- `start_date`
- `end_date`

**Database tables referenced**

- `assessment_master`

**Events dispatched**

- `assessment.created`

### `api/assessment/v1/dashboard_insights.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

- `function dashboardFacilityTypeId`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `assessment_response`

**Events dispatched**

None detected.

### `api/assessment/v1/department-status/list.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** list.php  Get department activation status list for facility + assessment period.  URL: /api/assessment/v1/department-status/list.php?fac_id=1&ass_period=1

**Dependencies included**

- `/../../../core/Response.php`
- `/../../../service/DepartmentStatusService.php`
- `/../../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/assessment/v1/department-status/save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** save.php  Save department activation/deactivation status for logged-in user's facility + assessment period.  Uses session: - fac_id - u_id  URL: /api/assessment/v1/department-status/save.php  Method: POST  Body bulk: {   "ass_period": 1,   "departments": [     {"dept_id": 1, "is_active": 1},     {"dept_id": 2, "is_active": 0}   ] }  Body single: {   "ass_period": 1,   "dept_id": 1,   "is_active": 1 }

**Dependencies included**

- `/../../../auth_api.php`
- `/../../../service/DepartmentStatusService.php`
- `/../../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `ass_period`
- `departments`
- `dept_id`
- `is_active`

**Database tables referenced**

None detected.

**Events dispatched**

- `department.activation.saved`

### `api/assessment/v1/department/list.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** list.php  List all framework departments for logged-in facility type and show activation status for current active assessment.  Method: GET  URL: /api/assessment/v1/department/list.php?framework=saqshi-nqas&assessment_id=1

**Dependencies included**

- `/../../../auth_api.php`
- `/../../../core/FrameworkEngine.php`
- `/../../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `facilities`
- `framework`
- `DB`
- `assessment_department`

**Events dispatched**

None detected.

### `api/assessment/v1/department/save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** save.php  Activate departments for active assessment.  Rule: - Department can be activated once. - Once activated, it cannot be deactivated.  Method: POST  Body: {   "assessment_id": 1,   "departments": [     {"dept_id": 25},     {"dept_id": 26}   ] }

**Dependencies included**

- `/../../../auth_api.php`
- `/../../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`
- `departments`

**Database tables referenced**

- `assessment_master`
- `to`
- `assessment_department`
- `failed`

**Events dispatched**

None detected.

### `api/assessment/v1/gap_analysis.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** gap_analysis.php  Generate gap analysis for assessment / department.  Gap means: - Non Compliant score = 0 - Partially Compliant score = 1  Method: GET  URL: Full assessment: /api/assessment/v1/gap_analysis.php?assessment_id=1  Department: /api/assessment/v1/gap_analysis.php?assessment_id=1&dept_id=25

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `assessment_response`
- `assessment_action_plan`

**Events dispatched**

None detected.

### `api/assessment/v1/get-cycle.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** get-cycle.php  Fetch assessment cycle details with departments.  URL: /api/assessment/v1/get-cycle.php?cycle_id=1

**Dependencies included**

- `/../../core/Response.php`
- `/../../service/DynamicAssessmentService.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/assessment/v1/get_checkpoint.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** get_checkpoint.php  Load one checkpoint at a time for department assessment.  Method: GET  URL: /api/assessment/v1/get_checkpoint.php   ?assessment_id=1   &dept_id=25   &concern_id=4   &subtype_id=96   &checkpoint_id=0  checkpoint_id = 0 means first checkpoint.

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `assessment_assessor_info`
- `facilities`
- `framework`
- `assessment_response`

**Events dispatched**

None detected.

### `api/assessment/v1/list.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** list.php  Lists all assessments for the logged-in user's facility.  Method: GET  URL: /api/assessment/v1/list.php

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

- `function listFacilityTypeId`
- `function listCheckpointMaxScore`
- `function listFrameworkTotalScore`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department_status`
- `assessment_department`
- `assessment_response`

**Events dispatched**

None detected.

### `api/assessment/v1/next_checkpoint.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** next_checkpoint.php  Find next checkpoint for selected assessment department.  Method: GET  URL: /api/assessment/v1/next_checkpoint.php   ?assessment_id=1   &dept_id=25   &concern_id=4   &subtype_id=96   &checkpoint_id=21070

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `facilities`

**Events dispatched**

None detected.

### `api/assessment/v1/previous_checkpoint.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** previous_checkpoint.php  Find previous checkpoint for selected assessment department.  Method: GET  URL: /api/assessment/v1/previous_checkpoint.php   ?assessment_id=1   &dept_id=25   &concern_id=4   &subtype_id=96   &checkpoint_id=21071

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `facilities`

**Events dispatched**

None detected.

### `api/assessment/v1/progress.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** progress.php  Assessment progress summary.  Includes: - Department progress - Assessor info - Original score - Improved/revised score - Gap closure summary  response assessment_id is the current assessment_id  Method: GET  URL: /api/assessment/v1/progress.php?assessment_id=1

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

- `function progressFacilityTypeId`
- `function progressCheckpointMaxScore`
- `function progressDepartmentBase`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `assessment_response`
- `assessment_action_plan`
- `assessment_assessor_info`

**Events dispatched**

None detected.

### `api/assessment/v1/resume.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** resume.php  Resume an assessment cycle.  URL: /api/assessment/v1/resume.php?cycle_id=1

**Dependencies included**

- `/../../core/Response.php`
- `/../../service/DynamicAssessmentService.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/assessment/v1/resume_department.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** resume_department.php  Resume department assessment.  It returns the checkpoint_id from where user should continue.  Logic: - If current_checkpoint_id exists, resume from it. - If not, return checkpoint_id = 0 so UI loads first checkpoint.  Method: GET  URL: /api/assessment/v1/resume_department.php?assessment_id=1&dept_id=25

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `where`
- `it`
- `assessment_master`
- `assessment_department`
- `assessment_assessor_info`
- `assessment_response`

**Events dispatched**

None detected.

### `api/assessment/v1/save-response.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** save_response.php  Save or update one checkpoint response.  New simplified flow: - responses are stored by assessment_id in assessment_response - response saved against assessment_id + dept_id + checkpoint_id - current checkpoint is updated in assessment_department  Method: POST  Body: {   "assessment_id": 1,   "dept_id": 25,   "checkpoint_id": 21070,   "response_value": 2,   "score": 2,   "remarks": "",   "evidence_url": "" }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`
- `dept_id`
- `checkpoint_id`
- `response_value`
- `score`
- `remarks`
- `evidence_url`

**Database tables referenced**

- `one`
- `assessment_master`
- `assessment_department`
- `assessment_assessor_info`
- `response`
- `assessment_response`
- `response_value`
- `current`
- `failed`

**Events dispatched**

- `checklist.response.saved`

### `api/assessment/v1/score.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** score.php  Calculate assessment score.  Supports: - Full assessment score - Department-wise score  Original score: - assessment_response.score  Improved score: - assessment_action_plan.revised_score if available - otherwise original score  Simplified design: - responses are stored by assessment_id in assessment_response

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

- `function scoreFacilityTypeId`
- `function scoreCheckpointMaxScore`
- `function scoreDepartmentBase`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `completed`
- `assessment_response`
- `assessment_action_plan`

**Events dispatched**

None detected.

### `api/assessment/v1/start.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** start.php  Start / Create Dynamic Assessment Cycle  URL: /api/assessment/v1/start.php  Method: POST  Body: {   "fac_id": 1,   "ass_period": 1,   "framework_code": "sample-framework",   "instance_no": 1,   "user_id": 1 }

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/Response.php`
- `/../../service/DynamicAssessmentService.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `fac_id`
- `ass_period`
- `framework_code`
- `instance_no`
- `user_id`

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/assessment/v1/start_cycle.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** start_cycle.php  Create or return existing assessment cycle for logged-in user's assigned facility.  Uses: $_SESSION['fac_id'] $_SESSION['u_id']  URL: /api/assessment/v1/start_cycle.php  Method: POST  Body: {   "framework_code": "saqshi-nqas",   "ass_period": 1,   "instance_no": 1,   "departments": [      {"dept_id": 1, "is_active": 1},      {"dept_id": 2, "is_active": 0}   ] }

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/Response.php`
- `/../../service/DynamicAssessmentService.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `framework_code`
- `ass_period`
- `instance_no`
- `departments`

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/assessment/v1/start_department.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** start_department.php  Start or resume department assessment.  New simplified flow:  assessment_master      ↓ assessment_department      ↓ assessment_assessor_info      ↓ assessment_response  Method: POST  Body: {   "assessment_id": 1,   "dept_id": 25 }

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `assessment_id`
- `dept_id`

**Database tables referenced**

- `assessment_master`
- `assessment_department`
- `assessment_department_status`
- `assessment_assessor_info`
- `assessment_response`

**Events dispatched**

None detected.

### `api/auth/v1/captcha.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** captcha.php  Generates a small server-side math captcha for login.  Method: GET  URL: /api/auth/v1/captcha.php

**Dependencies included**

- `/../../public_api.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/auth/v1/csrf.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/../../public_api.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/auth/v1/login.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** login.php  Secure login API for SaQshi.  Method: POST  URL: /api/auth/v1/login.php

**Dependencies included**

- `/../../public_api.php`
- `/../../core/Auth.php`
- `/../../core/Csrf.php`
- `/../../core/LoginCrypto.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `username`
- `password`
- `captcha`
- `password_enc`

**Database tables referenced**

None detected.

**Events dispatched**

- `auth.login.started`
- `auth.login.failed`
- `auth.login.auth_checked`
- `auth.login.succeeded`

### `api/auth/v1/login1.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`

**Dependencies included**

- `/../../public_api.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/auth/v1/login_key.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../public_api.php`
- `/../../core/LoginCrypto.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/auth/v1/logout.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** logout.php  Logout authenticated user.  Method: POST  URL: /api/auth/v1/logout.php

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/Auth.php`
- `/../../core/Csrf.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/auth/v1/logout1.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `../../../service/AuthService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/auth/v1/me.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** me.php  Returns current logged-in user session details.  Method: GET  URL: /api/auth/v1/me.php

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/Auth.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/auth/v1/validate.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `../../../service/AuthService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/certification/v1/current.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/certification/v1/dashboard.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/certification/v1/history.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/certification/v1/list.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/certification/v1/renewal_status.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/certification/v1/save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

- `certification.updated`

### `api/certification/v1/update.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/certification/v1/validate.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/chat/v1/clear.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/chat/v1/history.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/chat/v1/send.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`

**Dependencies included**

- `/_common.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/files/v1/delete.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** delete.php  Delete an uploaded file owned by the local uploads folder.

**Dependencies included**

- `/../../auth_api.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

- `url`
- `file_url`
- `path`

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/files/v1/upload.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`
- **Source intent:** upload.php  Authenticated file upload endpoint.  Used for: - assessment evidence - gap closure evidence - supporting documents

**Dependencies included**

- `/../../auth_api.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/framework/v1/assessment_methods.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../core/Response.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/framework/v1/checkpoints.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** checkpoints.php  Load checklist dynamically from nested SaQshi framework JSON.  Flow: Logged-in user facility → facility type → department → concern → subtype → assessment method → checkpoints  URL: /api/framework/v1/checkpoints.php   ?framework=saqshi-nqas   &ass_period=1   &dept_id=1   &concern_id=1   &subtype_id=1   &assessment_method=SI

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/Response.php`
- `/../../core/FrameworkEngine.php`
- `/../../service/DepartmentStatusService.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `nested`
- `facilities`
- `assessment_response`

**Events dispatched**

None detected.

### `api/framework/v1/concerns.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** concerns.php  Returns concerns for selected department based on logged-in user's assigned facility type.  Uses: $_SESSION['fac_id']  URL: /api/framework/v1/concerns.php?framework=saqshi-nqas&dept_id=1

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../core/Response.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/framework/v1/departments.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** departments.php  Returns departments from framework JSON with runtime active/inactive status for a facility + assessment period.  URL: /api/framework/v1/departments.php?framework=sample-framework&facility_type=DH&fac_id=1&ass_period=1

**Dependencies included**

- `/../../core/Response.php`
- `/../../core/FrameworkEngine.php`
- `/../../service/DepartmentStatusService.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `framework`

**Events dispatched**

None detected.

### `api/framework/v1/facility-types.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** facility-types.php  Load facility types from nested SaQshi JSON framework.  URL: /api/framework/v1/facility-types.php?framework=saqshi-nqas

**Dependencies included**

- `/../../core/Response.php`
- `/../../core/FrameworkEngine.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `nested`

**Events dispatched**

None detected.

### `api/framework/v1/load.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/../../core/Response.php`
- `/../../core/FrameworkEngine.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/framework/v1/my_departments.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** my_departments.php  Returns departments applicable to logged-in user's facility.  Uses: $_SESSION['fac_id']  Files: api/config/masters/facilities.json api/config/frameworks/saqshi-nqas.json  URL: /api/framework/v1/my_departments.php?framework=saqshi-nqas

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/framework/v1/my_facility.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** my_facility.php  Returns logged-in user's assigned facility details from JSON master file.

**Dependencies included**

- `/../../auth_api.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `JSON`

**Events dispatched**

None detected.

### `api/framework/v1/subtypes.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** subtypes.php  Returns subtypes/standards for selected department + concern based on logged-in user's assigned facility type.  Uses: $_SESSION['fac_id']  URL: /api/framework/v1/subtypes.php?framework=saqshi-nqas&dept_id=1&concern_id=1

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../core/Response.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/dashboard.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/DashboardService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/indicator_history.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/IndicatorService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/indicator_list.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/IndicatorService.php`
- `/../../service/PerformanceService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/indicator_save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/IndicatorService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/kpi_history.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/KPIService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/kpi_list.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/KPIService.php`
- `/../../service/PerformanceService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/kpi_save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/KPIService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

- `performance.kpi.saved`

### `api/performance/v1/outcome_history.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/OutcomeService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/outcome_list.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/OutcomeService.php`
- `/../../service/PerformanceService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/performance/v1/outcome_save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/OutcomeService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

- `performance.outcome.saved`

### `api/performance/v1/trend.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/PerformanceService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/reports/v1/checkpoint_progress_report.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** checkpoint_progress_report.php  Download checkpoint progress report in checklist Excel format.  Method: GET /api/reports/v1/checkpoint_progress_report.php?assessment_id=1

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

- `function scorecardNormalize`
- `function scorecardFacility`
- `function scorecardSharedStrings`
- `function scorecardCellText`
- `function scorecardColumnIndex`
- `function scorecardSetCell`
- `function scorecardCreateRow`
- `function scorecardApplyStyle`
- `function scorecardTemplateStyles`
- `function scorecardSetStyledCell`
- `function scorecardSetColumnWidth`
- `function scorecardRemoveMerges`
- `function scorecardAddMerge`
- `function scorecardRemoveColumnsAfter`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department_status`
- `assessment_response`
- `assessment_cycle_response`
- `assessment_action_plan`
- `assessment_assessor_info`

**Events dispatched**

None detected.

### `api/reports/v1/checkpoint_scorecard.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`
- **Source intent:** checkpoint_scorecard.php  Download checkpoint score card in standard Excel format.  Method: GET /api/reports/v1/checkpoint_scorecard.php?assessment_id=1

**Dependencies included**

- `/../../auth_api.php`
- `/../../core/FrameworkEngine.php`
- `/../../assets/conn/db.php`

**Declared classes and functions**

- `function scorecardNormalize`
- `function scorecardFacility`
- `function scorecardSharedStrings`
- `function scorecardCellText`
- `function scorecardColumnIndex`
- `function scorecardSetCell`
- `function scorecardCreateRow`
- `function scorecardApplyStyle`
- `function scorecardTemplateStyles`
- `function scorecardSetStyledCell`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `assessment_department_status`
- `assessment_response`
- `assessment_cycle_response`
- `assessment_action_plan`
- `assessment_assessor_info`

**Events dispatched**

None detected.

### `api/state/v1/assessment_history.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/assessment_progress.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/boundary.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/certification_summary.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/certification_update.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `POST`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `API`

**Events dispatched**

None detected.

### `api/state/v1/cqi_summary.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/dashboard.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

- `state.dashboard.viewed`

### `api/state/v1/facility_category.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/facility_detail.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/facility_progress.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/indicator_analytics.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`
- `/../../service/StateIndicatorAnalyticsService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/map.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/performance_summary.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/reports.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`
- `/../../service/StateReportService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/users.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** `GET`

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/user_save.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `workflow`

**Events dispatched**

None detected.

### `api/state/v1/user_status.php`

- **Role:** HTTP endpoint
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/_bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

## Runtime dependency

### `api/assets/conn/db.php`

- **Role:** Runtime dependency
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/core/Env.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/assets/conn/session.php`

- **Role:** Runtime dependency
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `s_user`

**Events dispatched**

None detected.

## API support file

### `api/auth_api.php`

- **Role:** API support file
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** auth_api.php  Base file for APIs requiring authenticated session.

**Dependencies included**

- `/bootstrap.php`
- `/core/SessionManager.php`
- `/core/Csrf.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/bootstrap.php`

- **Role:** API support file
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** bootstrap.php  Global bootstrap for all SaQshi APIs. Loads security, session, response and common settings.

**Dependencies included**

- `/core/Security.php`
- `/core/Response.php`
- `/core/ErrorHandler.php`
- `/core/Env.php`
- `/core/SessionManager.php`
- `/core/Csrf.php`
- `/core/Event.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/controllers/AssessmentController.php`

- **Role:** API support file
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/public_api.php`

- **Role:** API support file
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** public_api.php  Base file for APIs that DO NOT require login.

**Dependencies included**

- `/bootstrap.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/routes.php`

- **Role:** API support file
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `core/AuthMiddleware.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

## Internal endpoint helper

### `api/certification/v1/_common.php`

- **Role:** Internal endpoint helper
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/auth_api.php`
- `/assets/conn/db.php`
- `/service/CertificationService.php`

**Declared classes and functions**

- `function respond`
- `function certificationPayload`
- `function certificationFilters`
- `function certificationHandle`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/chat/v1/_common.php`

- **Role:** Internal endpoint helper
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/ChatAssistantService.php`

**Declared classes and functions**

- `function chatPayload`
- `function chatUserId`
- `function chatFacilityId`
- `function chatHandle`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/state/v1/_bootstrap.php`

- **Role:** Internal endpoint helper
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/../../auth_api.php`
- `/../../assets/conn/db.php`
- `/../../service/StateDashboardService.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

## Core infrastructure

### `api/core/AuditLogger.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/Auth.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** Auth.php  Centralized authentication service for SaQshi.  Uses: - s_user table - u_role table - login_attempts table - SessionManager  login_attempts columns: id, username, ip_address, attempt_time, status

**Dependencies included**

- `/SessionManager.php`
- `/Crypto.php`

**Declared classes and functions**

- `class Auth`
- `function __construct`
- `function login`
- `function logout`
- `function me`
- `function findUser`
- `function findUserById`
- `function decryptUserProfileFields`
- `function passwordStatus`
- `function upgradePasswordHash`
- `function isLocked`
- `function recordAttempt`
- `function clearOldFailedAttempts`
- `function loginAttemptTableExists`
- `function hashPassword`
- `function success`
- `function error`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `s_user`
- `u_role`
- `prepare`
- `login_attempts`

**Events dispatched**

None detected.

### `api/core/AuthMiddleware.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

- `class AuthMiddleware`
- `function check`
- `function role`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/ConfigLoader.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** ConfigLoader.php  SaQshi Open Source Framework Loads JSON-based configuration files for frameworks, tenants, UI, dashboards, and reports.

**Dependencies included**

None detected.

**Declared classes and functions**

- `class ConfigLoader`
- `function loadJson`
- `function loadFramework`
- `function loadTenant`
- `function loadUi`
- `function loadDashboard`
- `function loadReport`
- `function exists`
- `function listConfigs`
- `function setBasePath`
- `function sanitizeCode`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `a`

**Events dispatched**

None detected.

### `api/core/Crypto.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** Crypto.php  Small field encryption helper for sensitive profile data.  Format: enc:v1:<base64(mode + nonce/iv + tag + ciphertext)>

**Dependencies included**

None detected.

**Declared classes and functions**

- `class Crypto`
- `function encrypt`
- `function decrypt`
- `function decryptFields`
- `function isEncrypted`
- `function needsEncryption`
- `function key`
- `function encryptFallback`
- `function decryptFallbackPayload`
- `function xorWithKeystream`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/Csrf.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** Csrf.php  CSRF Protection for SaQshi APIs  Client must send: X-CSRF-TOKEN: token  OR csrf_token in POST/JSON body

**Dependencies included**

None detected.

**Declared classes and functions**

- `class Csrf`
- `function generate`
- `function token`
- `function regenerate`
- `function validate`
- `function getTokenInfo`
- `function extractRequestToken`
- `function destroy`
- `function fail`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `session`
- `request`

**Events dispatched**

None detected.

### `api/core/db.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/assets/conn/db.php`

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/Env.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

- `class Env`
- `function load`
- `function get`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/ErrorHandler.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

- `class ErrorHandler`
- `function register`
- `function requestId`
- `function friendlyMessage`
- `function log`
- `function handleException`
- `function handleError`
- `function handleShutdown`
- `function sendFriendly`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/Event.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** @var array<string, array<int, callable>>

**Dependencies included**

None detected.

**Declared classes and functions**

- `class Event`
- `function listen`
- `function dispatch`
- `function traceRequest`
- `function defaultMeta`
- `function safeSessionValue`
- `function writeLog`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

- `event.name`

### `api/core/FrameworkEngine.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/ConfigLoader.php`

**Declared classes and functions**

- `class FrameworkEngine`
- `function __construct`
- `function load`
- `function toArray`
- `function getFacilityTypes`
- `function getFacilityTypeById`
- `function getDepartments`
- `function getDepartmentById`
- `function getConcerns`
- `function getConcernById`
- `function getSubtypes`
- `function getSubtypeById`
- `function getCheckpoints`
- `function getCheckpointById`
- `function calculateScore`
- `function calculateScoreForScope`
- `function resolveOptionScore`
- `function getMaxScore`
- `function validateFramework`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/LoginCrypto.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

- `class LoginCrypto`
- `function opensslConfigPath`
- `function opensslErrors`
- `function keyDir`
- `function privateKeyPath`
- `function publicKeyPath`
- `function publicKey`
- `function decryptPassword`
- `function ensureKeys`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/Response.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** Response.php SaQshi Standard JSON Response Handler

**Dependencies included**

None detected.

**Declared classes and functions**

- `class Response`
- `function send`
- `function success`
- `function created`
- `function error`
- `function validation`
- `function unauthorized`
- `function forbidden`
- `function notFound`
- `function serverError`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/RoleMiddleware.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/Security.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** Security.php  Centralized security helper for SaQshi APIs.  Handles: - Security headers - JSON input parsing - HTTP method enforcement - Basic input sanitization helpers - Secure error-safe response helpers

**Dependencies included**

None detected.

**Declared classes and functions**

- `class Security`
- `function headers`
- `function requireMethod`
- `function requireAnyMethod`
- `function jsonInput`
- `function requireFields`
- `function cleanString`
- `function int`
- `function bool`
- `function email`
- `function token`
- `function hashEquals`
- `function fail`
- `function success`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `list`

**Events dispatched**

None detected.

### `api/core/SessionManager.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** SessionManager.php  Centralized secure session management for SaQshi APIs.  Handles: - Secure session start - Login session creation - Session timeout - Session regeneration - Logged-in user checks - Common session getters

**Dependencies included**

None detected.

**Declared classes and functions**

- `class SessionManager`
- `function start`
- `function login`
- `function logout`
- `function isLoggedIn`
- `function requireLogin`
- `function userId`
- `function username`
- `function roleId`
- `function facilityId`
- `function departmentId`
- `function user`
- `function updateProfile`
- `function checkTimeout`
- `function regeneratePeriodically`
- `function isSameClient`
- `function hashClientIp`
- `function hashUserAgent`
- `function jsonError`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/core/Validator.php`

- **Role:** Core infrastructure
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

None detected.

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

## Service layer

### `api/service/AuthService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/../core/Crypto.php`

**Declared classes and functions**

- `class AuthService`
- `function login`
- `function validate`
- `function logout`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `s_user`
- `u_role`

**Events dispatched**

None detected.

### `api/service/CertificationExpiryService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

- `class CertificationExpiryService`
- `function calculateValidTo`
- `function renewalStatus`
- `function normalizeStatus`
- `function validityYears`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/CertificationService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/CertificationValidator.php`
- `/CertificationExpiryService.php`

**Declared classes and functions**

- `class CertificationService`
- `function config`
- `function ensureTables`
- `function current`
- `function list`
- `function save`
- `function update`
- `function dashboard`
- `function history`
- `function findById`
- `function rawById`
- `function present`
- `function audit`
- `function ensureColumn`
- `function currentUserId`
- `function sessionFacilityId`
- `function withAssignedFacility`
- `function facilityFromJson`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `IF`
- `CURRENT_TIMESTAMP`
- `cert_details`
- `prepare`
- `failed`
- `certification_history`

**Events dispatched**

None detected.

### `api/service/CertificationValidator.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/CertificationExpiryService.php`

**Declared classes and functions**

- `class CertificationValidator`
- `function validatePayload`
- `function isDate`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/ChatAssistantService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

- `class ChatAssistantService`
- `function ensureTable`
- `function send`
- `function history`
- `function clear`
- `function saveMessage`
- `function buildReply`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `IF`
- `ai_chat_messages`
- `the`

**Events dispatched**

None detected.

### `api/service/DashboardService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** DashboardService.php  Performance dashboard service facade.

**Dependencies included**

- `/PerformanceService.php`

**Declared classes and functions**

- `class DashboardService`
- `function dashboard`
- `function summary`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/DepartmentStatusService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** DepartmentStatusService.php  Runtime department activation/deactivation service for facility + assessment period.

**Dependencies included**

None detected.

**Declared classes and functions**

- `class DepartmentStatusService`
- `function __construct`
- `function saveStatus`
- `function saveBulkStatus`
- `function getStatusList`
- `function isDepartmentActive`
- `function success`
- `function error`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `department`
- `assessment_department_status`
- `is_active`

**Events dispatched**

None detected.

### `api/service/DynamicAssessmentService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** DynamicAssessmentService.php  SaQshi Configurable Assessment Engine  Hierarchy: Facility + Period + Framework + Instance = Assessment Cycle One Cycle can have multiple Departments One Department can have multiple Checklist Responses

**Dependencies included**

- `/../core/FrameworkEngine.php`

**Declared classes and functions**

- `class DynamicAssessmentService`
- `function __construct`
- `function createCycle`
- `function addDepartments`
- `function saveResponse`
- `function getCycle`
- `function getResponses`
- `function completeDepartment`
- `function completeCycle`
- `function calculateCycleScore`
- `function calculateCheckpointScore`
- `function isDepartmentActive`
- `function markDepartmentInProgress`
- `function markCycleInProgress`
- `function getCycleId`
- `function success`
- `function error`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_cycle`
- `updated_on`
- `assessment_cycle_department`
- `is_active`
- `assessment_cycle_response`
- `response_value`

**Events dispatched**

None detected.

### `api/service/FormulaEngine.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** FormulaEngine.php  Formula helper for Performance Monitoring.

**Dependencies included**

None detected.

**Declared classes and functions**

- `class FormulaEngine`
- `function configPath`
- `function formulas`
- `function findFormula`
- `function calculate`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/IndicatorService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** IndicatorService.php  Generic KPI / Outcome indicator service per v3 design.

**Dependencies included**

- `/KPIService.php`
- `/OutcomeService.php`
- `/ValidationService.php`

**Declared classes and functions**

- `class IndicatorService`
- `function configPath`
- `function list`
- `function save`
- `function history`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/KPIService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** KPIService.php  KPI list, save and history service.

**Dependencies included**

- `/PerformanceService.php`

**Declared classes and functions**

- `class KPIService`
- `function configPath`
- `function list`
- `function save`
- `function history`
- `function saveEntry`
- `function entryHistory`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `performance_entries`
- `indicator_code`

**Events dispatched**

None detected.

### `api/service/OutcomeService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** OutcomeService.php  Outcome list, save and history service.

**Dependencies included**

- `/KPIService.php`

**Declared classes and functions**

- `class OutcomeService`
- `function configPath`
- `function list`
- `function save`
- `function history`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/PerformanceService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** PerformanceService.php  Shared helpers for Performance Monitoring.

**Dependencies included**

- `/FormulaEngine.php`

**Declared classes and functions**

- `class PerformanceService`
- `function tableName`
- `function ensureTable`
- `function readJson`
- `function rulesConfig`
- `function facilityTypeRule`
- `function assertIndicatorAllowed`
- `function configuredIndicatorCount`
- `function effectivePerformanceType`
- `function facilityMeta`
- `function departmentNames`
- `function departmentName`
- `function activeAssessment`
- `function latestActiveAssessmentPeriodId`
- `function activeDepartmentIds`
- `function filterByDepartmentIds`
- `function flattenIndicators`
- `function normalizeIndicator`
- `function dashboard`
- `function summary`
- `function monthlyStatus`
- `function trend`
- `function indicatorTrends`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `IF`
- `CURRENT_TIMESTAMP`
- `assessment_master`
- `assessment_department_status`
- `performance_entries`

**Events dispatched**

None detected.

### `api/service/StateAssessmentService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StateAssessmentService`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/StateCertificationService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StateCertificationService`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/StateCQIService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StateCQIService`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/StateDashboardService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/CertificationService.php`
- `/CertificationExpiryService.php`
- `/PerformanceService.php`
- `/../core/FrameworkEngine.php`

**Declared classes and functions**

- `class StateDashboardService`
- `function requireStateRole`
- `function applyMonitoringScope`
- `function monitoringScopeForUser`
- `function dashboard`
- `function safeSection`
- `function facilityCategory`
- `function facilityTypeLabel`
- `function facilityTypeMap`
- `function certificationSummary`
- `function certificationRowMatches`
- `function certificationMap`
- `function updateCertificationStatus`
- `function assessmentProgress`
- `function assessmentDepartmentProgress`
- `function assessmentResponseProgress`
- `function assessmentActionPlanProgress`
- `function assessmentScoreBase`
- `function checkpointMaxScore`
- `function cqiSummary`
- `function performanceSummary`
- `function performanceDetailsForFacilities`
- `function performanceLatestSubmittedCount`
- `function facilityDetail`
- `function facilityHierarchy`
- `function facilityAssessmentSummary`
- `function facilityPerformanceSummary`
- `function facilityCqiSummary`
- `function resolveFacilityId`
- `function users`
- `function updateUserStatus`
- `function attention`
- `function currentMonthStatus`
- `function latestAssessmentAttention`
- `function normalizeFilters`
- `function pagination`
- `function paginationMeta`
- `function facilitiesFromJson`
- `function filteredFacilities`
- `function facilitiesById`
- `function facilityCoordinatesFromDb`
- `function mapBoundary`
- `function mapConfig`
- `function mapMasterConfig`
- `function selectedMapState`
- `function mapKey`
- `function mapSourcePath`
- `function topologyToFeatureCollection`
- `function topologyGeometries`
- `function topologyGeometry`
- `function topologyLine`
- `function topologyArc`
- `function normalizeNin`
- `function dateOrEmpty`
- `function decodeJsonObject`
- `function certStatusFromPayload`
- `function normalizeCertStatus`
- `function latestCertificationHistory`
- `function facilityWhere`
- `function certWhere`
- `function assessmentWhere`
- `function actionPlanWhere`
- `function performanceWhere`
- `function tableExists`
- `function columnExists`
- `function facilityTypeIdColumn`
- `function certFacilityColumn`
- `function scalar`
- `function one`
- `function rows`
- `function percent`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `certification_history`
- `failed`
- `assessment_master`
- `assessment_department_status`
- `assessment_department`
- `assessment_action_plan`
- `facilities`
- `performance_entries`
- `s_user`
- `u_role`
- `is`
- `prepare`
- `INFORMATION_SCHEMA`

**Events dispatched**

- `state.certification.status_updated`
- `state.user.status_updated`

### `api/service/StateFacilityCategoryService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StateFacilityCategoryService`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/StateFacilityDrilldownService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StateFacilityDrilldownService`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/StateIndicatorAnalyticsService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

None detected.

**Declared classes and functions**

- `class StateIndicatorAnalyticsService`
- `function analytics`
- `function streamZeroFacilityList`
- `function assessmentWeakIndicators`
- `function performanceWeakIndicators`
- `function weaknessLabel`
- `function facilityTypeName`
- `function csvRow`
- `function responseTable`
- `function checkpointMap`
- `function performanceIndicatorMap`
- `function collectPerformanceIndicators`
- `function facilityWhere`
- `function pagination`
- `function paginationMeta`
- `function rows`
- `function one`
- `function prepare`
- `function tableExists`
- `function columnExists`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `assessment_master`
- `facilities`
- `performance_entries`
- `INFORMATION_SCHEMA`

**Events dispatched**

None detected.

### `api/service/StateMapService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StateMapService`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/StatePerformanceService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StatePerformanceService`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/StateReportService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StateReportService`
- `function streamCsv`
- `function exportCatalog`
- `function writeSummary`
- `function writeFacilities`
- `function writeAssessments`
- `function writeCqi`
- `function writePerformance`
- `function writeCertification`
- `function responseTable`
- `function selectColumn`
- `function monthName`
- `function facilityTypeName`
- `function departmentMap`
- `function checkpointMap`
- `function performanceIndicatorMap`
- `function collectPerformanceIndicators`
- `function indicatorFieldLabels`
- `function facilityWhereLocal`
- `function streamQuery`
- `function csvRow`
- `function prepareAndBind`
- `function tableExistsLocal`
- `function columnExistsLocal`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

- `certification_history`
- `is`
- `facilities`
- `assessment_action_plan`
- `assessment_master`
- `performance_entries`
- `INFORMATION_SCHEMA`

**Events dispatched**

None detected.

### `api/service/StateUserAdminService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).

**Dependencies included**

- `/StateDashboardService.php`

**Declared classes and functions**

- `class StateUserAdminService`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

### `api/service/ValidationService.php`

- **Role:** Service layer
- **HTTP method guard:** Not detected (internal/helper or legacy handling).
- **Source intent:** ValidationService.php  Performance indicator validation helper.

**Dependencies included**

None detected.

**Declared classes and functions**

- `class ValidationService`
- `function validateEntry`

**Request fields read from `$request`**

None detected.

**Database tables referenced**

None detected.

**Events dispatched**

None detected.

## Configuration files

Configuration files define static behaviour, validation rules, master data, certification settings, and performance settings. They do not expose HTTP endpoints.

### `api/config/certification/certification.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `types`, `assessment_modes`, `statuses`, `national_requires_state`, `allow_multiple_history`, `renewal_due_days`

### `api/config/certification/dashboard.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `cards`

### `api/config/certification/validation.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `required_fields`, `score`

### `api/config/frameworks/sample-framework.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `framework`, `settings`, `facility_types`, `departments`, `areas_of_concern`, `reports`, `dashboard`

### `api/config/frameworks/saqshi-nqas.json`

- **Role:** Configuration
- **JSON shape:** list
- **Top-level keys:** Not applicable or list-based configuration.

### `api/config/masters/assessment_methord.json`

- **Role:** Configuration
- **JSON shape:** list
- **Top-level keys:** Not applicable or list-based configuration.

### `api/config/masters/biharmap.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `type`, `arcs`, `transform`, `objects`, `crs`

### `api/config/masters/departmet.json`

- **Role:** Configuration
- **JSON shape:** list
- **Top-level keys:** Not applicable or list-based configuration.

### `api/config/masters/facilities.json`

- **Role:** Configuration
- **JSON shape:** list
- **Top-level keys:** Not applicable or list-based configuration.

### `api/config/masters/facility_types.json`

- **Role:** Configuration
- **JSON shape:** list
- **Top-level keys:** Not applicable or list-based configuration.

### `api/config/masters/map.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `type`, `arcs`, `transform`, `objects`, `crs`

### `api/config/masters/map_config.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `default_state`, `settings`, `states`

### `api/config/masters/upmap.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `type`, `arcs`, `transform`, `objects`, `crs`

### `api/config/performance/dashboard.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `description`, `route`, `page`, `filters`, `summary_cards`, `widgets`, `status_rules`, `actions`, `api`

### `api/config/performance/formula.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `description`, `variable_model`, `formulas`

### `api/config/performance/frequency.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `frequencies`

### `api/config/performance/indicator.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `description`, `sources`, `indicators`

### `api/config/performance/kpi.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `kpis`

### `api/config/performance/outcome.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `description`, `input_model`, `facility_type_count`, `indicator_total`, `facility_types`

### `api/config/performance/rules.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `description`, `default_rule`, `facility_type_rules`

### `api/config/performance/target.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `targets`

### `api/config/performance/validation.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `module`, `config_type`, `version`, `description`, `scope`, `global_validation`, `field_validation`, `month_year_validation`, `formula_validation`, `save_validation`, `indicator_validation`, `messages`

### `api/postman/collections/SaQshi API Testing Collection.postman_collection.json`

- **Role:** Configuration
- **JSON shape:** object
- **Top-level keys:** `info`, `item`, `variable`

### `api/sql/legacy_sql_notes.sql`

- **Role:** Configuration
- **JSON shape:** invalid JSON
- **Top-level keys:** Not applicable or list-based configuration.

## Maintaining this reference

After modifying an API file, service, core class or JSON configuration, run `php tools/generate-api-documentation.php`, review the generated diff, and update the relevant hand-written module guide with business rules, response examples and extension notes.
