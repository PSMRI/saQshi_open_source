# SaQshi

SaQshi is an open-source digital quality assessment and monitoring application for healthcare facilities. It supports facility-level assessment, department activation, checklist scoring, CQI workflows, performance monitoring, certification monitoring, state/district/block dashboards, reports, and documentation.

License: GPL-3.0. See [LICENSE](LICENSE).

## Project Context

SaQshi is aligned with the digital workflow needs of healthcare quality assessment programmes such as NQAS. The NQAS public guidance describes quality standards for public health facilities, self-assessment for improvement, preparation for certification, and eight Areas of Concern including Service Provision, Patient Rights, Inputs, Support Services, Clinical Care, Infection Control, Quality Management, and Outcome.

SaQshi implements this as a configurable application: facility and framework data are loaded through JSON and APIs, assessment responses are stored against an assessment, CQI tracks gaps and revised scores, performance monitoring captures monthly KPI/outcome data, and state-level dashboards provide aggregated monitoring. See [Project Overview and NQAS Alignment](docs/architecture/project_overview.md).

## Version Evolution

SaQshi evolved from an MVP checklist assessment tool into V1, V2, and the current open-source release. The current open-source version includes assessment, CQI, performance monitoring, certification, downloadable reports, advanced visualizations, state/district/division/block dashboards, and public project documentation.

See [Version Matrix](docs/architecture/version_matrix.md) for the MVP, V1, V2 and Open Source feature comparison.

## Main Modules

| Module | Purpose |
|---|---|
| Assessment | Create assessments, activate departments, capture assessor information, complete checkpoint scoring, and generate assessment reports. |
| CQI | Review gaps, prepare action plans, upload evidence, and close gaps. |
| Performance Monitoring | Capture KPI and outcome indicators month-wise and view trends. |
| Reports | Download scorecards, progress reports, CQI reports, performance reports, and state monitoring reports. |
| State Monitoring | View certification, assessment, CQI, performance, facility drill-down, user administration, and indicator analytics by administrative level. |
| Facility User Profile | Manage logged-in facility user profile and facility profile data. |
| Documentation and Help | User and developer guides inside the application. |

## Technology Overview

- Backend: PHP with MySQL/MariaDB.
- Frontend: HTML, CSS, JavaScript.
- API style: Versioned PHP endpoints under `api/*/v1`.
- Configuration: JSON files under `api/config` and `ui/pages/**/*.json`.
- Environment: `.env` loaded through `api/core/Env.php`.
- Authentication/session: PHP session-based API authentication.
- Reports: CSV/XLSX-style downloads and generated report views.
- Maps: OpenStreetMap/Leaflet for state certification map views.

## Repository Structure

```text
api/assessment/v1/       Assessment APIs
api/certification/       Certification APIs
api/core/                Shared core classes: auth, env, crypto, events, response, CSRF
api/files/v1/            File upload APIs
api/performance/v1/      KPI/outcome/performance APIs
api/reports/v1/          Report download APIs
api/service/             Shared service classes
api/sql/                 Database migration files
api/state/v1/            State/district/block monitoring APIs

ui/assets/               Shared UI CSS/JS
ui/components/           Header, sidebar, footer, modal, loader, notification
ui/layouts/              Dashboard shell
ui/pages/                Route-based pages and page manifests

docs/
  api/                 OpenAPI, Postman collection and API testing guide
  compliance/          Open-source readiness, licensing and release docs
  database/            Database setup and migration guide
  security/            Security reviews
  testing/             Test plan, VAPT, load testing, WCAG docs
```

## Requirements

- PHP 8.x recommended.
- MySQL or MariaDB.
- Web server such as Apache/IIS/Nginx configured to serve the project.
- PHP extensions commonly needed:
  - `mysqli`
  - `json`
  - `openssl`
  - `fileinfo`
  - `session`
  - `zip` where XLSX/report generation requires archive support

## Local Setup

1. Clone or copy the repository into the web root.
2. Copy `.env.example` to `.env`.
3. Update database credentials in `.env`.
4. Create/import the SaQshi database schema.
5. Apply migration files from `api/sql` in chronological order.
6. Configure the web server to serve the project root.
7. Open:

```text
{main_url}/ui/login.html
```

The port/path may differ depending on your local server setup.

## Environment Configuration

Use `.env.example` as the template:

```text
APP_ENV=local
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saqshi
DB_USERNAME=saqshi_user
DB_PASSWORD=change_me
DB_CONNECT_TIMEOUT=5
```

Do not commit `.env`. It is intentionally ignored by `.gitignore`.

## Database

Database setup and migration guidance is documented here:

```text
docs/database/database_setup_and_migration.md
```

Current migration files are stored under:

```text
api/sql/
```

## API Documentation

API documentation and testing files are available under:

```text
docs/api/
```

Useful files:

- `docs/api/openapi.yaml`
- `docs/api/swagger-ui.html`
- `docs/api/saqshi_postman_collection.json`
- `docs/api/POSTMAN_TESTING_GUIDE.md`

## Testing and Security

Testing documentation is available under:

```text
docs/testing/
```

Security documentation is available under:

```text
docs/security/
SECURITY.md
```

Important checks before release:

- Run syntax checks for changed PHP/JS files.
- Run available API smoke tests.
- Run VAPT/security checks.
- Run WCAG/accessibility checks.
- Confirm `.env`, logs, keys and uploads are not committed.

## Open Source Release Docs

Release and compliance documents:

- `docs/compliance/open_source_readiness_checklist.md`
- `docs/compliance/license_consistency_before_after.md`
- `docs/compliance/third_party_licenses.md`
- `docs/compliance/release_checklist.md`
- `NOTICE`
- `CHANGELOG.md`

## GitBook Documentation

This project root is GitBook-ready:

- `README.md` is the landing page.
- `SUMMARY.md` defines the GitBook sidebar.
- `docs/gitbook.md` explains how to import and maintain the book.

## Contributing

Please read:

- [CONTRIBUTING.md](CONTRIBUTING.md)
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- [SECURITY.md](SECURITY.md)

## License

SaQshi is released under GPL-3.0. See [LICENSE](LICENSE).
