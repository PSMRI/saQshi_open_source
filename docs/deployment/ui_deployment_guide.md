# UI Deployment Guide

This guide explains how to deploy and verify the SaQshi web UI. The API deployment guide covers PHP, database and server setup; this page focuses on the browser application under `ui/`.

## UI Runtime Model

SaQshi UI is a static HTML, CSS and JavaScript application. It does not need a frontend build step.

| Area | Purpose |
| --- | --- |
| `ui/login.html` | Login entry page. |
| `ui/dashboard.html` | Main authenticated shell. |
| `ui/assets/` | Shared CSS, JavaScript, images, icons and design system files. |
| `ui/components/` | Header, sidebar, footer, loader, modal, notification and chat components. |
| `ui/layouts/` | Layout templates used by the shell. |
| `ui/pages/` | Feature pages. Most pages have `.html`, `.js`, `.css` and `.json` files. |
| `ui/config/app.json` | UI-level application configuration. |

## Current UI Modules

| Module | Main Files | Purpose |
| --- | --- | --- |
| Login | `ui/login.html`, `ui/pages/login/*` | Authentication, captcha and encrypted password transport. |
| Facility assessment | `ui/pages/assessment/*` | Assessment creation, department activation, assessor info and checklist entry. |
| CQI | `ui/pages/cqi/*` | Gap analysis, action plan and closure. |
| Performance | `ui/pages/performance/*` | KPI/outcome entry, dashboard and trend views. |
| Certification | `ui/pages/certification/*` | Facility certification views. |
| State monitoring | `ui/pages/state/*` | State/district/division/block dashboards, reports and monitoring pages. |
| Assessor assignment | `ui/pages/state/assessors.*`, `ui/pages/assessor/*` | State assessor profile/mapping and assessor facility selection. |
| Facility user administration | `ui/pages/facilityusers/*` | My Profile and Facility Profile pages. |
| Help/GitBook | `ui/help/*`, `gitbook.html` | User and developer documentation. |

The dashboard shell loads pages through the client-side router. Page metadata is kept in each page JSON manifest, for example:

```text
ui/pages/assessment/checklist.html
ui/pages/assessment/checklist.js
ui/pages/assessment/checklist.css
ui/pages/assessment/checklist.json
```

## Recommended Deployment Pattern

Deploy `ui/` and `api/` under the same web root and same domain:

```text
{main_url}/ui/login.html
{main_url}/ui/dashboard.html
{main_url}/api/auth/v1/csrf.php
```

This same-origin pattern is recommended because SaQshi uses browser sessions, CSRF protection and API calls that expect the UI and API to share the same scheme, host and port.

Avoid opening UI files directly from the filesystem. Use a web server URL so JavaScript, JSON manifests, components and API calls resolve correctly.

## Deployment Steps

1. Copy the full project to the server application root.
2. Confirm these folders exist in the deployed root:

```text
api/
ui/
docs/
tools/
scripts/
```

3. Configure the web server document root to the project root.
4. Confirm static files are served:

```text
{main_url}/ui/login.html
{main_url}/ui/assets/js/app.js
{main_url}/ui/config/app.json
```

5. Confirm API files are served through PHP:

```text
{main_url}/api/auth/v1/csrf.php
```

6. Login with a test user.
7. Confirm role-based menus load correctly.
8. Open a few routed pages, then refresh the browser to ensure the page still loads.
9. Check browser developer console for missing CSS, JS, JSON, image or API files.

## Routing and Refresh

SaQshi uses a dashboard shell plus query-string routes. A typical routed page looks like:

```text
{main_url}/ui/dashboard.html?route=assessment/checklist
```

The server should serve `ui/dashboard.html` normally. No special SPA rewrite rule is required for the default query-string routing model.

If a developer adds path-style routing in the future, the server must be configured to fall back to `ui/dashboard.html` for UI paths while still allowing `api/*.php` to execute normally.

## Adding or Deploying a New UI Page

For each new page, deploy all four files when applicable:

```text
page-name.html
page-name.js
page-name.css
page-name.json
```

Before release, verify:

| Check | Expected Result |
| --- | --- |
| Page manifest | JSON is valid and referenced by the router. |
| CSS path | Page CSS loads without 404. |
| JS path | Page JS loads without 404. |
| API path | API endpoints use the correct `{main_url}/api/...` route. |
| Sidebar | Link is visible only for intended roles. |
| Browser refresh | Route reloads without 404. |
| Theme | Light and dark modes keep readable text and buttons. |
| Accessibility | Keyboard focus, labels and screen-reader text are usable. |
| Friendly errors | API failures show user-friendly messages, not PHP/database errors. |

## Assessor UI Deployment Notes

The assessor workflow uses the same dashboard shell and router as other pages.
Deploy these files together:

```text
ui/pages/state/assessors.html
ui/pages/state/assessors.js
ui/pages/state/assessors.css
ui/pages/state/assessors.json
ui/pages/assessor/dashboard.html
ui/pages/assessor/dashboard.js
ui/pages/assessor/dashboard.css
ui/pages/assessor/dashboard.json
ui/pages/assessor/facilities.html
ui/pages/assessor/facilities.json
```

