# Troubleshooting and FAQ

Version: 1.1
Updated: 2026-08-26

This page lists common SaQshi issues and first checks.

## Page Loading

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Page Load Failed | API returned error or route failed | Open browser console and API response |
| 404 after refresh | Web server routing/static path issue | Confirm direct page path exists |
| Sidebar link not working | Route or page JSON mismatch | Check `ui/pages/.../*.json` and router config |
| GitBook shows raw markdown | MIME or GitBook renderer issue | Confirm page opens through `gitbook.html?doc=...` |
| Root URL opens Login instead of landing page | Web-server default document still points to `ui/login.html` | Set the root default document to `index.html`; do not override profile-aware landing routing. |
| Education landing page does not open | Active profile is not Education, public branding API failed, or browser cached an older page | Confirm `GET /api/config/v1/public_deployment.php` returns `profile_code: education`; hard-refresh only after checking the deployment profile. |
| Healthcare landing page opens when Education was expected | Public branding check is unavailable or the profile is Healthcare | Confirm the active profile in an approved environment. The safe fallback remains the healthcare landing page. |
| GitBook page returns 404 | Document path or reader navigation is wrong | Open the exact repository-relative `gitbook.html?doc=...` URL; update `gitbook.html` and `SUMMARY.md` when appropriate. |

## Login

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Something went wrong | API/db/config error | Record page, time and safe API error reference; an authorised administrator may inspect protected server logs. Do not share raw logs or credentials. |
| Invalid username or password | `s_user.u_name` not found, inactive user, inactive role, wrong password or wrong captcha | Check exact `s_user.u_name`, `is_active`, `role_id_fk`, role status and captcha |
| Assessor cannot login | Assessor profile exists but linked `s_user` row was not created or user is inactive | Check `assessor_master.user_id`, matching `s_user.u_name = assessor_code`, role `Assessor`, `is_active = 1` |
| Captcha not loading | Captcha endpoint or session issue | Open captcha API directly |
| CSRF validation failed | Token missing/expired | Call CSRF API again and retry |
| Role menu wrong | Role mapping issue | Check user role and role status |
| Login works intermittently across IIS workers | Redis/Memurai session configuration or cookie mismatch | Confirm PHP Redis extension, Memurai health, cookie name, host/HTTPS behavior and `api/config/session.json`. Review protected PHP logs for file-session fallback. |
| Repeated logout after HTTPS/proxy change | Secure cookie, hostname or forwarded-HTTPS detection mismatch | Confirm HTTPS reaches PHP/IIS correctly and session cookie has the expected `Secure`, `HttpOnly` and `SameSite=Strict` attributes. |

## Database

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Connection failed | `.env` DB values wrong | Verify DB host/user/password/database |
| Data not visible | Facility/user mapping missing | Check `fac_id_fk`, role and scope |
| Duplicate NIN error | Facility NIN already exists | Search facility by NIN before update |
| Fresh installation starts but users cannot work end-to-end | Approved master/seed data is missing | Confirm active user, facility type, facility rows and profile/framework JSON were installed; do not import production dumps as a shortcut. |

## Assessment and CQI

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| No active assessment | No ACTIVE assessment or wrong facility | Check `assessment_master` for user facility |
| Department not active | Department not activated for assessment | Check department activation page/API |
| Checkpoints not loading | Framework JSON or filters mismatch | Check department, concern, subtype and method |
| Open gaps show zero | Scores are complete or query scope wrong | Check score 0/1 responses |
| Action plan warning | Missing gap/action plan record | Check action plan API response |
| Assessor cannot start assigned work | Assignment inactive/ended, wrong mapped unit, or work area already claimed | Confirm assessor mapping and available Class (Education) or Department/service area (Healthcare); do not bypass through browser request edits. |

## Performance

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| KPI not available | Facility type configured as outcome-only | Check performance JSON configuration |
| Denominator read-only | Denominator label is N/A | Expected behavior |
| Result not calculating | Formula or variable mapping issue | Check formula JSON |

## State Monitoring

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Counts show zero | Scope filter or source data mismatch | Check role scope and facility mapping |
| Certification status blank | Certification history not joined by NIN/facility | Check certification history JSON |
| Map does not zoom correctly | Map config bounds missing/wrong | Check `api/config/state/map.json` |
| User sees School/UDISE labels when Facility/NIN was expected | Different deployment profile is active | Confirm profile through approved configuration/deployment process; do not edit database column names to change labels. |

## Reports

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Download fails | API error or file permission issue | Check network response and logs |
| Score is wrong | Revised score/baseline score mismatch | Check response score and action plan revised score |
| Excel format wrong | Template/report code mismatch | Compare generated file with expected format |
| Report contains too much or too little data | Role/geography scope or approved export fields are wrong | Test with a least-privilege account; do not use a privileged export as proof of normal user access. |

## Public-Page Security Checks

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Static landing/GitBook headers are incomplete | IIS/web-server static-header policy is missing | In staging, inspect `/`, `gitbook.html` and `ui/login.html` for the approved CSP, clickjacking/frame, `nosniff` and referrer-policy headers. Track the current VAPT follow-up until verified. |
| Public branding API reveals sensitive data | Public endpoint was extended beyond presentation data | Restrict it to profile code/name, labels, branding and public content; remove secrets, internal hosts, user/session data and storage paths. |

## FAQ

**Can one facility have multiple assessments?**  
Yes. A Facility (Healthcare) or School (Education) can have historical completed/cancelled assessments, but only one active assessment at a time.

**Is evidence mandatory?**  
Evidence is optional unless local implementation rules make it mandatory.

**Can SaQshi support another state?**  
Yes. Use approved facility/school master data, map configuration and relevant profile/framework JSON. Complete data-owner approval and deployment acceptance testing before publishing or migrating real data.

**Can I change Healthcare to Education only to test the landing page?**
No. A profile change affects labels, modules, default framework behaviour and the public landing route. Test it in an approved non-production environment with backup, owner approval and the deployment-profile verification steps.

**Does an HTTP 200 GitBook or landing page result mean deployment is ready?**
No. It confirms route availability only. Complete role-flow checks, accessibility, security headers, legal/privacy approval, release checklist and controlled-UAT security validation before release.

**Can Kafka be added later?**  
Yes. The event abstraction allows future message broker integration without changing every API workflow.
