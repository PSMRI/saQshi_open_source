# SaQshi WCAG 2.2 and Web Platform Design Principles Review

Version: 1.2  
Updated: 2026-07-18

## Compliance Status

SaQshi is **code-level WCAG 2.2 aligned for the tested static checks** and actively aligned with relevant W3C Web Platform Design Principles. NVDA testing has passed for every result captured in the authenticated login and assessment-flow run. A full legal/formal WCAG conformance claim is **not yet made** because WCAG conformance still needs complete human keyboard testing, coverage of all workflows and contrast verification on all light/dark theme states.

Latest static audit result:

| Metric | Result |
|---|---:|
| HTML files checked | 47 |
| Passed | 47 |
| Needs fix | 0 |
| Errors | 0 |
| Warnings | 0 |

Result file:

```text
docs/testing/wcag_static_audit_results.json
```

## References

- WCAG 2.2: `https://www.w3.org/TR/WCAG22/`
- Web Platform Design Principles: `https://www.w3.org/TR/design-principles/`

## Why We Cannot Claim Full Compliance Yet

WCAG 2.2 success criteria are testable statements and need both automated and human evaluation. SaQshi is a growing application with many dynamic pages, generated tables, charts, maps, modals, uploads and report downloads. Because of that, full compliance requires:

- Keyboard-only testing for every workflow.
- Screen-reader testing across all remaining workflows and with JAWS/VoiceOver.
- Color contrast testing in light and dark themes.
- Zoom/reflow testing at 200% and small screens.
- Error handling and status message testing.
- Testing of all dynamic components after data loads.

## Rectifications Applied

| ID | Area | Issue | Fix | Status |
|---|---|---|---|---|
| A11Y-001 | Keyboard navigation | Authenticated layout did not provide a bypass/skip link to jump past header/sidebar navigation. | Added `Skip to main content` link in `ui/layouts/dashboard.html`. | Rectified |
| A11Y-002 | Focus target | Dynamic content region could not receive focus after skip link activation. | Added `tabindex="-1"` to `#sq-page-content`. | Rectified |
| A11Y-003 | Focus visibility | Skip link needed visible styling when focused. | Added `.sq-skip-link` styles in `ui/assets/css/sq-ui.css`. | Rectified |
| A11Y-004 | Language of generated document | Performance trend export generated `<html>` without `lang`. | Changed generated export HTML to `<html lang="en">`. | Rectified |
| A11Y-005 | Text resizing | User requested A-- to A++ text controls for accessibility. | Added global header accessibility menu with `A--`, `A`, `A+`, `A++`. | Rectified |
| A11Y-006 | Screen-reader support | User requested screen reader support mode. | Added global screen-reader mode toggle with stronger focus ring, underlined links and higher contrast borders. | Rectified |
| A11Y-007 | Form controls | Static audit found controls without explicit accessible names. | Added `aria-label` to missing inputs, selects and textareas across 30 UI page files. | Rectified |
| A11Y-008 | Page heading | Dashboard fragment had no local heading. | Added screen-reader-only `Dashboard Overview` heading. | Rectified |
| A11Y-009 | Testing tool | No local page-by-page accessibility static checker existed. | Added `scripts/accessibility/a11y-static-check.js`. | Rectified |
| A11Y-010 | Repeatable label fixer | Missing control labels needed repeatable remediation. | Added `scripts/accessibility/a11y-auto-label-controls.js`. | Rectified |
| A11Y-011 | Built-in speech | User expected screen-reader mode to produce sound. | Added browser Web Speech API controls: `Read page`, `Stop`, and voice confirmation where supported. | Rectified |
| A11Y-012 | Automatic page speech | User expected screen-reader mode to start voicing automatically after changing pages. | Added `sq:page-ready` route event and automatic page speech when screen-reader mode is enabled. | Rectified |
| A11Y-013 | Captcha accessibility | Login captcha needed clearer screen-reader instructions and fallback route. | Captcha remains text-based math, question is announced with `aria-live`, input uses `aria-describedby`, and assisted-login guidance is shown. | Rectified |
| A11Y-014 | Runtime JS controls | Static audit cannot see controls created after data load. | Added `ui/assets/js/core/a11y.js` to label dynamic buttons, links, fields and tables after page load and route changes. | Rectified |
| A11Y-015 | Chart/map alternatives | Trend charts and certification map needed non-visual summaries. | Added screen-reader trend summaries for performance charts and visible map summary plus mapped-facility table caption. | Rectified |
| A11Y-016 | Target size | Compact UI reduced some button/control hit areas. | Added global target-size guard for normal buttons and form controls while allowing explicit compact controls only where intentionally marked. | Rectified |
| A11Y-017 | Dark-theme contrast | Dark-theme contrast needed repeatable verification. | Added `scripts/accessibility/contrast-check.js` for key light/dark theme color pairs and strengthened existing dark-theme contrast guard. | Rectified |
| A11Y-018 | NVDA validation | Manual NVDA evidence was pending. | Validated login/captcha, authenticated navigation, assessment list and checklist response flows with NVDA Speech Viewer; all captured results passed. | Passed for tested flows |

