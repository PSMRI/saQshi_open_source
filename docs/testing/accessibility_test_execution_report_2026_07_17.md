# Accessibility Test Execution Report

Date: 2026-07-18  
Test base URL: `{main_url}`

Follow-up revalidation: 2026-08-26

## Scope Requested

| Test Area | Execution Status |
| --- | --- |
| Keyboard-only testing for every workflow | Partially executed through static/live checks; full keyboard traversal needs browser automation or manual browser session. |
| Screen-reader testing with NVDA/JAWS/VoiceOver | NVDA manually executed and passed for every result captured in the authenticated test run. JAWS and VoiceOver remain pending. |
| Color contrast testing in light and dark themes | Executed for key theme color pairs using `scripts/accessibility/contrast-check.js`. |
| Zoom/reflow testing at 200% and small screens | Not fully executable without browser automation; manual test procedure documented. |
| Error handling and status message testing | Partially executed through live captcha/API smoke and static ARIA/live-region checks. Full workflow validation needs authenticated test users. |
| Dynamic components after data loads | Partially mitigated and checked through runtime helper presence; full runtime scan needs Playwright/axe or manual browser checks. |

## Automated Tests Executed

### Static Accessibility Audit

Command:

```bash
node scripts/accessibility/a11y-static-check.js
```

Result:

```text
47 files checked
47 passed
0 review
0 needs fix
0 errors
0 warnings
```

Evidence file:

```text
docs/testing/wcag_static_audit_results.json
```

### Light/Dark Contrast Check

Command:

```bash
node scripts/accessibility/contrast-check.js
```

Result:

```text
PASS Light body text: 16.61:1
PASS Light surface text: 17.85:1
PASS Light secondary text: 10.35:1
PASS Light primary button: 5.17:1
PASS Light input text: 17.85:1
PASS Dark body text: 17.89:1
PASS Dark surface text: 15.55:1
PASS Dark secondary text: 12.68:1
PASS Dark primary button: 7.36:1
PASS Dark input text: 17.06:1
PASS Dark muted text: 7.91:1
```

### Live URL Accessibility Smoke

Command:

```bash
node scripts/accessibility/live-a11y-smoke.js {main_url}
```

Checks:

- Login shell reachable.
- Login shell has `lang="en"`.
- Login shell loads runtime accessibility helper.
- Login fragment reachable.
- Captcha has help text, `aria-describedby` and `aria-live`.
- Dashboard shell reachable.
- Dashboard shell has `lang="en"`.
- Dashboard shell loads runtime accessibility helper.
- Captcha API reachable.
- Captcha API returns a text math question.

Evidence file:

```text
docs/testing/wcag_live_smoke_results.json
```

## Manual Screen-reader Test Executed: NVDA

**Status: Pass for all captured results.** The test was run in Firefox with an
authenticated SaQshi session and NVDA Speech Viewer. The following controls and
content were announced with usable names, roles, states and status feedback:

| Flow | Result |
| --- | --- |
| Login | Username, password, show/hide password, refresh, remember-me, forgot-password and login controls were announced. |
| Login captcha | The text-math question, required/invalid state and screen-reader assistance text were announced; the numeric answer was entered successfully. |
| Authenticated shell and navigation | Skip link, banner, global search, header controls, accessibility controls, sidebar landmarks, current-page state and navigation links were announced. |
| Assessment list | Status filter, refresh button, facility-assessments table, score and Continue action were announced. |
| Checklist scope | Department, concern, subtype and method combo boxes, their selected values and Load Checkpoint button were announced. |
| Checklist response | Checkpoint heading/context, compliance-score grouping, radio-button checked states, Back, Save / Update and Next controls were announced. |
| Save feedback | The successful response-save notification was announced. |

The transcript is retained in the execution record supplied on 2026-07-18. This
is a successful NVDA validation of the tested login and authenticated assessment
flows. It does not substitute for testing pages not included in the run, or for
JAWS and VoiceOver validation.

## Manual Tests Still Required

These checks cannot be honestly completed by static scripts alone.

### Keyboard-only Workflow Test

For each role and major workflow:

1. Open the page.
2. Use only `Tab`, `Shift + Tab`, `Enter`, arrow keys and `Esc`.
3. Confirm focus is visible.
4. Confirm focus order follows the visual workflow.
5. Confirm modals trap focus and close with `Esc`.
6. Confirm forms can be completed and submitted.
7. Confirm validation errors are reachable by keyboard.

