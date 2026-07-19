# Sanitized Base Schema

This directory is reserved for the public, sanitized SaQshi base database schema.

Public release base schema file:

```text
api/sql/schema/001_base_schema.sql
```

Rules:

- Include table structures, indexes, views and safe seed values required for a fresh installation.
- Do not include production data, real user records, passwords, uploads, logs, certification records or live facility-sensitive data.
- Use placeholder/sample values only when seed data is required.
- Keep migrations under `api/sql/` and document each migration in `docs/database/database_setup_and_migration.md`.

Use `001_base_schema.sql` for a fresh database installation. It creates the
database, core tables, indexes, compatibility view, and safe role seed values.
It does not include real users, facility records, assessment records, uploads,
logs or certification data.

Clean-install validation:

- The application has been checked on a newly created schema based on
  `001_base_schema.sql`.
- Deployment-specific seed data is still required: active users, facility types,
  facility rows and approved JSON/master configuration.
- Do not treat a local test database as public sample data unless it has been
  separately sanitized and approved for redistribution.