## Current Positive Controls Observed

| WCAG Area | SaQshi Implementation |
|---|---|
| 1.3 Info and Relationships | Many pages use semantic headings, tables, labels and grouped cards. |
| 1.4 Distinguishable | Theme variables are centralized; dark-theme button/input contrast has been improved and key color pairs are covered by a lightweight contrast script. |
| 2.1 Keyboard Accessible | Native buttons, links, inputs and selects are used widely; global focus styles exist. |
| 2.4 Navigable | Page titles, breadcrumb, sidebar navigation and now skip link are present. |
| 2.4.7 Focus Visible | Global `:focus-visible` styles and component-specific focus styles exist. |
| 2.5.8 Target Size Minimum | Shared buttons and form controls have a practical target-size guard; explicitly compact controls should be used sparingly. |
| 3.1.1 Language of Page | Main shell pages use `lang="en"`. |
| 3.3 Input Assistance | Forms include labels/instructions and validation messages in major workflows. |
| 4.1 Name, Role, Value | Key components use ARIA roles/labels: navigation, dialogs, notifications, chat, loader. |
| 4.1.3 Status Messages | Loader/notification/chat areas use `aria-live` in shared components. |

## Web Platform Design Principles Alignment

| Principle Area | SaQshi Status |
|---|---|
| User needs and usability | UI is workflow-first: assessment, checklist, CQI, reports and monitoring are direct pages, not marketing screens. |
| Secure contexts for powerful features | Location/geocoordinate features should be used only after user action and should be HTTPS in production. |
| Do not reveal assistive technology use | No code path intentionally detects screen readers or assistive technology. |
| Graceful feature handling | Map/chart/chat features show fallback or friendly failure messages where implemented; chart/map data also has text/table summaries. |
| Human-readable formats | API docs, Postman collection, OpenAPI, JSON configuration and test docs are readable and versioned. |

## WCAG 2.2 Review Checklist

| ID | Criterion Area | Test | Current Status |
|---|---|---|---|
| WCAG-001 | Language of Page | Check every top-level HTML page has `lang`. | Mostly Pass; dynamic export fixed |
| WCAG-002 | Page Titled | Check every route has title/header. | Mostly Pass |
| WCAG-003 | Keyboard | Use Tab/Shift+Tab/Enter/Escape on every page. | Needs full manual test |
| WCAG-004 | Bypass Blocks | Skip link appears on keyboard focus. | Rectified |
| WCAG-005 | Focus Visible | Focus ring visible for links, buttons, inputs, sidebar, modals. | Mostly Pass |
| WCAG-006 | Labels or Instructions | Every input/select/textarea has visible label or accessible name. | Needs page-by-page audit |
| WCAG-007 | Error Identification | Invalid inputs show clear messages. | Mostly Pass; needs workflow test |
| WCAG-008 | Contrast Minimum | Light and dark theme text/buttons pass contrast. | Improved; key color pairs covered by contrast script |
| WCAG-009 | Reflow | Pages work at 200% zoom and responsive widths. | Needs browser test |
| WCAG-010 | Non-text Content | Icons/charts/maps have accessible names or text alternatives. | Improved; charts/maps now include text summaries/tables |
| WCAG-011 | Status Messages | Loading, success, errors announced without focus stealing. | Mostly Pass |
| WCAG-012 | Target Size Minimum | Buttons and controls have practical target sizes. | Improved; global guard added |
| WCAG-013 | Accessible Authentication | Login uses text math captcha with screen-reader instructions and assisted-login fallback. | NVDA passed for the captured login/captcha result; retain JAWS/VoiceOver and broader workflow validation |

