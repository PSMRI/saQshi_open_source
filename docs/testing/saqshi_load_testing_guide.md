# SaQshi Load Testing Guide

Version: 1.1
Updated: 2026-08-26

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
| Public landing | `/`, `/education-index.html` | Verify profile-aware landing routes remain responsive and do not fall back to the login shell. |
| Documentation | `/gitbook.html` | Verify the documentation reader remains responsive when many users open guidance. |
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
node scripts/load-test/saqshi-load-test.js --url {main_url}/api/auth/v1/csrf.php --duration 10 --concurrency 3
```

This checks whether the server can handle basic API requests without stressing it.

For the public pages changed in this release, use a separate, low-concurrency
GET-only check on a local or dedicated test server:

```text
node scripts/load-test/saqshi-load-test.js --urls {main_url}/,{main_url}/gitbook.html --duration 10 --concurrency 2
```

Do not include an education redirect in this mixed test unless the test server
is configured with the education profile. Validate profile redirects separately
with a browser check.

## Moderate Test

After smoke test passes:

```text
node scripts/load-test/saqshi-load-test.js --url {main_url}/api/auth/v1/csrf.php --duration 60 --concurrency 20
```

## Multiple URL Test

Workers rotate through comma-separated URLs:

```text
node scripts/load-test/saqshi-load-test.js --urls {main_url}/api/auth/v1/csrf.php,{main_url}/api/auth/v1/captcha.php --duration 30 --concurrency 10
```

## Authenticated API Test

For authenticated endpoints, first login in Postman or browser and copy the session cookie.

Then run:

```text
node scripts/load-test/saqshi-load-test.js --url {main_url}/api/state/v1/dashboard.php --duration 30 --concurrency 10 --cookie "SAQSHI_SESSION=your-session-cookie"
```

If the API needs CSRF for POST:

```text
node scripts/load-test/saqshi-load-test.js --url {main_url}/api/performance/v1/kpi_save.php --method POST --header "X-CSRF-TOKEN: your-token" --cookie "SAQSHI_SESSION=your-session-cookie" --body "{\"indicator_id\":\"KPI_001\",\"department_id\":25,\"entry_month\":7,\"entry_year\":2026,\"numerator\":10,\"denominator\":20,\"result\":50}" --duration 20 --concurrency 3
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

### How the Runner Counts Failures

The bundled runner counts transport errors and HTTP `500+` responses as
failures. It records HTTP `401`, `403`, `404` and `405` in the status-count
output but does not treat them as failures by itself. Always inspect
`status_counts` before accepting a result:

- Public GET checks should return only intended `200` responses.
- Protected endpoint checks must use a valid session cookie; unexpected `401`
  or `403` responses invalidate the test run.
- Method-guard checks are functional/security tests, not load tests; the
  expected result for an unsupported method can be `405`.

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
- Never use login endpoints for sustained credential attempts; use a dedicated
  test account only for a small, rate-limited functional check.
- Do not include session cookies, CSRF tokens, passwords or request bodies in
  committed result files, tickets or GitBook documentation.
- Keep public-page and GitBook checks GET-only. They do not require a session
  and must not be mixed with state-changing requests.

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
node scripts\load-test\saqshi-load-test.js --url {main_url}/api/auth/v1/csrf.php --duration 10 --concurrency 3
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

## Latest Public Route Verification

Executed on: 2026-08-26

This was a focused availability and regression check, not a load run. The
following routes returned HTTP `200` on `http://localhost:94`:

| Route | Result |
|---|---|
| `/` | Profile-aware healthcare landing page served |
| `/gitbook.html` | GitBook reader served |
| `/ui/login.html` | Login shell served |
| `/api/auth/v1/captcha.php` | Captcha JSON served |

The detailed release revalidation is recorded in
`docs/testing/public_pages_gitbook_revalidation_2026_08_26.md`.
