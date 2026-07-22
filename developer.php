<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SaQshi is an open-source healthcare quality assessment and monitoring platform.">
    <title>SaQshi | Open-source healthcare quality</title>
    <link rel="stylesheet" href="/ui/assets/css/saqshi-developer.css">
</head>
<body>
<header class="site-header">
    <nav class="container nav">
        <a class="brand" href="developer.php"><span class="mark">S</span>SaQshi <span class="badge">Open source</span></a>
        <div class="links">
            <a href="#platform">Platform</a>
            <a href="#evolution">Journey</a>
            <a href="gitbook.html?doc=docs%2Fuser%2Fuser_guide.md">User Guide</a>
            <a href="#developers">Developers</a>
            <a href="#documentation">Documentation</a>
            <a href="gitbook.html">GitBook</a>
        </div>
        <a class="button primary" href="login.php">Open application</a>
    </nav>
</header>

<main>
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <div class="eyebrow">Healthcare quality, made measurable</div>
                <h1>Build better quality systems for every facility.</h1>
                <p class="lead">SaQshi is an open-source platform for healthcare quality assessment, continuous quality improvement, performance monitoring, certification tracking, and actionable reporting.</p>
                <div class="actions">
                    <a class="button primary" href="#documentation">Explore developer docs</a>
                    <a class="button secondary" href="gitbook.html?doc=docs%2Fuser%2Fuser_guide.md">Quick User Guide</a>
                    <a class="button secondary" href="gitbook.html">Open GitBook</a>
                    <a class="button secondary" href="gitbook.html?doc=docs%2Farchitecture%2Fproject_overview.md">Read project overview</a>
                </div>
            </div>
            <aside class="hero-panel">
                <div class="panel-top"><span>SaQshi API</span><span class="status">available</span></div>
                <pre class="code">POST /api/assessment/v1/create_assessment.php

{
  "assessment_name": "Internal Assessment",
  "framework_code": "saqshi-nqas",
  "start_date": "2026-07-14"
}