## Accessibility Controls Added

The authenticated header now includes an accessibility menu:

| Control | Purpose |
|---|---|
| `A--` | Smaller text |
| `A` | Normal text |
| `A+` | Larger text |
| `A++` | Extra large text |
| `Screen reader mode` | Stronger focus indicator, underlined links, improved border contrast, relaxed line-height, and automatic page speech after navigation |
| `Read page` | Uses browser speech synthesis to read the current page title/subtitle/content aloud |
| `Stop` | Stops current browser speech |

### Login Page Screen Reader Use

The login page is intentionally shown before authentication and does not load
the authenticated header. Because of this, the SaQshi header accessibility menu
is available after login, not on the login screen itself.

For the login page, users should use their operating-system or browser screen
reader:

- Windows Narrator: `Ctrl + Windows + Enter`.
- NVDA or JAWS: start the screen reader before opening `{main_url}/ui/login.html`.
- macOS VoiceOver: `Command + F5`.

The login captcha is text-based, not image-only. The captcha question is placed
in an `aria-live` region and the input is connected to help text with
`aria-describedby`. If a user cannot complete the captcha independently, the
deployment should provide an assisted-login or password-reset process through
the administrator.

After login, users can open the top header accessibility menu and enable
`Screen reader mode`, `Read page`, `Stop`, and text-size controls. These
settings are persisted for authenticated pages in the same browser.

Settings are persisted through `SQ.storage` and applied to the root document:

```text
data-font-size="small|normal|large|xlarge"
data-screen-reader-mode="true|false"
```

Files:

```text
ui/assets/js/core/router.js
ui/assets/js/core/a11y.js
ui/components/header/header.html
ui/components/header/header.js
ui/components/header/header.css
ui/assets/css/sq-ui.css
scripts/accessibility/contrast-check.js
```

Important distinction:

- External screen readers such as NVDA, JAWS and VoiceOver read the page through semantic HTML, ARIA labels and focus order.
- SaQshi's automatic page speech and `Read page` button are additional browser speech helpers using the Web Speech API.
- Speech availability depends on the browser, operating system voices and sound settings.

## Page-by-Page Static Audit Result

The static checker scanned all HTML pages/components/layouts under `ui/pages`, `ui/components`, `ui/layouts` and `ui/help`.

| Page/Area | Result |
|---|---|
| Assessment: Assessor Info | Pass |
| Assessment: Checklist | Pass |
| Assessment: Create | Pass |
| Assessment: Departments | Pass |
| Assessment: List | Pass |
| Certification: Dashboard | Pass |
| Certification: History | Pass |
| Certification: Manage | Pass |
| Certification: Renewal | Pass |
| CQI: Action Plan | Pass |
| CQI: Closure | Pass |
| CQI: Gap Analysis | Pass |
| Dashboard | Pass |
| Facility User: Facilities | Pass |
| Facility User: Users/Profile | Pass |
| Login | Pass |
| Performance: Dashboard | Pass |
| Performance: Indicator | Pass |
| Performance: KPI | Pass |
| Performance: Outcome | Pass |
| Performance: Trend | Pass |
| Reports: Dashboard | Pass |
| Reports: Progress | Pass |
| Reports: Score | Pass |
| State: Assessment Progress | Pass |
| State: Certification | Pass |
| State: CQI | Pass |
| State: Dashboard | Pass |
| State: Facility Category | Pass |
| State: Facility Detail | Pass |
| State: Indicator Analytics | Pass |
| State: Map | Pass |
| State: Performance | Pass |
| State: Reports | Pass |
| State: Users | Pass |
| Shared Components/Layout/Help | Pass |

## Accessibility Risks After Latest Rectification

