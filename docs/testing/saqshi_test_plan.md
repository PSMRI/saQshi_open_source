# SaQshi Test Plan and Test Cases

Version: 1.0  
Updated: 2026-07-13  
Scope: Assessment, CQI, Performance Monitoring, Certification, Reports, State/Regional/District/Block Monitoring, Security and Documentation

Related VAPT documents:

- `docs/testing/saqshi_vapt_report.md`
- `docs/testing/saqshi_vapt_test_cases.csv`

## 1. Objective

This document defines the test cases required before release or demo of SaQshi. It covers functional, API, UI, role-based access, security, data validation, reporting, regression and performance test areas.

## 2. Test Roles

| Role | Role ID | Scope |
|---|---:|---|
| Facility user | Facility role | Own facility only |
| District monitoring user | 4 | Assigned district only |
| Regional / Division monitoring user | 5 | Assigned division only |
| Block monitoring user | 8 | Assigned block only |
| State monitoring user | 9 | Full configured state data |

## 3. Test Data Prerequisites

| Data | Required condition |
|---|---|
| Facility master | Facility exists in `api/config/masters/facilities.json` and/or `facilities` table |
| Facility user | Active user mapped to facility |
| Monitoring users | Active users with correct `role_id_fk`, `division_id`, `dist_id` or `block_id` |
| Assessment | At least one active assessment and one completed/cancelled assessment |
| Department status | At least one active and one inactive department |
| Checklist response | Responses with score 0, 1 and 2 |
| Action plan | At least one open, completed and overdue action plan |
| Performance | KPI and Outcome entries for at least one month |
| Certification | Certification history rows with conditional/certified status |

## 4. Severity and Priority

| Level | Meaning |
|---|---|
| P0 | Login, data corruption, wrong role scope, report download broken |
| P1 | Main workflow broken: assessment, checklist, CQI, performance, certification |
| P2 | UI issue, validation issue, missing message, pagination/search problem |
| P3 | Cosmetic, text, spacing, minor documentation issue |

## 5. Functional Test Cases

### Authentication and Session

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| AUTH-001 | Valid login | Login with active user and correct captcha | User reaches dashboard | P0 |
| AUTH-002 | Invalid password | Login with wrong password | Friendly invalid login message | P0 |
| AUTH-003 | Inactive user | Login with inactive user | Login blocked with friendly message | P0 |
| AUTH-004 | Captcha required | Submit empty/invalid captcha | Captcha validation error | P1 |
| AUTH-005 | Password not plain in request | Inspect browser network request | Raw password must not appear; encrypted payload is sent | P0 |
| AUTH-006 | Session expired | Simulate expired session and call protected API | Unauthorized response and redirect to login | P1 |
| AUTH-007 | Logout | Click logout | Session cleared and login page opens | P1 |

### Facility Assessment

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| ASM-001 | Create assessment when no active exists | Open Create Assessment, enter valid details, save | Assessment is created and active | P0 |
| ASM-002 | Prevent duplicate active assessment | Try creating new assessment while active exists | Page blocks create and shows active assessment message | P0 |
| ASM-003 | Cancel current assessment | Cancel active assessment and create new | Old assessment becomes cancelled; new can be created | P1 |
| ASM-004 | List assessments | Open Assessment List | Active, completed and cancelled assessments show correctly | P1 |
| ASM-005 | Continue assessment route | Click Continue Assessment | Checklist page opens, not department page | P1 |
| ASM-006 | Browser refresh route | Refresh on assessment subpage | Page reloads without 404 | P0 |

### Department Activation

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| DEP-001 | Load departments | Open Departments page | Departments load with active/inactive status | P0 |
| DEP-002 | Activate department | Activate inactive department | Status changes to Activated | P1 |
| DEP-003 | Lock activated button | View activated department | Activate button disabled/locked | P1 |
| DEP-004 | API source | Verify API calls | Uses `assessment/v1/department-status/list.php` and `save.php` | P1 |

### Assessor Information

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| ASSR-001 | Menu visible after activation | Login facility user | Assessor Info appears in sidebar | P1 |
| ASSR-002 | Save assessor info | Select activated department and save details | Details saved successfully | P1 |
| ASSR-003 | Existing details show | Reopen saved department | Saved details prefill | P1 |
| ASSR-004 | Validation | Save without required fields | Required field messages show | P2 |

