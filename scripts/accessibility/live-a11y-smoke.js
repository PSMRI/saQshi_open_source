#!/usr/bin/env node
/*
 * Live accessibility smoke test for reachable SaQshi URLs.
 *
 * This is not a replacement for Playwright/axe or real screen-reader testing.
 * It verifies that the deployed application is reachable and that key
 * accessibility markers exist in live-served files.
 */

const fs = require("fs");
const http = require("http");
const https = require("https");
const path = require("path");

const baseUrl = (process.argv[2] || "http://localhost").replace(/\/+$/, "");
const outputPath = path.join(process.cwd(), "docs", "testing", "wcag_live_smoke_results.json");

async function get(url) {
  return new Promise((resolve, reject) => {
    const client = url.startsWith("https://") ? https : http;
    const request = client.get(url, {
      headers: {
        "Accept": "text/html,application/json;q=0.9,*/*;q=0.8"
      },
      timeout: 8000
    }, response => {
      let text = "";
      response.setEncoding("utf8");
      response.on("data", chunk => {
        text += chunk;
      });
      response.on("end", () => {
        resolve({
          url,
          status: response.statusCode || 0,
          ok: (response.statusCode || 0) >= 200 && (response.statusCode || 0) < 300,
          contentType: response.headers["content-type"] || "",
          text
        });
      });
    });

    request.on("timeout", () => {
      request.destroy(new Error(`Request timed out: ${url}`));
    });
    request.on("error", reject);
  });
}

function pass(name, ok, details = "") {
  return {
    name,
    status: ok ? "pass" : "fail",
    details
  };
}

function includesAll(text, markers) {
  return markers.every(marker => text.includes(marker));
}

async function main() {
  const results = {
    checked_at: new Date().toISOString(),
    base_url: baseUrl,
    checks: []
  };

  const login = await get(`${baseUrl}/ui/login.html`);
  results.checks.push(pass("Login shell reachable", login.ok, `HTTP ${login.status}`));
  results.checks.push(pass("Login shell has language", /<html[^>]+lang=["']en["']/i.test(login.text), "Expected html lang=en"));
  results.checks.push(pass("Login shell loads runtime a11y helper", login.text.includes("/ui/assets/js/core/a11y.js"), "Expected a11y.js script"));

  const loginFragment = await get(`${baseUrl}/ui/pages/login/login.html`);
  results.checks.push(pass("Login fragment reachable", loginFragment.ok, `HTTP ${loginFragment.status}`));
  results.checks.push(pass(
    "Login captcha has accessible help",
    includesAll(loginFragment.text, ["captchaHelp", "aria-describedby=\"captchaHelp captchaQuestion\"", "aria-live=\"polite\""]),
    "Expected captcha help text, aria-describedby and aria-live"
  ));

  const dashboard = await get(`${baseUrl}/ui/dashboard.html`);
  results.checks.push(pass("Dashboard shell reachable", dashboard.ok, `HTTP ${dashboard.status}`));
  results.checks.push(pass("Dashboard shell has language", /<html[^>]+lang=["']en["']/i.test(dashboard.text), "Expected html lang=en"));
  results.checks.push(pass("Dashboard shell loads runtime a11y helper", dashboard.text.includes("/ui/assets/js/core/a11y.js"), "Expected a11y.js script"));

  const captcha = await get(`${baseUrl}/api/auth/v1/captcha.php`);
  let captchaJson = null;
  try {
    captchaJson = JSON.parse(captcha.text);
  } catch (error) {
    // handled by check below
  }

  const question = String(captchaJson?.data?.question || "");
  results.checks.push(pass("Captcha API reachable", captcha.ok, `HTTP ${captcha.status}`));
  results.checks.push(pass("Captcha API returns text math question", /\d+\s*\+\s*\d+\s*=\s*\?/.test(question), question || "No question"));

  const failed = results.checks.filter(check => check.status !== "pass");
  results.summary = {
    total: results.checks.length,
    passed: results.checks.length - failed.length,
    failed: failed.length
  };

  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, JSON.stringify(results, null, 2));

  console.log(JSON.stringify(results, null, 2));
  process.exit(failed.length ? 1 : 0);
}

main().catch(error => {
  console.error(error);
  process.exit(1);
});
