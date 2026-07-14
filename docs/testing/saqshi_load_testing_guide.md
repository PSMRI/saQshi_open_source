# SaQshi Load Testing Guide

Version: 1.0  
Updated: 2026-07-13

## Can We Do Load Testing?

Yes. SaQshi APIs and UI routes can be load tested, but heavy load testing should be done only on a dedicated test environment, not on production and not on a developer machine with live data.

This repository includes a lightweight Node.js runner:

```text
scripts/load-test/saqshi-load-test.js
```

It does not need npm packages. It uses Node's built-in `http` and `https` modules.

## What to Test

Recommended load-test areas:

| Area | Endpoint / Page | Why |
|---|---|---|
| Public auth helpers | `/api/auth/v1/csrf.php`, `/api/auth/v1/captcha.php` | Baseline API speed |
| Login | `/api/auth/v1/login.php` | Auth capacity, but avoid brute-force style tests |
| Dashboard | `/api/assessment/v1/dashboard_insights.php` | Facility landing-page load |
| State monitoring | `/api/state/v1/dashboard.php` | Large facility counts and summaries |
| Certification status | `/api/state/v1/certification_summary.php` | Pagination and state-level data |
| Assessment progress | `/api/state/v1/assessment_progress.php` | Multi-assessment summary |
| CQI summary | `/api/state/v1/cqi_summary.php` | Gap/action-plan summary |
| Reports | `/api/state/v1/reports.php?download=summary` | Report-generation timeout risk |

## Safe Local Smoke Test

Use a very small test first:

```text
node scripts/load-test/saqshi-load-test.js --url http://localhost:94/api/auth/v1/csrf.php --duration 10 --concurrency 3
```

This checks whether the server can handle basic API requests without stressing it.

## Moderate Test

After smoke test passes:

```text
node scripts/load-test/saqshi-load-test.js --url http://localhost:94/api/auth/v1/csrf.php --duration 60 --concurrency 20
```

## Multiple URL Test

Workers rotate through comma-separated URLs:

```text
node scripts/load-test/saqshi-load-test.js --urls http://localhost:94/api/auth/v1/csrf.php,http://localhost:94/api/auth/v1/captcha.php --duration 30 --concurrency 10
```

## Authenticated API Test

For authenticated endpoints, first login in Postman or browser and copy the session cookie.

Then run:

```text
node scripts/load-test/saqshi-load-test.js --url http://localhost:94/api/state/v1/dashboard.php --duration 30 --concurrency 10 --cookie "SAQSHI_SESSION=your-session-cookie"
```

If the API needs CSRF for POST:

```text
node scripts/load-test/saqshi-load-test.js --url http://localhost:94/api/performance/v1/kpi_save.php --method POST --header "X-CSRF-TOKEN: your-token" --cookie "SAQSHI_SESSION=your-session-cookie" --body "{\"indicator_id\":\"KPI_001\",\"department_id\":25,\"entry_month\":7,\"entry_year\":2026,\"numerator\":10,\"denominator\":20,\"result\":50}" --duration 20 --concurrency 3
```

Use POST load tests carefully because they can create/update data repeatedly.

## Result Output

Results are saved automatically under:

```text
docs/testing/load_test_results/
```

Each result contains:

- Total requests
- Requests per second
- Failure count
- Failure rate
- Status code counts
- Latency min/average/p50/p90/p95/p99/max
- Sample request results

## Acceptance Targets

Suggested initial targets for local or test server:

| Test Type | Target |
|---|---|
| Smoke API | Failure rate `0%`, p95 below `1000 ms` |
| Authenticated dashboard | Failure rate below `1%`, p95 below `2000 ms` |
| State monitoring list | Failure rate below `1%`, p95 below `3000 ms` |
| Report download | No PHP timeout, no 500 error |

These are starting targets. Final targets should be based on expected real users and server hardware.

## Important Safety Rules

- Do not run high concurrency tests on production.
- Do not run POST load tests against real data unless the payload is safe and reversible.
- Start with low concurrency, then increase gradually.
- Watch PHP error logs, MySQL CPU, memory and slow query logs.
- For 50k+ facilities, always test pagination endpoints and report downloads separately.

## Recommended Test Levels

| Level | Duration | Concurrency | Purpose |
|---|---:|---:|---|
| Smoke | 10 seconds | 3 | Check server responds |
| Baseline | 60 seconds | 10 | Normal local performance |
| Moderate | 120 seconds | 25 | Multi-user test server load |
| Stress | 300 seconds | 50+ | Dedicated test environment only |

## Common Findings and Fix Direction

| Symptom | Likely Cause | Fix Direction |
|---|---|---|
| High p95/p99 latency | Slow query or large JSON processing | Add pagination, indexes, caching |
| Many 500 responses | PHP fatal/error under concurrency | Check PHP logs and friendly error log |
| Many 401/403 responses | Missing session/CSRF | Pass cookie and CSRF header |
| Browser freezes on state pages | Too many rows rendered | Use server-side pagination |
| Report timeout | Large Excel generation | Queue report, stream file, or optimize query |

## Initial Smoke Result

Executed on: 2026-07-13

Command:

```text
node scripts\load-test\saqshi-load-test.js --url http://localhost:94/api/auth/v1/csrf.php --duration 10 --concurrency 3
```

Result file:

```text
docs\testing\load_test_results\load-test-1783959747491.json
```

Summary:

| Metric | Result |
|---|---:|
| Total requests | 807 |
| Requests per second | 80.54 |
| Failures | 0 |
| Failure rate | 0% |
| HTTP 200 responses | 807 |
| Average latency | 36.59 ms |
| p50 latency | 24.36 ms |
| p90 latency | 51.19 ms |
| p95 latency | 67.49 ms |
| p99 latency | 271.71 ms |
| Max latency | 1401.93 ms |

Smoke result: Passed.
