/*!
 * ==========================================================
 * SaQshi Open Source
 * Assessor Dashboard
 * dashboard.js
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */
(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    function esc(value) {
        return String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function status(row) {
        return row.assessment_status || (row.last_assessment_id ? "ACTIVE" : "Not started");
    }

    function moduleEnabled(modules, key) {
        return Boolean(modules?.modules?.[key]?.enabled);
    }

    function actionButton(row) {
        const action = row.next_action || {};
        const label = action.label || (row.last_assessment_id ? "Continue" : "Start");

        if (action.type === "disabled") {
            return `<button class="sq-btn sq-btn-muted" type="button" disabled>${esc(label)}</button>`;
        }

        if (action.type === "start" || action.type === "route") {
            return `<button class="sq-btn sq-btn-primary" type="button" data-start-facility="${esc(row.fac_id)}">${esc(label)}</button>`;
        }

        return `<button class="sq-btn sq-btn-muted" type="button" disabled>${esc(label)}</button>`;
    }

    function workflowHint(row) {
        const action = row.next_action || {};
        const parts = [];

        if (action.active_department_count !== undefined) parts.push(`${action.active_department_count} dept active`);
        if (action.assessor_info_count !== undefined) parts.push(`${action.assessor_info_count} assessor info`);
        if (action.response_count !== undefined) parts.push(`${action.response_count} responses`);

        return parts.length ? parts.join(" | ") : esc(action.state || "");
    }

    function renderModules(modules) {
        const roleModules = modules?.role_visibility?.assessor || [];
        const labels = roleModules
            .filter(key => moduleEnabled(modules, key))
            .map(key => modules.modules[key]?.label || key);

        return labels.length ? labels.join(", ") : "Assessment";
    }

    function render(data) {
        document.getElementById("assessorTotalFacilities").textContent = data.total_facilities || 0;
        document.getElementById("assessorActiveMappings").textContent = data.active_mappings || 0;
        document.getElementById("assessorName").textContent = data.assessor?.assessor_name || data.assessor?.assessor_code || "-";
        document.querySelector(".sq-assessor-toolbar p").textContent = `Configured modules: ${renderModules(data.modules || {})}`;

        const rows = data.facilities || [];
        document.getElementById("assessorFacilityRows").innerHTML = rows.length ? `
            <table class="sq-assessor-table">
                <thead><tr><th>Facility</th><th>Location</th><th>Type</th><th>Assessment</th><th>Next Step</th><th>Action</th></tr></thead>
                <tbody>${rows.map(row => `
                    <tr>
                        <td><strong>${esc(row.fac_name || "Facility " + row.fac_id)}</strong><small>NIN ${esc(row.fac_nin || "-")}</small></td>
                        <td>${esc(row.Dist_Name || "-")}<small>${esc(row.Block_Name || "")}</small></td>
                        <td>${esc(row.Health_facilty_type || "-")}</td>
                        <td>${esc(status(row))}<small>${esc(row.assessment_name || "")}</small></td>
                        <td>${esc(row.next_action?.label || "-")}<small>${esc(workflowHint(row))}</small></td>
                        <td>
                            <div class="sq-assessor-actions">
                                ${actionButton(row)}
                                <button class="sq-btn sq-btn-muted" type="button" data-detail-facility="${esc(row.fac_id)}">View</button>
                            </div>
                        </td>
                    </tr>`).join("")}</tbody>
            </table>` : `<div class="sq-assessor-empty">No facilities are mapped to this assessor profile.</div>`;
    }

    async function load() {
        const response = await SQ.api.get("/assessor/v1/dashboard.php", {}, { loader: false, showError: false });
        render(response.data || {});
    }

    async function startAssessment(facId) {
        try {
            const response = await SQ.api.post("/assessor/v1/start_assessment.php", {
                fac_id: Number(facId),
                framework_code: "saqshi-nqas"
            }, { loader: true, showError: false });
            const data = response.data || {};
            const nextAction = data.next_action || {};
            const route = nextAction.route || data.next_route || "assessment/departments";
            const assessmentId = data.assessment?.assessment_id || "";
            const params = Object.assign({ assessment_id: assessmentId }, nextAction.params || {});
            if (SQ.notification) SQ.notification.success(data.created ? "Assessment created." : "Assessment loaded.");
            SQ.router.navigate(route, params);
        } catch (error) {
            if (SQ.notification) SQ.notification.error(error.message || "Unable to start assessment.");
        }
    }

    function renderAssessments(rows) {
        if (!rows?.length) {
            return `<div class="sq-assessor-empty">No assessment history found for this facility.</div>`;
        }

        return `
            <table class="sq-assessor-table sq-assessor-summary-table">
                <thead><tr><th>Assessment</th><th>Status</th><th>Departments</th><th>Checklist</th><th>Score</th><th>Period</th></tr></thead>
                <tbody>${rows.map(row => `
                    <tr>
                        <td><strong>${esc(row.assessment_name || "Assessment " + row.assessment_id)}</strong><small>${esc(row.framework_code || "")}</small></td>
                        <td>${esc(row.status || "-")}</td>
                        <td>${esc(row.active_departments || 0)}</td>
                        <td>${esc(row.saved_checkpoints || 0)} / ${esc(row.total_checkpoints || 0)}</td>
                        <td>${esc(row.score_percent || 0)}%<small>${esc(row.obtained_score || 0)} / ${esc(row.max_score || 0)}</small></td>
                        <td>${esc(row.start_date || "-")}<small>${esc(row.end_date || "")}</small></td>
                    </tr>
                `).join("")}</tbody>
            </table>`;
    }

    function renderSummary(data) {
        const facility = data.facility || {};
        const modules = data.modules || {};
        const performance = data.performance || {};
        const blocks = [];
        const links = [];

        if (moduleEnabled(modules, "performance")) {
            if (moduleEnabled(modules, "kpi")) blocks.push(`<div><span>KPI Months</span><strong>${esc(performance.kpi_months || 0)}</strong></div>`);
            if (moduleEnabled(modules, "outcome")) blocks.push(`<div><span>Outcome Months</span><strong>${esc(performance.outcome_months || 0)}</strong></div>`);
            links.push(`<button class="sq-btn sq-btn-primary" type="button" data-summary-route="performance/dashboard" data-summary-params="${esc(JSON.stringify({ readonly: 1 }))}">Performance Dashboard</button>`);
            links.push(`<button class="sq-btn sq-btn-muted" type="button" data-summary-route="performance/trend" data-summary-params="${esc(JSON.stringify({ indicator_type: "OUTCOME", readonly: 1 }))}">Outcome Trend</button>`);
            if (moduleEnabled(modules, "kpi")) {
                links.push(`<button class="sq-btn sq-btn-muted" type="button" data-summary-route="performance/trend" data-summary-params="${esc(JSON.stringify({ indicator_type: "KPI", readonly: 1 }))}">KPI Trend</button>`);
            }
        }

        document.getElementById("assessorFacilitySummaryTitle").textContent = facility.fac_name || "Facility Details";
        document.getElementById("assessorFacilitySummaryMeta").textContent = `${facility.Dist_Name || "-"} / ${facility.Block_Name || "-"} / NIN ${facility.NIN_no || facility.fac_nin || "-"}`;
        document.getElementById("assessorFacilitySummaryBody").innerHTML = `
            <div class="sq-assessor-summary-grid">${blocks.join("") || `<div><span>Modules</span><strong>Assessment</strong></div>`}</div>
            <div class="sq-assessor-empty">Assessor access is read-only for KPI/outcome and CQI. Assessment entry remains the assessor responsibility.</div>
            ${links.length ? `<div class="sq-assessor-summary-links">${links.join("")}</div>` : ""}
            <div class="sq-assessor-summary-section">
                <h4>Assessment History</h4>
                ${renderAssessments(data.assessments || [])}
            </div>`;
        document.getElementById("assessorFacilitySummaryCard").hidden = false;
        document.getElementById("assessorFacilitySummaryCard").scrollIntoView({ behavior: "smooth", block: "start" });
    }

    async function loadFacilitySummary(facId) {
        try {
            const response = await SQ.api.get("/assessor/v1/facility_summary.php", { fac_id: Number(facId) }, { loader: true, showError: false });
            renderSummary(response.data || {});
        } catch (error) {
            if (SQ.notification) SQ.notification.error(error.message || "Unable to load facility details.");
        }
    }

    function bind() {
        document.getElementById("assessorDashboardRefresh")?.addEventListener("click", load);
        document.getElementById("assessorFacilitySummaryClose")?.addEventListener("click", function () {
            document.getElementById("assessorFacilitySummaryCard").hidden = true;
        });
        document.getElementById("assessorFacilitySummaryBody")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-summary-route]");

            if (!button || !SQ.router) {
                return;
            }

            const route = button.getAttribute("data-summary-route");
            const params = JSON.parse(button.getAttribute("data-summary-params") || "{}");

            if (route) {
                SQ.router.navigate(route, params);
            }
        });
        document.getElementById("assessorFacilityRows")?.addEventListener("click", function (event) {
            const startButton = event.target.closest("[data-start-facility]");
            const detailButton = event.target.closest("[data-detail-facility]");

            if (startButton) {
                startAssessment(startButton.getAttribute("data-start-facility"));
                return;
            }

            if (detailButton) {
                loadFacilitySummary(detailButton.getAttribute("data-detail-facility"));
            }
        });
    }

    async function init() {
        bind();
        await load();
    }

    SQ.assessorDashboard = { init };
})(window, document);