Workflows to cover:

- Login and captcha.
- Facility dashboard.
- Assessment create/list/departments/assessor/checklist.
- CQI gap analysis/action plan/closure.
- Performance KPI/outcome/trend/dashboard.
- Reports and downloads.
- State dashboard/map/certification/category/progress/CQI/performance/drill-down/users/reports.
- Facility user profile and facility profile.

### Remaining Screen-reader Test

Complete the remaining coverage with:

- JAWS on Windows if available.
- VoiceOver on macOS.

For NVDA, extend the passed coverage to the remaining workflows, including CQI,
performance, reports, certification and state-monitoring pages.

Minimum flow:

1. Start screen reader.
2. Open `{main_url}/ui/login.html`.
3. Confirm page title and username/password/captcha fields are announced.
4. Confirm captcha question is announced as text.
5. Login.
6. Enable SaQshi screen-reader mode from the authenticated header.
7. Navigate dashboard, sidebar, forms, data tables, charts and map.
8. Confirm status/error messages are announced.
9. Confirm chart/map pages provide equivalent text/table summaries.

### 200% Zoom and Small Screen Test

Manual browser checks:

1. Set browser zoom to 200%.
2. Confirm no horizontal scrolling is required for normal content.
3. Confirm buttons and inputs remain usable.
4. Test width around 320px, 375px, 768px and desktop.
5. Confirm header/sidebar/content do not overlap.
6. Confirm modals and dropdowns fit within viewport.

### Dynamic Component Test After Data Loads

Use a real authenticated session with data:

1. Load each page.
2. Wait for API data to render.
3. Inspect controls created after API response.
4. Confirm dynamic buttons/links/inputs/tables have accessible names.
5. Confirm pagination, filters, charts, map markers and expanded rows are keyboard usable.
6. Confirm `SQ.a11y.enhance()` has applied labels where needed.

## Follow-up Revalidation: Public Landing and GitBook (2026-08-26)

This follow-up covered the public pages changed after the original July
execution. It is a focused availability and source-level check; it is not a
replacement for the manual browser and screen-reader tests listed above.

| Check | Result | Status |
|---|---|---|
| Healthcare public root | `GET /` returned HTTP 200 and served the profile-aware healthcare landing page. | Passed |
| Digital hero alternative text | The tablet-based healthcare assessment hero image has descriptive alternative text. | Passed |
| GitBook reader availability | `GET /gitbook.html` returned HTTP 200. | Passed |
| GitBook test-document sources | The testing CSV and Markdown sources returned HTTP 200. | Passed for source availability |
| Login shell | `GET /ui/login.html` returned HTTP 200. | Passed |
| Captcha helper | `GET /api/auth/v1/captcha.php` returned an accessible text-math question. | Passed |

### Automated Rerun Limitation

The static and live accessibility scripts were started during this follow-up,
but their existing evidence files were locked by another local process:

```text
docs/testing/wcag_static_audit_results.json
docs/testing/wcag_live_smoke_results.json
```

The scripts therefore could not save replacement JSON reports. Unlock those
files and rerun the static audit and live smoke scripts before recording a new
automated-result count.

### Additional Manual Tests Required

Add the following to the manual accessibility run:

1. Test healthcare and education profile landing pages with keyboard only.
2. At 200% zoom and at mobile widths, confirm the hero image, header actions
   and back-to-top control remain usable without overlapping content.
3. Use NVDA, JAWS and VoiceOver to confirm the landing hero alternative text
   is concise and that the sign-in/learn links have a logical reading order.
4. Test GitBook navigation groups, document links and CSV tables with keyboard
   and screen reader, including visible focus and table headers.

## Conclusion

Automated and live smoke checks passed for the parts executable in this environment. NVDA manual validation also passed for every captured result in the login and authenticated assessment flows. The 2026-08-26 follow-up confirmed availability of the updated public landing and GitBook sources plus descriptive alternative text for the new digital hero. SaQshi is improved and test-ready, but a formal WCAG conformance claim still requires full workflow coverage for keyboard, screen readers, zoom/reflow and dynamic-data testing, including JAWS and VoiceOver.