start a quality-improvement cycle</pre>
            </aside>
        </div>
    </section>

    <section id="platform" class="section white">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">One connected platform</div>
                <h2>From assessment to sustained improvement.</h2>
                <p>SaQshi connects the work of facility teams with the visibility needed by district, state, and programme administrators.</p>
            </div>
            <div class="grid">
                <article class="card"><div class="icon">01</div><h3>Assessment</h3><p>Create facility assessments, activate departments, score checkpoints, save evidence and track progress in one workflow.</p></article>
                <article class="card"><div class="icon">02</div><h3>Continuous improvement</h3><p>Turn assessment gaps into action plans, assign follow-up work, collect evidence and close improvement cycles.</p></article>
                <article class="card"><div class="icon">03</div><h3>Performance and certification</h3><p>Monitor indicators, KPIs, outcomes, trends and certification status across the healthcare system.</p></article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">How it fits together</div>
                <h2>A transparent quality journey.</h2>
            </div>
            <div class="journey">
                <div class="step"><span>01</span><h3>Configure</h3><p>Set up facility profiles, frameworks, departments and assessment periods.</p></div>
                <div class="step"><span>02</span><h3>Assess</h3><p>Capture checklist responses, scores, assessor details and supporting evidence.</p></div>
                <div class="step"><span>03</span><h3>Improve</h3><p>Prioritize gaps, manage action plans and verify closure.</p></div>
                <div class="step"><span>04</span><h3>Learn</h3><p>Use dashboards, reports and trends to guide quality decisions.</p></div>
            </div>
        </div>
    </section>

    <section id="evolution" class="section white">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">Evolution journey</div>
                <h2>From manual assessment to a configurable quality platform.</h2>
                <p>SaQshi has evolved from checklist digitisation into a modular quality platform covering assessment, CQI, performance monitoring, certification, state monitoring and reporting.</p>
            </div>
            <figure class="journey-image-card">
                <img src="ui/assets/images/saqshi-evolution-journey.png" alt="SaQshi Evolution Journey from legacy assessment to reusable platform">
            </figure>
        </div>
    </section>

    <section id="developers" class="section white">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">For developers</div>
                <h2>Designed to be understood, extended and deployed.</h2>
                <p>SaQshi uses a straightforward PHP and MySQL architecture with versioned APIs, shared service classes and JSON-driven configuration.</p>
            </div>
            <div class="resources">
                <a class="resource" href="gitbook.html"><div><h3>GitBook documentation</h3><p>Open the complete documentation sidebar for user, developer, API, testing and compliance guides.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Farchitecture%2Fproject_overview.md"><div><h3>Project overview and NQAS alignment</h3><p>Understand why SaQshi exists, how it maps to NQAS, and how assessment, CQI, performance, certification and state monitoring connect.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Farchitecture%2Fversion_matrix.md"><div><h3>Version matrix</h3><p>Compare MVP, V1 and V2/open-source capabilities before implementation or deployment planning.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Fuser%2Fuser_guide.md"><div><h3>User guide</h3><p>Read the application workflow guide for facility and monitoring users.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Farchitecture%2Ftechnical_architecture.md"><div><h3>Technical architecture overview</h3><p>View the platform, service, infrastructure and release architecture diagrams in one flow.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Farchitecture%2Fservice_map.md"><div><h3>Service architecture and map</h3><p>See the service architecture diagram and what each API service does.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Farchitecture%2Fhld_lld_design.md"><div><h3>HLD and LLD diagrams</h3><p>View the corrected high-level and low-level architecture diagrams with implementation notes.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Farchitecture%2Fconfiguration_formats.md"><div><h3>Configuration JSON formats</h3><p>Learn how to create facility master JSON and checklist/framework JSON safely.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Fdatabase%2Fdata_dictionary_erd.md"><div><h3>Data dictionary and ERD</h3><p>Review main tables, relationships and the first-pass entity relationship diagram.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Fdeployment%2Fdeployment_guide.md"><div><h3>Deployment guide</h3><p>Deploy SaQshi on IIS, Apache or Nginx with environment and permission checks.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="#architecture"><div><h3>Architecture guide</h3><p>Understand the frontend, API, core services, data configuration and database layers.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="#api"><div><h3>API reference</h3><p>Browse assessment, authentication, framework, performance, certification and state APIs.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="#setup"><div><h3>Local setup</h3><p>Set up PHP, MySQL, environment values and migrations for a development instance.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=CONTRIBUTING.md"><div><h3>Contribute</h3><p>Read the contribution guide and help make quality tooling more useful for healthcare teams.</p></div><span class="arrow">-&gt;</span></a>
            </div>
        </div>
    </section>

    <section id="documentation" class="section">
        <div class="container">
            <div class="section-heading">
                <div class="eyebrow">Developer documentation</div>
                <h2>Everything needed to work with SaQshi.</h2>
                <p>Use the <a href="gitbook.html">GitBook page</a> for the complete documentation map.</p>
            </div>
            <div id="architecture" class="doc-block">
                <h3>Architecture</h3>
                <p>Client pages call versioned API endpoints under <code>api/&lt;module&gt;/v1/</code>. Endpoints apply session, authentication and validation rules, use shared services or prepared database queries, and return a JSON response. Core utilities live in <code>api/core/</code>, business services in <code>api/service/</code>, and static configuration in <code>api/config/</code>.</p>
                <pre class="code-block">Client UI -> Versioned API endpoint -> Auth / Security / Session
          -> Service or prepared database query -> JSON response</pre>
            </div>
            <div id="setup" class="doc-block">
                <h3>Local setup</h3>
                <ol>
                    <li>Install PHP 8.x and MySQL or MariaDB.</li>
                    <li>Copy <code>.env.example</code> to <code>.env</code> and set database values.</li>
                    <li>Create/import the SaQshi database and apply SQL migrations from <code>api/sql/</code>.</li>
                    <li>Configure Apache, IIS or Nginx to serve the project root.</li>
                </ol>
            </div>
            <div id="api" class="resources">
                <a class="resource" href="gitbook.html?doc=docs%2Fapi%2FREADME.md"><div><h3>API documentation index</h3><p>How the API documentation is organized.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Fapi%2Fsource-reference.md"><div><h3>Complete API source reference</h3><p>All API endpoints, core classes, services and configuration files.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Fapi%2Fassessment.md"><div><h3>Assessment API guide</h3><p>Detailed assessment lifecycle, validation and extension guidance.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="docs/api/saqshi_postman_collection.json"><div><h3>Postman collection</h3><p>Importable API testing collection.</p></div><span class="arrow">-&gt;</span></a>
                <a class="resource" href="gitbook.html?doc=docs%2Fapi%2Fsaqshi_local_postman_environment.json"><div><h3>Postman environment</h3><p>Local API variables for base URL, CSRF, assessment, department and performance tests.</p></div><span class="arrow">-&gt;</span></a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container cta">
            <div>
                <h2>Start exploring SaQshi.</h2>
                <p>Whether you are implementing quality programmes, integrating systems, or extending the platform, this developer hub is your entry point.</p>
            </div>
            <a class="button secondary" href="login.php">Open application</a>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="container footer-row">
        <span>SaQshi - Open-source healthcare quality assessment and monitoring</span>
        <span><a href="gitbook.html">GitBook</a> - <a href="gitbook.html?doc=LICENSE">GPL-3.0</a> - <a href="gitbook.html?doc=SECURITY.md">Security</a> - <a href="gitbook.html?doc=CODE_OF_CONDUCT.md">Code of conduct</a></span>
    </div>
</footer>
</body>
</html>