### Checklist

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| CHK-001 | Load scope filters | Select department, area, subtype | Checkpoint loads | P0 |
| CHK-002 | Optional assessment method | Leave method blank if not available | Checkpoint still loads | P1 |
| CHK-003 | Save score 0 | Select score 0 and next | Response saved as non-compliance | P0 |
| CHK-004 | Save score 1 | Select score 1 and next | Response saved as partial compliance | P0 |
| CHK-005 | Save score 2 | Select score 2 and next | Response saved as full compliance | P0 |
| CHK-006 | Back and update | Go back and change response | Updated score saved | P1 |
| CHK-007 | Completed area message | Complete all checkpoints in selected area | Clear completed message with edit/update option | P1 |
| CHK-008 | Progress count | Save responses | Completed/remaining count updates | P1 |

### CQI: Gap Analysis, Action Plan, Closure

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| CQI-001 | Gap analysis list | Open gap analysis after checklist | Score 0/1 checkpoints show | P0 |
| CQI-002 | Action plan suggested text | Open action plan page | Predefined action plan appears | P1 |
| CQI-003 | User action plan save | Enter custom action plan and save | Custom plan saved and shown in future | P1 |
| CQI-004 | Responsible person/target date | Fill owner and date | Values saved | P1 |
| CQI-005 | Optional evidence upload | Upload image/PDF/doc/xls | File accepted and linked | P1 |
| CQI-006 | Evidence delete | Delete wrong uploaded file | File removed and UI updates | P1 |
| CQI-007 | Closure revised score | Close gap with revised score | Closure status and revised score saved | P0 |
| CQI-008 | Completed action plan message | Complete all selected action plans | Completed message with edit/update option | P1 |

### Performance Monitoring

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| PERF-001 | KPI entry allowed | Facility type with KPI applicable opens KPI | KPI indicator loads one by one | P1 |
| PERF-002 | Outcome entry | Select department and month | Outcome indicators load one by one | P1 |
| PERF-003 | Date picker | Select month from calendar | Month/year captured correctly | P2 |
| PERF-004 | Denominator N/A | Indicator has denominator label N/A | Denominator field is read-only | P1 |
| PERF-005 | Result calculation | Enter numerator/denominator | Result calculates correctly | P1 |
| PERF-006 | All entered message | Complete all indicators for month | Already entered message with edit/update option | P1 |
| PERF-007 | Outcome treated as KPI | Facility type configured outcome-as-KPI | KPI page blocked if not applicable; reports reflect rule | P1 |
| PERF-008 | Dashboard trend | Open performance dashboard | Month-wise KPI/Outcome trend/chart options load | P2 |
| PERF-009 | Trend report download | Download performance trend | Excel contains facility, indicators and month-wise values | P1 |

### Certification

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| CERT-001 | Add certification | Enter facility certification details | Certification history saved | P0 |
| CERT-002 | Update certification | Update status/type/date/score | New history row/updated status visible | P1 |
| CERT-003 | Validity calculation | Save certification date/status | Valid from/to and renewal status calculate | P1 |
| CERT-004 | Certification status page | Open state certification page | Facility certification list loads with pagination | P1 |
| CERT-005 | Certification map | Open map | Certified facilities plotted from coordinates | P2 |
| CERT-006 | Scoped map | Login district/block user | Map shows only assigned scope | P1 |

### Reports

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| RPT-001 | Scorecard download | Download checklist scorecard | Excel uses live response data, not template sample data | P0 |
| RPT-002 | Full checklist rows | Download scorecard | Full checklist appears, standards highlighted | P1 |
| RPT-003 | Revised score | Close gaps and download | Revised score appears where available | P1 |
| RPT-004 | Progress report | Download progress report | Includes checkpoint, action plan, responsible person, remarks/status | P1 |
| RPT-005 | State reports | Download facilities/assessment/CQI/performance/certification reports | CSV downloads successfully | P1 |
| RPT-006 | Scoped reports | Login district/block/regional user and download | Download includes only assigned scope | P0 |

