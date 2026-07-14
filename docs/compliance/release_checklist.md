# SaQshi Release Checklist

Version: 1.0  
Updated: 2026-07-13

Use this checklist before publishing SaQshi to GitHub, sharing a release archive, or deploying a tagged version.

## 1. Release Identity

| Check | Status |
|---|---|
| Release version selected. | Pending |
| Release date selected. | Pending |
| Release branch/tag created. | Pending |
| `CHANGELOG.md` updated. | Done for current development baseline |
| License verified as GPL-3.0. | Done |

## 2. Source Hygiene

| Check | Command / Evidence | Status |
|---|---|---|
| `.env` is not tracked. | `git ls-files .env` should return nothing. | Done in latest review |
| `.env.example` exists. | `.env.example` present. | Done |
| Logs are ignored. | `.gitignore` excludes `api/storage/events/*.log` and `api/storage/logs/*.log`. | Done |
| Generated keys are ignored. | `.gitignore` excludes `api/storage/keys/`. | Done |
| Uploads are ignored. | `.gitignore` excludes `uploads/`. | Done |
| Large archives removed before public release. | Review `*.rar`, `*.zip`, `*.7z`. | Pending |
| No production database dumps committed. | Manual review required. | Pending |
| No real patient/person data committed. | Manual review required. | Pending |

## 3. License and Attribution

| Check | Status |
|---|---|
| Root `LICENSE` exists. | Done |
| SaQshi-owned UI/config license text aligned to GPL-3.0. | Done |
| `NOTICE` exists. | Done |
| `docs/compliance/third_party_licenses.md` exists. | Done |
| Third-party bundled UI/API files reviewed for exact version/license. | Pending |
| CDN libraries documented or replaced with local managed copies. | Partial |
| Map attribution visible where maps are shown. | Partial / verify manually |

## 4. Documentation

| Check | Status |
|---|---|
| `README.md` expanded with setup and architecture. | Done |
| `SECURITY.md` exists. | Done |
| `CONTRIBUTING.md` exists. | Done but should be expanded |
| `CODE_OF_CONDUCT.md` exists. | Done |
| API OpenAPI file exists. | Done |
| Postman collection exists. | Done |
| Database setup/migration guide exists. | Done |
| User/developer in-app documentation reviewed. | Pending |

## 5. Database

| Check | Status |
|---|---|
| Fresh database can be created from schema. | Pending |
| Migration files in `api/sql` applied in order. | Pending |
| Migration rollback/backup plan documented. | Partial |
| Seed/test data separated from production data. | Pending |
| Facility master data source/license confirmed. | Pending |

## 6. Build and Syntax Checks

Recommended commands:

```text
node -c ui/assets/js/core/router.js
node -c ui/components/header/header.js
Run the available accessibility static checker from the maintained UI/API test workflow.
```

Additional checks:

| Check | Status |
|---|---|
| All changed JavaScript files pass `node -c`. | Pending per release |
| Changed PHP files pass `php -l`. | Pending per release |
| JSON files parse correctly. | Pending per release |
| UI route manifests load correctly. | Pending per release |

## 7. API and Functional Testing

| Check | Status |
|---|---|
| Postman smoke test executed. | Pending |
| Login/logout tested. | Pending |
| Facility assessment workflow tested. | Pending |
| CQI workflow tested. | Pending |
| Performance workflow tested. | Pending |
| State/district/block monitoring tested. | Pending |
| Report downloads tested. | Pending |
| File upload/delete tested. | Pending |

## 8. Security Checks

| Check | Status |
|---|---|
| SQL injection review updated. | Done but review before release |
| VAPT checklist updated. | Done but review before release |
| Raw database/PHP errors not exposed to users. | Partial |
| Password hashing verified. | Pending per release |
| CSRF verified for state-changing APIs. | Pending per release |
| Upload file type/size validation verified. | Pending per release |
| Secret scan completed. | Pending |

## 9. Accessibility and UX

| Check | Status |
|---|---|
| Static WCAG audit passes. | Done in latest review |
| Manual keyboard test completed. | Pending |
| Screen-reader mode tested. | Pending |
| Text resizing tested. | Pending |
| Light/dark theme contrast checked. | Pending |
| Charts/maps have text/table alternatives. | Partial |

## 10. Release Approval

Before public release, confirm:

- Product owner approval.
- Security owner approval.
- License/attribution owner approval.
- Database/data owner approval.
- Deployment owner approval.

## Final Gate

Do not publish if any of these are true:

- `.env` or secrets are tracked.
- Real patient/person data is committed.
- License is inconsistent.
- Security contact is missing.
- Database setup cannot be reproduced.
- Critical/high VAPT issue is unresolved.
