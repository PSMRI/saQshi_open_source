# Sanitized Base Schema

This directory is reserved for the public, sanitized SaQshi base database schema.

Expected public release file:

```text
api/sql/schema/001_base_schema.sql
```

Rules:

- Include table structures, indexes, views and safe seed values required for a fresh installation.
- Do not include production data, real user records, passwords, uploads, logs, certification records or live facility-sensitive data.
- Use placeholder/sample values only when seed data is required.
- Keep migrations under `api/sql/` and document each migration in `docs/database/database_setup_and_migration.md`.

Until `001_base_schema.sql` is added and verified, the repository should be treated as install-documentation complete but not fully reproducible from source alone.