### State / Regional / District / Block Monitoring

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| MON-001 | Role 9 state login | Login role 9 | Sidebar shows State Monitoring and full state dashboard | P0 |
| MON-002 | Role 5 regional login | Login role 5 with division_id | Sidebar shows Regional Monitoring and data scoped to division | P0 |
| MON-003 | Role 4 district login | Login role 4 with dist_id | Sidebar shows District Monitoring and data scoped to district | P0 |
| MON-004 | Role 8 block login | Login role 8 with block_id | Sidebar shows Block Monitoring and data scoped to block | P0 |
| MON-005 | Facility menus hidden | Login monitoring user | Facility assessment/CQI/performance entry menus hidden | P1 |
| MON-006 | Dashboard cards | Open monitoring dashboard | Facility count and assessment started/in-progress/completed show for scope | P1 |
| MON-007 | Facility categorization | Open page and expand district/block rows | Counts match scoped facilities | P1 |
| MON-008 | Assessment progress | Open page | Shows all assessments for scoped facilities, latest marked | P1 |
| MON-009 | CQI monitoring | Open page | Shows facilities with action plans, completed/pending/overdue | P1 |
| MON-010 | Performance monitoring | Open page | Shows facility/dist/block/type and months filled for scoped data | P1 |
| MON-011 | Indicator analytics | Open page | Shows checklist indicators with maximum zero scores and pagination | P1 |
| MON-012 | User administration | Search and activate/deactivate user | User status updates and list paginates | P1 |

## 6. API Test Cases

| ID | Endpoint area | Test | Expected result |
|---|---|---|---|
| API-001 | Auth | Login with valid/invalid payloads | Correct success/error JSON |
| API-002 | Assessment | Create/list/active/cancel/complete | Status codes and JSON schema correct |
| API-003 | Department status | List/save active status | Status persisted |
| API-004 | Checklist response | Save 0/1/2 score | Score and response stored |
| API-005 | Action plan | Save/update/closure | Status and revised score stored |
| API-006 | File upload | Upload/delete allowed file types | File metadata and deletion success |
| API-007 | Performance | KPI/outcome list/save/trend | Month-wise data stored and retrieved |
| API-008 | Certification | Save/update/history | History row and summary update |
| API-009 | State scope | Call `api/state/v1/*` as role 4/5/8/9 | Response data restricted by role |
| API-010 | Friendly errors | Force validation/DB error | No raw SQL/PHP details in response |

## 7. UI and Usability Test Cases

| ID | Test case | Expected result |
|---|---|---|
| UI-001 | Browser refresh on every route | No 404; route reloads |
| UI-002 | Sidebar role visibility | Correct menu for facility vs monitoring users |
| UI-003 | Compact layout | Main cards fit normal 100% browser view where practical |
| UI-004 | Dark/light theme | Text and buttons remain readable |
| UI-005 | Pagination | Tables show pager and do not render thousands of rows at once |
| UI-006 | Search | Facility/NIN search filters data correctly |
| UI-007 | Friendly empty states | No data pages show useful message |
| UI-008 | Floating chat | Only one floating chat button appears |

## 8. Security Test Cases

| ID | Test case | Expected result |
|---|---|---|
| SEC-001 | `.env` secret handling | DB credentials are not committed in public config |
| SEC-002 | Password hashing | Plain password is hashed on first login/update |
| SEC-003 | Profile encryption | Name, mobile and email are encrypted where configured |
| SEC-004 | CSRF | POST/PUT/PATCH/DELETE without CSRF blocked |
| SEC-005 | Role access | Facility user cannot call state APIs |
| SEC-006 | Monitoring scope | District/block/regional user cannot access out-of-scope data |
| SEC-007 | Upload validation | Disallowed file types rejected |
| SEC-008 | Error sanitization | SQL/PHP internal errors not shown to user |

## 9. Regression Checklist

Run this after each major change:

1. Login as facility user.
2. Create or continue assessment.
3. Activate department.
4. Save assessor info.
5. Save checklist responses 0, 1 and 2.
6. Open gap analysis.
7. Save action plan.
8. Upload and delete evidence.
9. Close gap and revise score.
10. Download scorecard and progress report.
11. Save KPI/Outcome month.
12. Add/update certification.
13. Login as role 9, 5, 4 and 8.
14. Verify dashboard, certification, assessment progress, CQI, performance and reports are scoped.
15. Refresh browser on important routes.

## 10. Release Acceptance Criteria

| Area | Pass condition |
|---|---|
| Functional | P0 and P1 test cases pass |
| Role scope | State/regional/district/block data restrictions verified |
| Reports | Downloads use live data and correct scope |
| Security | No raw password, DB credential, SQL or PHP error exposure |
| UI | Main pages load without 404 and without broken layout |
| Performance | Large table pages use pagination/search |

## 11. Additional Non-Functional Test Cases

