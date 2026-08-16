# Background worker deployment

SaQshi stores jobs durably in MySQL. The initial deployer chooses how workers are run; the queue data and application behaviour remain the same in every mode.

Run this once from the project root on Windows:

```powershell
powershell -ExecutionPolicy Bypass -File api/cli/configure-background-worker.ps1
```

| Mode | Use when | Runner |
| --- | --- | --- |
| `scheduled-task` | Small single-server installation | Runs one queue pass at the configured interval. |
| `windows-service` | A server needs continuous processing | Runs the PHP worker continuously through NSSM. |
| `redis-coordinated` | More than one app/worker server | Runs continuous workers with a Redis lease so only one holds the shared worker lock. |

The choice is saved in `api/config/background_worker.json`. To install the selected Scheduled Task or NSSM service, run the same command as Administrator with `-Apply`. Windows Service mode also needs the local path to `nssm.exe`:

```powershell
powershell -ExecutionPolicy Bypass -File api/cli/configure-background-worker.ps1 -Mode windows-service -NssmPath C:\tools\nssm.exe -Apply
```

Redis-coordinated mode uses the Redis connection in `api/config/cache.json`; set `SAQSHI_REDIS_PASSWORD` where applicable. Start its worker under the organisation's normal process manager:

```powershell
php api/cli/job_worker.php --daemon --redis-lock
```

All modes currently process the same MySQL `background_jobs` table. Redis is used only for distributed worker coordination, not as the source of truth, so queued work survives a Redis restart.
