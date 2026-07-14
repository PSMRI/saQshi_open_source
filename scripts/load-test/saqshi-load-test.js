#!/usr/bin/env node
/*
 * ==========================================================
 * SaQshi Open Source
 * Lightweight API Load Test Runner
 * saqshi-load-test.js
 * ==========================================================
 */

const fs = require("fs");
const http = require("http");
const https = require("https");
const path = require("path");
const { performance } = require("perf_hooks");

function parseArgs(argv) {
    const args = {
        urls: ["http://localhost:94/api/auth/v1/csrf.php"],
        duration: 15,
        concurrency: 5,
        method: "GET",
        timeout: 15000,
        headers: {},
        body: "",
        output: path.join("docs", "testing", "load_test_results", `load-test-${Date.now()}.json`)
    };

    for (let i = 2; i < argv.length; i += 1) {
        const key = argv[i];
        const value = argv[i + 1];

        if (key === "--url" && value) {
            args.urls = [value];
            i += 1;
        } else if (key === "--urls" && value) {
            args.urls = value.split(",").map((item) => item.trim()).filter(Boolean);
            i += 1;
        } else if (key === "--duration" && value) {
            args.duration = Math.max(1, Number(value) || args.duration);
            i += 1;
        } else if (key === "--concurrency" && value) {
            args.concurrency = Math.max(1, Number(value) || args.concurrency);
            i += 1;
        } else if (key === "--method" && value) {
            args.method = String(value).toUpperCase();
            i += 1;
        } else if (key === "--timeout" && value) {
            args.timeout = Math.max(1000, Number(value) || args.timeout);
            i += 1;
        } else if (key === "--header" && value) {
            const splitAt = value.indexOf(":");
            if (splitAt > 0) {
                args.headers[value.slice(0, splitAt).trim()] = value.slice(splitAt + 1).trim();
            }
            i += 1;
        } else if (key === "--cookie" && value) {
            args.headers.Cookie = value;
            i += 1;
        } else if (key === "--body" && value) {
            args.body = value;
            i += 1;
        } else if (key === "--output" && value) {
            args.output = value;
            i += 1;
        } else if (key === "--help") {
            printHelp();
            process.exit(0);
        }
    }

    return args;
}

function printHelp() {
    console.log(`
SaQshi Load Test Runner

Usage:
  node scripts/load-test/saqshi-load-test.js --url http://localhost:94/api/auth/v1/csrf.php --duration 30 --concurrency 10

Options:
  --url <url>             Single URL to test
  --urls <a,b,c>          Comma-separated URLs; workers rotate across them
  --duration <seconds>    Test duration, default 15
  --concurrency <number>  Number of parallel workers, default 5
  --method <GET|POST>     HTTP method, default GET
  --header "K: V"         Add header, can be repeated
  --cookie "name=value"   Add Cookie header
  --body <json/text>      Request body for POST/PUT
  --timeout <ms>          Per-request timeout, default 15000
  --output <path>         JSON result output path
`);
}

function percentile(values, p) {
    if (!values.length) return 0;
    const sorted = [...values].sort((a, b) => a - b);
    const index = Math.ceil((p / 100) * sorted.length) - 1;
    return sorted[Math.max(0, Math.min(sorted.length - 1, index))];
}

function requestOnce(urlText, args) {
    return new Promise((resolve) => {
        const started = performance.now();
        const url = new URL(urlText);
        const client = url.protocol === "https:" ? https : http;
        const body = args.body || "";
        const headers = { ...args.headers };

        if (body && !headers["Content-Type"]) {
            headers["Content-Type"] = "application/json";
        }

        if (body) {
            headers["Content-Length"] = Buffer.byteLength(body);
        }

        const req = client.request({
            protocol: url.protocol,
            hostname: url.hostname,
            port: url.port,
            path: `${url.pathname}${url.search}`,
            method: args.method,
            headers,
            timeout: args.timeout
        }, (res) => {
            let bytes = 0;

            res.on("data", (chunk) => {
                bytes += chunk.length;
            });

            res.on("end", () => {
                resolve({
                    ok: res.statusCode >= 200 && res.statusCode < 500,
                    statusCode: res.statusCode,
                    latencyMs: performance.now() - started,
                    bytes
                });
            });
        });

        req.on("timeout", () => {
            req.destroy(new Error("Request timeout"));
        });

        req.on("error", (error) => {
            resolve({
                ok: false,
                statusCode: 0,
                latencyMs: performance.now() - started,
                error: error.message,
                bytes: 0
            });
        });

        if (body) {
            req.write(body);
        }

        req.end();
    });
}

async function runWorker(id, args, until, results) {
    let index = id;

    while (performance.now() < until) {
        const url = args.urls[index % args.urls.length];
        const result = await requestOnce(url, args);
        result.url = url;
        results.push(result);
        index += args.concurrency;
    }
}

async function main() {
    const args = parseArgs(process.argv);
    const startedAt = new Date();
    const start = performance.now();
    const until = start + args.duration * 1000;
    const results = [];

    console.log(`SaQshi load test started`);
    console.log(`URLs: ${args.urls.join(", ")}`);
    console.log(`Duration: ${args.duration}s, concurrency: ${args.concurrency}, method: ${args.method}`);

    await Promise.all(
        Array.from({ length: args.concurrency }, (_, index) => runWorker(index, args, until, results))
    );

    const durationSeconds = (performance.now() - start) / 1000;
    const latencies = results.map((item) => item.latencyMs);
    const failures = results.filter((item) => !item.ok);
    const statusCounts = {};
    const errorCounts = {};

    results.forEach((item) => {
        statusCounts[item.statusCode] = (statusCounts[item.statusCode] || 0) + 1;
        if (item.error) {
            errorCounts[item.error] = (errorCounts[item.error] || 0) + 1;
        }
    });

    const summary = {
        started_at: startedAt.toISOString(),
        finished_at: new Date().toISOString(),
        duration_seconds: Number(durationSeconds.toFixed(3)),
        urls: args.urls,
        method: args.method,
        concurrency: args.concurrency,
        total_requests: results.length,
        requests_per_second: Number((results.length / durationSeconds).toFixed(2)),
        failures: failures.length,
        failure_rate_percent: results.length ? Number(((failures.length / results.length) * 100).toFixed(2)) : 0,
        latency_ms: {
            min: Number((Math.min(...latencies) || 0).toFixed(2)),
            avg: Number((latencies.reduce((sum, value) => sum + value, 0) / Math.max(1, latencies.length)).toFixed(2)),
            p50: Number(percentile(latencies, 50).toFixed(2)),
            p90: Number(percentile(latencies, 90).toFixed(2)),
            p95: Number(percentile(latencies, 95).toFixed(2)),
            p99: Number(percentile(latencies, 99).toFixed(2)),
            max: Number((Math.max(...latencies) || 0).toFixed(2))
        },
        status_counts: statusCounts,
        error_counts: errorCounts
    };

    fs.mkdirSync(path.dirname(args.output), { recursive: true });
    fs.writeFileSync(args.output, JSON.stringify({ summary, sample_results: results.slice(0, 25) }, null, 2));

    console.log(JSON.stringify(summary, null, 2));
    console.log(`Result saved: ${args.output}`);

    if (summary.failure_rate_percent > 5) {
        process.exitCode = 1;
    }
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