### Accessibility

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| ACC-001 | Keyboard navigation | Use Tab/Shift+Tab through login, sidebar, forms and modals | Focus order is logical and all controls reachable | P2 |
| ACC-002 | Visible focus state | Tab through buttons, inputs, links and table actions | Focus ring/active state is visible | P2 |
| ACC-003 | Form labels | Inspect login, checklist, action plan, performance and certification forms | Inputs have visible label or accessible label | P2 |
| ACC-004 | Color contrast | Check light and dark theme text/buttons | Text and button contrast remains readable | P2 |
| ACC-005 | Screen reader names | Inspect icon-only buttons and refresh/action buttons | Buttons have meaningful accessible text/title | P2 |

### Browser and Device Compatibility

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| COMP-001 | Chrome latest | Run major facility and monitoring flows | Pages load and actions work | P1 |
| COMP-002 | Edge latest | Run login, dashboard, checklist, reports | Pages load and actions work | P1 |
| COMP-003 | Firefox latest | Run login, dashboard, checklist, reports | Pages load and actions work | P2 |
| COMP-004 | 1366x768 desktop | Open dashboard/checklist/state pages at 100% zoom | Layout is compact and usable | P2 |
| COMP-005 | Mobile width | Open sidebar and key forms under 480px width | No overlapping text; navigation usable | P2 |

### Performance and Load Readiness

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| LOAD-001 | State dashboard response time | Call dashboard API with state user | Response completes within accepted local threshold | P1 |
| LOAD-002 | Pagination under large data | Open certification, assessment, CQI, performance and indicator analytics lists | Data renders page-wise, not all rows at once | P1 |
| LOAD-003 | Search large facility list | Search by facility name/NIN | Results return without browser freeze | P1 |
| LOAD-004 | Report download size | Download large scoped reports | File downloads successfully without PHP timeout | P1 |
| LOAD-005 | Map load | Open certification map with configured boundary | Map initializes and markers render without repeated reload failure | P2 |

### Data Integrity and Concurrency

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| DATA-001 | Duplicate NIN prevention | Update facility NIN to another facility NIN | Update blocked with duplicate message | P0 |
| DATA-002 | Concurrent checklist update | Two sessions update same checkpoint | Latest save is controlled and no duplicate response row corruption | P1 |
| DATA-003 | Concurrent action plan update | Two sessions update same plan | Saved data remains consistent | P1 |
| DATA-004 | Score recalculation | Save responses and revised score | Dashboard/report score updates from latest data | P0 |
| DATA-005 | Certification history | Update certification multiple times | History records old/new data and latest status shows correctly | P1 |

### Backup, Restore and Deployment

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| DEPLOY-001 | Fresh environment setup | Copy code, create `.env`, configure DB | App boots without exposing secrets | P0 |
| DEPLOY-002 | Missing `.env` | Temporarily remove `.env` in test environment | Friendly configuration error; no raw password/path shown | P1 |
| DEPLOY-003 | Upload folder permissions | Remove upload write permission in test environment | Friendly upload error; no PHP warning shown | P1 |
| DEPLOY-004 | Event log write | Trigger login/dashboard/checklist event | Event log is written or gracefully skipped | P2 |
| DEPLOY-005 | Restore DB backup | Restore test DB backup and login | Application runs with restored data | P1 |

### Audit and Event Logging

| ID | Test case | Steps | Expected result | Priority |
|---|---|---|---|---|
| AUD-001 | API request event | Call any API | `api.request.started` and `api.request.finished` logged where enabled | P2 |
| AUD-002 | Auth event | Attempt login success/failure | Login events recorded without password/captcha values | P1 |
| AUD-003 | Assessment event | Create/complete assessment | Assessment event recorded with safe metadata | P2 |
| AUD-004 | Certification event | Update certification | Certification event recorded with safe metadata | P2 |
| AUD-005 | Sensitive data redaction | Inspect logs after auth/profile actions | Password, captcha, password_enc and secrets are not logged | P0 |

## 12. Safe Local Checks Performed

The following checks were safe and non-destructive:

| Check | Result |
|---|---|
| Created full functional test plan | Completed |
| Created VAPT report and VAPT test CSV | Completed |
| Disabled legacy raw-password login endpoint | Completed |
| Verified `.env` is ignored by git | Completed |
| Verified API error handling uses friendly messages/request IDs | Completed |
| Verified security headers helper exists | Completed |
| Verified upload/delete code has extension, MIME and uploads-path checks | Completed |

Active load testing, destructive authorization bypass attempts, DB restore drills and scanner-based VAPT are not performed in this workspace. They should be executed only on a dedicated test environment.
