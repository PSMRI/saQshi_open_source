# Contributing

Contributions are welcome. Please keep changes focused, documented, and aligned
with the SaQshi open-source release structure.

## Before You Start

- Read the [Code of Conduct](CODE_OF_CONDUCT.md).
- Read the [Security Policy](SECURITY.md) before reporting vulnerabilities.
- Check the [README](README.md) for setup and module overview.
- Use `.env.example` for local configuration. Do not commit `.env`.
- Review the [Release Checklist](docs/compliance/release_checklist.md) before proposing release-facing changes.

## Branch and Change Workflow

- Create a short, focused branch for each change, such as `feature/assessment-report-export` or `fix/state-dashboard-counts`.
- Keep one pull request focused on one purpose. Avoid mixing UI redesign, API behavior changes and documentation cleanup in the same pull request unless they are directly connected.
- Describe the user-facing change, affected modules, database impact and test evidence in the pull request.
- Rebase or merge from the main development branch before final review if the change becomes stale.
- Do not commit generated private data, local uploads, logs, database dumps, credentials or environment files.

## Coding Standards

- Follow the existing project structure: PHP APIs under `api/`, UI pages under `ui/`, documentation under `docs/`, tools under `tools/`, and maintenance scripts under `scripts/`.
- Keep API responses consistent with the project response format: status, message, data, errors and timestamp where applicable.
- Use prepared statements or existing service helpers for database access. Do not add raw SQL built from request values.
- Keep UI pages modular with separate `.html`, `.js`, `.css` and `.json` files where the existing pattern uses them.
- Preserve accessibility behavior: keyboard access, visible focus, readable contrast, labels for form controls and screen-reader friendly status messages.
- Add comments for non-obvious business rules, validation rules and security-sensitive logic.
- Keep SaQshi-owned file headers aligned with GPL-3.0. Do not alter third-party license notices.

## Testing Expectations

- Run PHP syntax checks for changed PHP files before submitting.
- Smoke-test changed UI routes in the browser, including refresh behavior and role-based sidebar access.
- Test API changes with the Postman collection or Swagger UI when applicable.
- For database changes, document the migration path and verify that a fresh setup can still be reproduced.
- For security-sensitive changes, check CSRF/session behavior, authorization checks, input validation and friendly error responses.
- For accessibility-sensitive changes, test keyboard navigation and screen-reader mode behavior.

## Documentation Expectations

- Update the GitBook/SUMMARY navigation when adding a new public document.
- Update OpenAPI/Postman docs when API request or response contracts change.
- Update the database dictionary/migration notes when tables or columns change.
- Update user guide or developer guide sections when workflows change.
- Keep `{main_url}` placeholders in public documentation instead of hardcoded localhost URLs.

## Review Expectations

- Maintainers should review for correctness, security, privacy, accessibility, documentation and release impact.
- Pull requests that touch authentication, authorization, encryption, file upload, database migrations or reporting exports should receive extra review.
- Public-release changes should be checked against the open-source readiness checklist and DPG readiness assessment.

## Project Areas

- `api/` contains PHP APIs, services, core helpers, config and SQL migrations.
- `ui/` contains HTML, CSS, JavaScript, layout, components and route pages.
- `docs/` contains API, compliance, database, security and testing documents.
- `tools/` contains developer utilities.
- `scripts/` contains setup or maintenance scripts.

## Pull Request Checklist

- Keep SaQshi-owned source headers and visible metadata aligned to GPL-3.0.
- Do not rewrite third-party license notices.
- Update documentation when changing API contracts, routes, database tables or UI workflows.
- Add or update test notes where behavior changes.
- Verify that `.env`, uploads, logs, keys, archives and real user/facility data are not committed.
- Confirm that public data/configuration files have redistribution rights.
- Confirm that any new dependency is listed in third-party attribution before release.

## Useful References

- API guide: `docs/api/README.md`
- OpenAPI file: `docs/api/openapi.yaml`
- Postman guide: `docs/api/POSTMAN_TESTING_GUIDE.md`
- Database guide: `docs/database/database_setup_and_migration.md`
- Release checklist: `docs/compliance/release_checklist.md`
- Third-party attribution: `docs/compliance/third_party_licenses.md`

