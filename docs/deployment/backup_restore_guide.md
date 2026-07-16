# Backup and Restore Guide

SaQshi stores important data in three places: the database, uploaded files, and environment/configuration files. A reliable backup must include all three.

## What to Back Up

| Area | Examples |
| --- | --- |
| Database | users, roles, facilities, assessment, CQI, performance, certification and audit tables |
| Uploads | evidence files, certificates, generated reports |
| Configuration | `.env`, framework JSON, performance JSON, certification JSON, map JSON |
| Documentation | GitBook docs if edited in deployment |

## Backup Frequency

| Data | Suggested Frequency |
| --- | --- |
| Database | Daily, or more often for active deployments |
| Uploads | Daily |
| Configuration | On every change |
| Full application snapshot | Before every upgrade |

## Database Backup

Use your database administration tool or `mysqldump`.

```bash
mysqldump -u {db_user} -p {db_name} > saqshi_backup.sql
```

## Upload Backup

Back up:

```text
uploads/
api/storage/
```

Keep upload backups and database backups from the same time window so evidence links remain valid.

## Restore Order

1. Restore application files.
2. Restore `.env`.
3. Restore database.
4. Restore `uploads/`.
5. Restore `api/storage/` if operational logs/events are required.
6. Verify permissions.
7. Open `{main_url}/ui/login.html`.
8. Test one facility login and one monitoring login.

## Restore Verification

| Check | Expected Result |
| --- | --- |
| User login | Existing users can login |
| Assessment list | Historical assessments are visible |
| Evidence links | Uploaded files open correctly |
| Reports | Downloads generate correctly |
| State dashboards | Counts match restored database |
| GitBook | Documentation opens correctly |

## Backup Security

- Encrypt backup files when stored outside the server.
- Restrict access to backups.
- Do not email raw database backups.
- Rotate old backups according to local policy.
- Test restore regularly, not only after failure.
