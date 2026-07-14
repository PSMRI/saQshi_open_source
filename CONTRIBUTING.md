# Contributing

Contributions are welcome. Please keep changes focused, documented, and aligned
with the SaQshi open-source release structure.

## Before You Start

- Read the [Code of Conduct](CODE_OF_CONDUCT.md).
- Read the [Security Policy](SECURITY.md) before reporting vulnerabilities.
- Check the [README](README.md) for setup and module overview.
- Use `.env.example` for local configuration. Do not commit `.env`.

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

## Useful References

- API guide: `docs/api/README.md`
- OpenAPI file: `docs/api/openapi.yaml`
- Postman guide: `docs/api/POSTMAN_TESTING_GUIDE.md`
- Database guide: `docs/database/database_setup_and_migration.md`
- Release checklist: `docs/compliance/release_checklist.md`
- Third-party attribution: `docs/compliance/third_party_licenses.md`

