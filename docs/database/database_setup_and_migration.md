# SaQshi Database Setup and Migration Guide

Version: 1.0  
Updated: 2026-07-13

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
```

Do not commit `.env`.

## Create Database User

Example for local development:

```sql
CREATE DATABASE saqshi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'saqshi_user'@'localhost' IDENTIFIED BY 'change_me';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, CREATE VIEW
ON saqshi.*
TO 'saqshi_user'@'localhost';

FLUSH PRIVILEGES;
```

For production, use a strong password and follow least-privilege rules approved by the deployment owner.

## Base Schema

The current repository contains migration files under:

```text
api/sql/
```

Only one migration file was found during this documentation pass:

```text
api/sql/2026_07_02_rename_assessment_cycle_response.sql
```

If a full base schema dump exists outside the repository, place a sanitized version under a controlled path such as:

```text
api/sql/schema/001_base_schema.sql
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

## Current Migration

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
