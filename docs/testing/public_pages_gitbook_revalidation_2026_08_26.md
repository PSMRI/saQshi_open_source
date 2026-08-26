# Public Pages and GitBook Revalidation — 2026-08-26

## Scope

This focused revalidation covered the public healthcare landing page, the
profile-aware landing-page route, and the GitBook documentation reader after
their recent updates.

## Results

| Check | Result | Evidence |
|---|---|---|
| PHP syntax | Pass | `php tools/php_syntax_check.php` completed successfully. |
| JSON validation | Pass | `php tools/json_syntax_check.php` completed successfully after removal of a UTF-8 BOM from `api/config/masters/facility_types_eduction_bihar.json`. |
| PHP style | Pass | `php tools/php_style_check.php` completed successfully. |
| JavaScript style | Pass | `php tools/js_style_check.php` completed successfully. |
| Unit tests | Pass | `php tools/run_unit_tests.php`: 8 passed, 0 failed. |
| Release readiness | Passed with review | `php tools/release_readiness_check.php` completed with existing warnings for local/private runtime files and large assets. |
| Public landing page | Pass | `GET /` returned HTTP 200 and the profile-aware `index.html` landing page. |
| GitBook reader | Pass | `GET /gitbook.html` returned HTTP 200. |
| Login shell | Pass | `GET /ui/login.html` returned HTTP 200. |
| Captcha method guard | Pass | A `HEAD` request to `/api/auth/v1/captcha.php` returned HTTP 405, confirming the endpoint rejects an unsupported method. |

## Security Notes

- The public-page configuration no longer exposes the login page as the root
  default document; IIS serves `index.html`, whose profile-aware client logic
  selects the appropriate healthcare or education landing page.
- The release-readiness review continues to flag runtime data, logs and key
  files for exclusion from a public release. These are deployment hygiene
  warnings, not test failures, and must be resolved before publishing a build.
- Accessibility smoke-report files were locked by another local process during
  this recheck, so the static and live accessibility scripts could not replace
  their JSON result files. Existing accessibility documentation remains in the
  GitBook; rerun those scripts once the report files are unlocked.

## Limits

This is a focused regression and configuration check, not a substitute for a
full penetration test, authenticated workflow test, manual screen-reader test
or production deployment review.
