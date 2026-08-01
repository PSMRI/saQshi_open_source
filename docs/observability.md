# Local log monitoring with Grafana

SaQshi can collect API and audit-event logs without Docker, a virtual machine, or changes to PHP session handling. The portable local stack uses:

```text
SaQshi API event logs -> Grafana Alloy -> Grafana Loki -> Grafana
```

Alloy reads `api/storage/events/*.log` asynchronously. It does not add work to the login request.

## Local installation

Open **Command Prompt** or **Git Bash** in the following folder:

```text
tools/observability
```

Run the PowerShell installer (do not paste the contents of the `.ps1` file into the terminal):

```cmd
powershell.exe -ExecutionPolicy Bypass -File ".\install-observability.ps1" -PublicAddress "127.0.0.1"
```

For a server, replace `127.0.0.1` with the server IP address or DNS name. The installer downloads the required official Windows archives, prepares the configuration, and starts the local processes without creating Windows services.

## Local URLs

| Component | URL | Purpose |
| --- | --- | --- |
| Grafana | `http://127.0.0.1:3300/` | Search and dashboards for SaQshi logs |
| Alloy | `http://127.0.0.1:12345/` | Collector status and diagnostics |
| Loki | `http://127.0.0.1:3100/ready` | Health check; returns `ready` |

Grafana uses port **3300** so it does not conflict with any other Grafana service using port 3000. On first login use `admin` / `admin`; Grafana requires a new password immediately.

## Viewing SaQshi logs

1. Open Grafana at port 3300 and sign in.
2. Open **Explore**.
3. Choose the preconfigured **SaQshi Logs** data source.
4. Run this query:

```logql
{job="saqshi"}
```

New event-log lines are collected automatically. Existing historical files may not appear until new events are written, because Alloy begins tailing files from their current end.

## Troubleshooting

If Grafana at port 3300 is unavailable, run the installer again and wait until it prints the three URLs. Do not use port 3000 unless it is intentionally configured for SaQshi.

If the installer reports an error, copy the terminal error text rather than the script source code. Check Loki first by opening `/ready`, then Alloy at port 12345.

## Kafka comparison

Kafka is an event-streaming broker, not a log-search interface. It can be added later when the same SaQshi event must feed multiple systems. For normal API/audit log monitoring, Alloy, Loki, and Grafana are lighter and provide search and dashboards directly.
