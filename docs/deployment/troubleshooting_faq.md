# Troubleshooting and FAQ

This page lists common SaQshi issues and first checks.

## Page Loading

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Page Load Failed | API returned error or route failed | Open browser console and API response |
| 404 after refresh | Web server routing/static path issue | Confirm direct page path exists |
| Sidebar link not working | Route or page JSON mismatch | Check `ui/pages/.../*.json` and router config |
| GitBook shows raw markdown | MIME or GitBook renderer issue | Confirm page opens through `gitbook.html?doc=...` |

## Login

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Something went wrong | API/db/config error | Check API JSON and `api/storage/logs/` |
| Invalid username or password | `s_user.u_name` not found, inactive user, inactive role, wrong password or wrong captcha | Check exact `s_user.u_name`, `is_active`, `role_id_fk`, role status and captcha |
| Assessor cannot login | Assessor profile exists but linked `s_user` row was not created or user is inactive | Check `assessor_master.user_id`, matching `s_user.u_name = assessor_code`, role `Assessor`, `is_active = 1` |
| Captcha not loading | Captcha endpoint or session issue | Open captcha API directly |
| CSRF validation failed | Token missing/expired | Call CSRF API again and retry |
| Role menu wrong | Role mapping issue | Check user role and role status |

## Database

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Connection failed | `.env` DB values wrong | Verify DB host/user/password/database |
| Data not visible | Facility/user mapping missing | Check `fac_id_fk`, role and scope |
| Duplicate NIN error | Facility NIN already exists | Search facility by NIN before update |

## Assessment and CQI

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| No active assessment | No ACTIVE assessment or wrong facility | Check `assessment_master` for user facility |
| Department not active | Department not activated for assessment | Check department activation page/API |
| Checkpoints not loading | Framework JSON or filters mismatch | Check department, concern, subtype and method |
| Open gaps show zero | Scores are complete or query scope wrong | Check score 0/1 responses |
| Action plan warning | Missing gap/action plan record | Check action plan API response |

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

## Reports

| Issue | Possible Cause | First Check |
| --- | --- | --- |
| Download fails | API error or file permission issue | Check network response and logs |
| Score is wrong | Revised score/baseline score mismatch | Check response score and action plan revised score |
| Excel format wrong | Template/report code mismatch | Compare generated file with expected format |

## FAQ

**Can one facility have multiple assessments?**  
Yes. A facility can have historical completed/cancelled assessments, but only one active assessment at a time.

**Is evidence mandatory?**  
Evidence is optional unless local implementation rules make it mandatory.

**Can SaQshi support another state?**  
Yes. Update facility master data, map configuration and relevant JSON configuration.

**Can Kafka be added later?**  
Yes. The event abstraction allows future message broker integration without changing every API workflow.
