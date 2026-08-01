# Memurai (Redis) session configuration

SaQshi supports Redis-compatible session storage through Memurai on Windows. This avoids session-file contention and allows session storage to be shared by multiple IIS/PHP worker processes.

SaQshi reads its session settings from:

```text
api/config/session.json
```

## Prerequisites

- Windows Server or Windows development machine.
- Memurai Server running locally or on an approved internal host.
- PHP Redis extension enabled for the PHP instance used by IIS.

Confirm the PHP extension:

```powershell
php -m | Select-String redis
```

If no result is returned, enable the matching `php_redis` extension in the PHP `php.ini`, restart IIS, and run the check again.

## Install Memurai on Windows

1. Install Memurai Server using the organisation-approved Windows installer.
2. Start the **Memurai** Windows service.
3. Confirm the server responds:

```powershell
& "C:\Program Files\Memurai\memurai-cli.exe" ping
```

Expected result:

```text
PONG
```

Do not expose Memurai's port `6379` directly to the internet. Keep it bound to localhost or an internal network protected by firewall rules.

## SaQshi configuration

The default local configuration is:

```json
{
  "cookie_name": "SAQSHI_SESSION",
  "driver": "redis",
  "redis": {
    "host": "127.0.0.1",
    "port": 6379,
    "database": 2,
    "prefix": "saqshi_sess_",
    "timeout_seconds": 1,
    "read_timeout_seconds": 1,
    "persistent": true,
    "password_env": "SAQSHI_REDIS_PASSWORD"
  }
}
```

`password_env` is the *name* of an optional Windows environment variable; it is not the password itself. If Memurai does not require authentication, leave the environment variable unset.

If authentication is enabled, set the password outside source code. For example, create a system environment variable named `SAQSHI_REDIS_PASSWORD`, then restart IIS so PHP can read it.

## PHP `php.ini`

SaQshi applies its own session configuration before `session_start()`. Do **not** set an application-specific `session.save_path` in a shared `php.ini` when the same PHP runtime serves multiple applications.

If a global Redis session handler is required, the equivalent setting is:

```ini
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?database=2&prefix=saqshi_sess_"
```

Prefer the SaQshi JSON configuration for per-application isolation.

## Two applications on one Memurai server

Give each application its own cookie name and either a different database number or a distinct prefix.

| Application | Cookie | Database | Prefix |
| --- | --- | --- | --- |
| SaQshi | `SAQSHI_SESSION` | `2` | `saqshi_sess_` |
| Other application | `OTHERAPP_SESSION` | `3` | `otherapp_sess_` |

Never share the same cookie name and key prefix between unrelated applications.

## Verify session performance

Check Memurai health and memory usage:

```powershell
& "C:\Program Files\Memurai\memurai-cli.exe" ping
& "C:\Program Files\Memurai\memurai-cli.exe" info memory
```

During a SaQshi login, session reads and writes should be only a few milliseconds on a healthy local Memurai server. If login is still slow, check database queries, password hashing, external captcha/notification calls, and synchronous audit logging rather than assuming Redis is the bottleneck.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Login fails after enabling Redis | Confirm the PHP Redis extension and the Memurai service are running. |
| Session is not retained | Confirm cookie name, browser cookie settings, and the configured Redis database/prefix. |
| Connection refused | Verify host/port and Windows Firewall; test `memurai-cli.exe ping`. |
| Two applications log each other out | Use separate cookie names and prefixes/databases. |
| Production password needed | Set `SAQSHI_REDIS_PASSWORD` as a machine environment variable; do not commit it to JSON or `php.ini`. |
