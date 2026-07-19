# System Requirements for UAT and Production

This page gives practical server sizing and software requirements for SaQshi deployment. Final sizing depends on number of facilities, concurrent users, upload volume, report usage, map usage and backup policy.

## Deployment Profiles

| Environment | Purpose | Recommended Use |
| --- | --- | --- |
| Development | Local developer testing and code changes. | Single developer or small technical team. |
| UAT | User acceptance testing, training, release verification and pilot workflows. | Test users from facility/block/district/state roles. |
| Production | Live operational use. | Real facility assessment, CQI, certification, performance monitoring and reports. |

## Minimum Software Requirements

| Component | UAT | Production |
| --- | --- | --- |
| Operating system | Windows Server 2019+, Ubuntu 22.04 LTS+, Debian 12+, RHEL/Rocky/Alma 9+ | Same, preferably LTS/server edition |
| Web server | IIS, Apache 2.4+ or Nginx 1.22+ | IIS, Apache 2.4+ or Nginx 1.22+ with HTTPS |
| PHP | PHP 8.2+ | PHP 8.2+ or approved current stable PHP |
| Database | MySQL 8+ or MariaDB 10.6+ | MySQL 8+ or MariaDB 10.6+ managed or dedicated |
| Browser support | Current Chrome, Edge, Firefox | Current Chrome, Edge, Firefox |
| TLS/HTTPS | Recommended | Required |
| Time sync | NTP recommended | NTP required |

## Required PHP Extensions

| Extension | Purpose |
| --- | --- |
| `mysqli` | MySQL/MariaDB connection. |
| `openssl` | Password transport and field encryption. |
| `json` | API responses and configuration files. |
| `mbstring` | Safe string handling. |
| `fileinfo` | Upload MIME validation. |
| `zip` | Report/export/archive operations where required. |
| `session` | Login/session handling. |

## Recommended Server Sizing

### Small UAT / Pilot

Use for training, demonstration or a pilot with limited data.

| Resource | Recommendation |
| --- | --- |
| App server CPU | 2 vCPU |
| App server RAM | 4 GB |
| App disk | 50 GB SSD |
| Database | Same server allowed for UAT, or small managed DB |
| Database RAM | 2-4 GB |
| Upload storage | 20-50 GB depending on evidence files |
| Expected users | 10-30 concurrent test users |

### Medium Production

Use for district/division/state deployment with regular monitoring users and report downloads.

| Resource | Recommendation |
| --- | --- |
| App server CPU | 4 vCPU |
| App server RAM | 8-16 GB |
| App disk | 100 GB SSD |
| Database CPU | 4 vCPU |
| Database RAM | 8-16 GB |
| Database storage | 100-250 GB SSD, expandable |
| Upload storage | 250 GB+, expandable |
| Expected users | 50-150 concurrent users |

### Large Production / State Scale

Use where more than 50,000 facilities or heavy state-level monitoring/reporting is expected.

| Resource | Recommendation |
| --- | --- |
| App server | 2 or more app nodes behind load balancer |
| App CPU | 4-8 vCPU per node |
| App RAM | 16 GB per node |
| Database | Dedicated managed MySQL/MariaDB |
| Database CPU | 8+ vCPU |
| Database RAM | 32+ GB |
| Database storage | 500 GB+ SSD with growth monitoring |
| Upload storage | Object storage or dedicated file storage |
| Cache | Optional Redis/cache layer for heavy dashboards |
| Expected users | 150+ concurrent users |

## Storage Planning

| Data Type | Storage Location | Planning Note |
| --- | --- | --- |
| Database records | MySQL/MariaDB | Grows with assessments, responses, CQI, certification and performance entries. |
| Evidence files | `uploads/` or object storage | Can grow quickly if image/PDF evidence is uploaded. |
| Reports/exports | Generated on demand or stored temporarily | Avoid keeping unnecessary generated files permanently. |
| Logs/events | `api/storage/logs/`, `api/storage/events/` | Apply retention policy and log rotation. |
| Backups | Separate disk/object storage | Keep outside web root and test restore. |

## Network and Security Requirements

| Area | Requirement |
| --- | --- |
| HTTPS | Required for production. |
| Firewall | Allow only required ports such as 80/443 and restricted admin access. |
| Database access | Database should not be publicly exposed. Restrict to app server or private network. |
| Admin access | SSH/RDP restricted to approved admin IPs/VPN. |
| Secrets | Use `.env`, cloud secret store or equivalent. Do not commit secrets. |
| Upload protection | Validate file type/size and prevent direct execution from upload folders. |
| Directory listing | Disabled. |
| Timezone | Set correct server timezone and NTP sync. |

## Recommended PHP Configuration

Adjust values according to evidence upload size and report requirements.

| Setting | UAT | Production |
| --- | ---:| ---:|
| `memory_limit` | 256M | 512M or higher for large reports |
| `max_execution_time` | 60 | 120 for large reports/imports |
| `upload_max_filesize` | 10M-25M | 25M-50M or policy-based |
| `post_max_size` | Higher than upload limit | Higher than upload limit |
| `display_errors` | Off for shared UAT | Off |
| `log_errors` | On | On |
| `session.cookie_httponly` | On | On |
| `session.cookie_secure` | On when HTTPS | On |

## Database Requirements

| Requirement | Recommendation |
| --- | --- |
| Character set | `utf8mb4` |
| Collation | `utf8mb4_unicode_ci` or compatible |
| Backup | Daily automated backup for production |
| Point-in-time recovery | Recommended for production |
| Indexing | Review indexes for assessment, facility, state monitoring and report queries |
| Slow query log | Enable for production tuning |
| Connection limits | Size according to expected concurrent users |

## Environment Variables

Use `.env.example` as the baseline.

Minimum required values:

```text
APP_ENV=production
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saqshi
DB_USERNAME=saqshi_user
DB_PASSWORD=change_me
DB_CONNECT_TIMEOUT=5

SAQSHI_FIELD_ENCRYPTION_KEY=change_to_long_random_secret
```

Production must not use default placeholder passwords or encryption keys.

## UAT Readiness Checklist

- UAT server configured with same PHP/database versions planned for production.
- `.env` configured with UAT database and non-production secrets.
- Test users created for facility, block, district, division and state roles.
- Sample or approved test data loaded.
- Upload folder writable.
- Logs/events writable.
- GitBook, Swagger and Postman files reachable.
- Accessibility smoke checks completed.
- UAT backup/restore procedure tested at least once.

## Production Readiness Checklist

- HTTPS enabled with valid certificate.
- `.env` protected and not web-downloadable.
- `APP_DEBUG=false`.
- Database backup and restore tested.
- Upload/evidence retention policy configured.
- Log retention and rotation configured.
- Runtime private files not committed or published.
- Admin access restricted.
- Release readiness check completed.
- Load test completed for expected concurrent users.
- Monitoring configured for disk, CPU, RAM, database and web/API errors.

## Monitoring Requirements

Production monitoring should include:

- CPU, memory and disk usage.
- Database connections and slow queries.
- Web server error logs.
- PHP error logs.
- API event/error logs.
- Upload storage growth.
- Backup success/failure.
- SSL certificate expiry.

## Notes for More Than 50,000 Facilities

For large state deployments:

- Use server-side pagination for all large tables.
- Avoid loading full facility lists into the browser.
- Prefer database-backed search/filter APIs.
- Keep reports streaming or paginated.
- Monitor slow state dashboard and report queries.
- Consider read replicas or summary tables if reporting load becomes high.
- Consider object storage for evidence uploads.

