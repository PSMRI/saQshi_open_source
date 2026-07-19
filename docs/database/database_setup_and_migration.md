# SaQshi Database Setup and Migration Guide

Version: 1.1  
Updated: 2026-07-18

## Purpose

This guide explains how to configure SaQshi database access, create a local database, apply migration files, and manage future database changes safely.

## Database Engine

SaQshi currently uses MySQL/MariaDB through PHP `mysqli`.

Recommended:

- MySQL 8.x or compatible MariaDB.
- UTF-8 capable database collation.
- A dedicated database user for the application.

## Environment Configuration

Database credentials are read from `.env`.

Create `.env` from `.env.example`:

```text
APP_ENV=local
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saqshi
DB_USERNAME=saqshi_user
DB_PASSWORD=change_me
DB_CONNECT_TIMEOUT=5
SAQSHI_FIELD_ENCRYPTION_KEY=change_this_to_a_long_random_secret
```

Do not commit `.env`.

## Create Database User

Example for local development:

```sql
CREATE DATABASE saqshi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'saqshi_user'@'{db_host}' IDENTIFIED BY 'change_me';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, CREATE VIEW
ON saqshi.*
TO 'saqshi_user'@'{db_host}';

FLUSH PRIVILEGES;
```

For production, use a strong password and follow least-privilege rules approved by the deployment owner.

## Base Schema

The repository now includes a sanitized base schema for fresh installation:

```text
api/sql/schema/001_base_schema.sql
```

This file creates:

- `saqshi` database,
- role/user tables,
- facility master tables,
- assessment, department, assessor info and response tables,
- CQI action plan tables,
- KPI/outcome performance table,
- certification tables,
- state assessor assignment tables,
- AI chat history table,
- schema migration tracking table,
- legacy `assessment_cycle_response` compatibility view.

Run it on a fresh database server:

```text
mysql -u root -p < api/sql/schema/001_base_schema.sql
```

Or, if the database already exists and you want to apply only with the app user:

```text
mysql -u saqshi_user -p saqshi < api/sql/schema/001_base_schema.sql
```

The file contains schema and safe role seed values only. It does not contain
real users, passwords, facility records, uploads, logs, assessment responses or
certification records.

## Clean Schema Validation Status

The current application has been checked on a newly created database schema
using `api/sql/schema/001_base_schema.sql` as the base installation script.
This confirms that the public base schema is sufficient to start the
application when the required environment values and approved master/config data
are added.

Validation recorded:

| Item | Status |
| --- | --- |
| Fresh schema import | Checked on newly created schema |
| Core login user table and role table | Checked |
| Facility table and facility type seed path | Checked |
| Assessment creation and active assessment flow | Checked |
| Department activation and assessor info flow | Checked |
| Checklist response save path | Checked after schema naming fixes |
| CQI gap-analysis/action-plan/gap-closure tables | Checked after schema naming fixes |
| Report download template path and generated Excel reports | Checked |
| Performance indicator entry denominator-zero handling | Checked |

Deployment note: a clean schema still needs deployment-specific seed data such
as at least one active user, facility type rows, facility rows and approved
framework/configuration JSON files before users can complete end-to-end
workflows.

After running the base schema, import approved/sanitized master data as needed:

- facility master data,
- facility type data,
- framework/checklist JSON files,
- certification configuration,
- performance KPI/outcome JSON files.

The repository also includes migration and schema-support files under
`api/sql/` such as:

```text
api/sql/schema/001_base_schema.sql
api/sql/2026_07_02_rename_assessment_cycle_response.sql
api/sql/schema/user_profile_encryption_columns.sql
api/sql/schema/assessor_info_encryption_columns.sql
api/sql/schema/2026_07_18_assessor_assignment.sql
api/sql/schema/2026_07_18_configurable_responses.sql
api/sql/schema/2026_07_18_department_status_compatibility.sql
```

Do not commit production data dumps.

## Applying Migrations

1. Back up the database.
2. Confirm the current schema version.
3. Review the SQL script manually.
4. Apply scripts in chronological order.
5. Test affected workflows.

Example:

```text
mysql -u saqshi_user -p saqshi < api/sql/2026_07_02_rename_assessment_cycle_response.sql
```

## User Profile Field Encryption Migration

SaQshi encrypts facility user profile identity fields at rest when they are saved from the My Profile page/API.

Encrypted fields in `s_user`:

- `f_name`
- `m_name`
- `l_name`
- `mail_id`
- `mob_no`

For an existing database, run these steps once:

1. Set a stable `SAQSHI_FIELD_ENCRYPTION_KEY` in `.env`.
2. Widen the profile columns so encrypted values fit:

```text
mysql -u saqshi_user -p saqshi < api/sql/schema/user_profile_encryption_columns.sql
```

3. Encrypt existing plaintext profile rows:

```text
php scripts/encrypt_existing_user_profile_fields.php
```

The migration script is safe to rerun. It skips values already stored with the `enc:v1:` prefix.

Important:

- Do not rotate `SAQSHI_FIELD_ENCRYPTION_KEY` after data is encrypted unless a planned decrypt/re-encrypt migration is performed.
- Back up the database before running the column-size SQL or migration script.
- New profile saves through `api/admin/v1/users.php` will store encrypted values automatically.