Also deploy the shared sidebar and dashboard shell changes:

```text
ui/components/sidebar/sidebar.html
ui/components/sidebar/sidebar.js
ui/dashboard.html
```

After deployment:

1. Login as a state user and confirm `Assessor Management` appears.
2. Create or edit an assessor profile. If linked user ID is blank, verify a login user is created automatically.
3. Map at least one facility.
4. Login as the assessor user.
5. If a temporary password is used, verify My Profile forces password change.
6. Confirm only assessor pages, profile, help and chat are visible.
7. Start a mapped facility assessment and verify the route opens department
   activation or assessor info according to department count.

## Apache Notes

Use the project root as the virtual host document root.

Example:

```apache
DocumentRoot "/var/www/saqshi"

<Directory "/var/www/saqshi">
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>

<Files ".env">
    Require all denied
</Files>
```

Confirm Apache serves `.json`, `.css`, `.js`, `.svg`, `.png`, `.jpg`, `.woff` and `.woff2` files with normal static MIME types.

## IIS Notes

Recommended IIS checks:

- Enable Static Content.
- Configure PHP through FastCGI for `api/*.php`.
- Set `ui/login.html` as an optional default document if required.
- Add MIME mappings for `.json`, `.svg`, `.woff`, `.woff2`, `.md`, `.yaml` and `.yml` where needed.
- Disable directory browsing.
- Deny public access to `.env`, server logs, backups and private storage folders.
- Ensure upload and log folders have write permission for the application pool identity.

## Nginx Notes

Use the project root as `root`. Serve static UI files directly and route PHP to PHP-FPM.

Example outline:

```nginx
server {
    listen 80;
    server_name example.org;
    root /var/www/saqshi;
    index ui/login.html;

    location /ui/ {
        try_files $uri =404;
    }

    location /docs/ {
        try_files $uri =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
    }

    location ~ /\.(env|git) {
        deny all;
    }
}
```

## Cloud Deployment Notes

For a VM-based cloud deployment, deploy UI and API together on the same web server:

```text
Users -> HTTPS domain -> Apache/Nginx/IIS -> ui/ static files
Users -> HTTPS domain -> Apache/Nginx/IIS -> api/ PHP endpoints -> MySQL/MariaDB
```

For advanced deployments, `ui/` may be served from a CDN or object storage, but only after confirming:

- API URL configuration is explicit and correct.
- Session cookies and CSRF flow work across domains.
- CORS policy is restricted to approved origins.
- API responses are not cached publicly.
- Private pages are not exposed as public data.

The default recommended production pattern remains one domain with UI and API under the same `{main_url}`.

## Cache and Versioning

Some UI assets use query-string versions such as:

```text
header.js?v=20260704-5
```

When deploying UI changes:

1. Replace changed files.
2. Bump asset query versions where the application already uses them.
3. Clear server/CDN cache if enabled.
4. Ask testers to hard-refresh once after a release if stale browser cache is suspected.

## Security Checklist

| Check | Requirement |
| --- | --- |
| HTTPS | Required for production. |
| Same-origin | Recommended for session and CSRF reliability. |
| Directory listing | Disabled. |
| `.env` | Not downloadable. |
| Logs/backups | Not served publicly. |
| Upload storage | Access controlled and validated through API. |
| API errors | Friendly JSON responses, no raw database/PHP errors. |
| Role menus | Sidebar and page access follow role permissions. |
| Browser cache | Sensitive API responses are not cached publicly. |

## Troubleshooting

| Symptom | Likely Cause | Fix |
| --- | --- | --- |
| Login page opens but styling is missing | Static CSS path or MIME issue | Check `ui/assets/css` paths and web server static file support. |
| Page shows 404 after refresh | Wrong direct URL or missing dashboard shell route | Use `{main_url}/ui/dashboard.html?route=...` and verify router links. |
| API returns network error | UI and API are on different origins or PHP is not configured | Confirm `{main_url}/api/auth/v1/csrf.php` opens and same-origin deployment is used. |
| Sidebar link opens plain HTML without layout | Link bypasses the dashboard router | Link pages through the route system, not direct `ui/pages/...` URLs. |
| Page loads but data is blank | API failed or role/facility context missing | Check browser network tab and API response JSON. |
| Dark theme text is unreadable | Page CSS is overriding theme variables | Use shared theme variables and test both themes. |
| New page has no CSS/JS | Page manifest missing or path mismatch | Validate the page `.json` manifest and file names. |

## Release Verification

Before marking a UI deployment complete, verify these URLs:

```text
{main_url}/ui/login.html
{main_url}/ui/dashboard.html
{main_url}/ui/help/documentation.html
{main_url}/docs/api/swagger-ui.html
{main_url}/api/auth/v1/csrf.php
```

Then verify at least one page from each major module:

- Assessment
- CQI
- Performance Monitoring
- Reports
- State Monitoring
- Facility User Profile
- Documentation