| Risk | Impact | Current Fix | Remaining Verification |
|---|---|---|
| Captcha accessibility | Some users may be unable to login independently. | Text math captcha now announces the question, links the input to help text, and tells users to contact admin for assisted login/password reset. | Confirm with NVDA/JAWS/VoiceOver and define operational assisted-login SOP for each deployment. |
| Runtime JS-rendered controls | Static HTML audit may miss controls created after load. | Added runtime helper `SQ.a11y.enhance()` and `sq:page-ready` hook to label dynamic controls/tables. | Add Playwright/axe runtime scans when browser automation is available. |
| Charts/maps | Non-visual users may not understand chart/map content. | Performance charts now generate screen-reader summaries; certification map has visible summary and mapped-facility table/caption. | Manually verify screen-reader reading order on chart/map pages. |
| Compact UI target size | Motor-impaired users may struggle with small buttons. | Normal buttons and form controls now have a global practical target-size guard; compact controls must be explicit. | Manually test dense tables and mobile/touch workflows. |
| Dark-theme contrast | Low-vision users may see insufficient contrast in uncommon states. | Added `contrast-check.js` for key light/dark theme pairs and kept dark-theme CSS contrast guard. | Run full browser contrast audit across hover, disabled, selected and chart/map states. |

## Recommended Manual Test Procedure

1. Login without using a mouse.
2. Use the skip link to jump to main content.
3. Navigate sidebar, header buttons, forms, modals and chat using only keyboard.
4. Confirm visible focus is never hidden behind sticky header/sidebar.
5. Run screen reader through:
   - Login
   - Dashboard
   - Assessment checklist
   - CQI action plan
   - Certification status
   - State monitoring pages
6. Test light and dark themes.
7. Test 200% zoom.
8. Test mobile width.
9. Verify all error messages are announced and readable.
10. Verify report/chart pages provide data in text/table form.

## NVDA Manual Test Evidence (2026-07-18)

**Result: Pass for every captured result in the authenticated run.** NVDA
correctly announced the required fields and captcha guidance on login; the
skip link, landmarks, navigation, header and accessibility controls after
login; assessment-list filters/table/actions; and checklist scope selectors,
checkpoint context, compliance radio states, Save / Update, Next and successful
save feedback.

The evidence applies to these tested flows only. Complete the remaining
application workflows with NVDA, and run JAWS and VoiceOver, before making a
complete screen-reader conformance claim. Detailed evidence is recorded in
`docs/testing/accessibility_test_execution_report_2026_07_17.md`.

## Automated/Static Checks Performed

Executed scans:

```text
rg "<html|aria-label|role=|aria-live|sr-only|focus-visible" ui
rg "outline|focus-visible|:focus|skip|sq-sr-only" ui/assets/css ui/components ui/pages
node scripts/accessibility/a11y-auto-label-controls.js
node scripts/accessibility/a11y-static-check.js
node scripts/accessibility/contrast-check.js
node scripts/accessibility/live-a11y-smoke.js {main_url}
node -c ui/components/header/header.js
node -c ui/pages/performance/trend.js
```

Findings:

- Main shell pages include `lang="en"`.
- Shared components include ARIA labels/roles for navigation, dialogs, loader, chat and notifications.
- Global focus-visible styles exist.
- Screen-reader utility classes exist.
- Skip link was missing and has now been added.
- Generated performance trend export missed `lang` and has now been fixed.
- Missing explicit control names were found and fixed across 30 UI page files.
- Final static audit result: 47 files checked, 47 passed, 0 errors, 0 warnings.
- Built-in speech controls were added to the accessibility menu for browsers that support `speechSynthesis`.
- Live smoke testing confirms login/dashboard shells are reachable on `{main_url}`, captcha is text-based, and runtime accessibility helper is loaded.

Detailed execution evidence:

```text
docs/testing/accessibility_test_execution_report_2026_07_17.md
docs/testing/wcag_live_smoke_results.json
```

## Final Recommendation

Treat SaQshi as **WCAG 2.2 AA-aligned but not formally certified**. NVDA has passed for the tested login and authenticated assessment evidence. Before public/production release, complete the remaining accessibility audit using:

- Browser keyboard-only testing.
- NVDA on the remaining workflows, plus JAWS on Windows and VoiceOver on macOS.
- Lighthouse or axe DevTools.
- Manual contrast testing.
- Screen-reader review of charts/maps/tables.