## Assessor / Assessee Field Encryption Migration

SaQshi encrypts structured assessor/assessee personal fields at rest when assessor information is saved.

Encrypted fields in `assessment_assessor_info`:

- `assessor_name`
- `assessor_mobile`
- `assessor_email`
- `assessee_name`
- `assessee_mobile`
- `assessee_email`

Designation fields remain plain because they store post/role values.

For an existing database, run these steps once:

1. Confirm `SAQSHI_FIELD_ENCRYPTION_KEY` is set in `.env`.
2. Widen the assessor information columns so encrypted values fit:

```text
mysql -u saqshi_user -p saqshi < api/sql/schema/assessor_info_encryption_columns.sql
```

3. Encrypt existing plaintext assessor information rows:

```text
php scripts/encrypt_existing_assessor_info_fields.php
```

The migration script is safe to rerun. It skips values already stored with the `enc:v1:` prefix.

## Current Migration

### `schema/2026_07_18_assessor_assignment.sql`

Purpose:

- Creates `assessor_master` for state-managed assessor profiles.
- Creates `assessor_facility_mapping` for mapping assessors to one or more facilities.
- Adds assessor tracking fields to `assessment_master` for assessments started through the state assessor workflow.
- Adds `password_must_change` and `password_changed_on` to `s_user` for temporary-password login flows.

Important:

- Run after the base assessment tables exist.
- Assessor name, mobile and email are encrypted by the API before saving.
- If `assessor_master.user_id` is blank during new assessor creation, the API creates a login user automatically with username equal to assessor code.
- Login checks `s_user.u_name`, so each assessor who needs login access must
  have a linked active `s_user` row. The expected assessor login identifier is
  the assessor code.
- Temporary passwords are hashed before storage and should be delivered through configured SMS/email channels.

### `2026_07_02_rename_assessment_cycle_response.sql`

Purpose:

- Renames `assessment_cycle_response` to `assessment_response`.
- Renames `cycle_id` to `assessment_id`.
- Adds assessment/dept/checkpoint indexes and a unique response scope.
- Creates a compatibility view named `assessment_cycle_response`.

Important:

- Take backup before running.
- If the database already has indexes with conflicting names, adjust/drop legacy indexes manually.
- Verify old endpoints do not write to the compatibility view unless the database supports that safely.

### `schema/2026_07_18_configurable_responses.sql`

Purpose:

- Adds response metadata columns to `assessment_response`.
- Stores structured checkpoint answers in `response_json`.
- Tracks `max_score` and `score_status` so non-scored data-entry checkpoints do not distort score percentages.
- Creates `assessment_response_field_index` for structured healthcare analytics such as service load, staff availability or operational counts.
- Creates `assessment_response_evidence` for future field-level/multi-file evidence references.

Important:

- Run after `assessment_response` exists.
- Check whether the columns already exist before running on a database where the application has already auto-created them.
- The API service performs an idempotent schema check before checklist response load/save, but production deployments may still prefer this explicit migration.

### `schema/2026_07_18_department_status_compatibility.sql`

Purpose:

- Adds/normalizes `ass_period_id` and `assessment_id` on `assessment_department_status`.
- Keeps older/current department-status services using `ass_period_id` compatible with newer schema/docs using `assessment_id`.
- Adds triggers to keep both values synchronized when either one is supplied.

Run this if a fresh database was created before the base schema was updated, or if `assessment/v1/list.php` or department activation shows an unknown-column error involving `ass_period_id` or `assessment_id`.

## Future Migration Naming Standard

Use:

```text
YYYY_MM_DD_short_description.sql
```

Examples:

```text
2026_07_14_create_cqi_action_plan_user_suggestions.sql
2026_07_14_add_facility_geo_coordinates.sql
```

Each migration should include:

- Purpose.
- Pre-checks.
- SQL changes.
- Post-checks.
- Rollback notes where possible.

## Migration Template

```sql
-- SaQshi migration
-- File: YYYY_MM_DD_short_description.sql
-- Purpose:
--   Explain the change.
--
-- Before running:
--   1. Take database backup.
--   2. Confirm target tables/columns exist.
--
-- Rollback:
--   Explain manual rollback or note why rollback needs backup restore.

START TRANSACTION;

-- SQL changes here.

COMMIT;
```

Use transactions only when the database engine and statements support transactional DDL safely.

## Backup Before Migration

Example:

```text
mysqldump -u root -p --routines --triggers --single-transaction saqshi > saqshi_backup_YYYYMMDD_HHMM.sql
```

Store backups outside the public repository.

## Data Privacy Rules

Never commit:

- Production database dumps.
- Patient data.
- Facility evidence uploads.
- Password hashes from production.
- User mobile/email details from production.
- Secret keys or `.env`.

If sample data is needed, create a sanitized seed file.

## Post-Migration Smoke Test

After database changes, test:

- Login.
- Active assessment loading.
- Department activation.
- Checklist save/update.
- Gap analysis.
- Action plan.
- Gap closure.
- Report downloads.
- KPI/outcome entry.
- State dashboard counts.
- Certification status/map.

## Recommended Schema Version Table

Future improvement:

```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_id VARCHAR(150) PRIMARY KEY,
    applied_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by VARCHAR(100) NULL
);
```

This will allow SaQshi to know which migrations have already been applied.
