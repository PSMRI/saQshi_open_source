# Memurai (Redis) Session Configuration

Version: 1.1
Updated: 2026-08-26

SaQshi supports Redis-compatible session storage through Memurai on Windows. This avoids session-file contention and allows session storage to be shared by multiple IIS/PHP worker processes. Redis is preferred for resilient multi-worker deployments; SaQshi deliberately falls back to a local file session when Redis cannot start a session, so an outage must still be investigated rather than treated as a normal steady state.

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

The current repository configuration is:

```json
{
  "cookie_name": "SAQSHI_JH_SESSION",
  "driver": "redis",
  "redis": {
    "host": "127.0.0.1",
    "port": 6379,
    "database": 5,
    "prefix": "jhsaqshi_sess_",
    "timeout_seconds": 1,
    "read_timeout_seconds": 1,
    "persistent": true,
    "password_env": "SAQSHI_REDIS_PASSWORD"
  }
}
```

`password_env` is the *name* of an optional Windows environment variable; it is not the password itself. If Memurai does not require authentication, leave the environment variable unset.

If authentication is enabled, set the password outside source code. For example, create a system environment variable named `SAQSHI_REDIS_PASSWORD`, then restart IIS so PHP can read it.

For a new deployment, choose a cookie name, Redis database and prefix that are unique to that deployment. Do not copy the repository values unchanged into an environment that shares Memurai with another SaQshi installation.

## PHP `php.ini`

`api/core/SessionManager.php` applies the SaQshi session configuration before it calls `session_start()`. It also sets strict cookie-only handling, `HttpOnly`, `SameSite=Strict`, HTTPS-only cookies when HTTPS is detected, session timeout handling and periodic session-ID regeneration. Do **not** set an application-specific `session.save_path` in a shared `php.ini` when the same PHP runtime serves multiple applications.

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
| SaQshi (current repository example) | `SAQSHI_JH_SESSION` | `5` | `jhsaqshi_sess_` |
| Other application | `OTHERAPP_SESSION` | `3` | `otherapp_sess_` |

Never share the same cookie name and key prefix between unrelated applications.

## Verify session performance

Check Memurai health and memory usage:

```powershell
& "C:\Program Files\Memurai\memurai-cli.exe" ping
& "C:\Program Files\Memurai\memurai-cli.exe" info memory
```

During a SaQshi login, session reads and writes should be only a few milliseconds on a healthy local Memurai server. If login is still slow, check database queries, password hashing, external captcha/notification calls, and synchronous audit logging rather than assuming Redis is the bottleneck.

## Deployment verification

Run these checks in a non-production or approved maintenance window:

```powershell
php -m | Select-String redis
Get-Content api/config/session.json
& "C:\Program Files\Memurai\memurai-cli.exe" ping
& "C:\Program Files\Memurai\memurai-cli.exe" info memory
```

Then sign in through HTTPS, refresh an authenticated page, sign out and sign in again. Confirm that the configured cookie name is present, has `HttpOnly` and `SameSite=Strict`, and has the `Secure` flag under HTTPS. Do not copy cookie values into tickets or logs.

If Redis is unavailable, check the application/PHP error log for `Redis session start failed; using file-session fallback.` Treat this as an operational alert. Restore Memurai and then confirm new authenticated sessions use the intended Redis configuration. Do not restart or flush a shared Redis database without the service owner's approval.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Login fails after enabling Redis | Confirm the PHP Redis extension and the Memurai service are running. |
| Session is not retained | Confirm cookie name, browser cookie settings, and the configured Redis database/prefix. |
| Connection refused | Verify host/port and Windows Firewall; test `memurai-cli.exe ping`. |
| Two applications log each other out | Use separate cookie names and prefixes/databases. |
| Production password needed | Set `SAQSHI_REDIS_PASSWORD` as a machine environment variable; do not commit it to JSON or `php.ini`. |
| Redis extension is missing | SaQshi cannot configure Redis storage; enable the matching PHP Redis extension and restart IIS. |
| Redis temporarily unavailable | SaQshi may use its protected local file-session fallback; investigate service, firewall, credentials and PHP logs, then restore Redis. |
| User sees repeated logouts after HTTPS change | Confirm reverse-proxy/IIS HTTPS detection, cookie `Secure` behavior, hostname and cookie name before clearing browser cookies. |
