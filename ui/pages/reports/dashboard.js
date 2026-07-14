/*!
 * ==========================================================
 * SaQshi Open Source
 * Report Dashboard
 * dashboard.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        list: "/assessment/v1/list.php",
        activeAssessment: "/assessment/v1/active_assessment.php",
        progress: "/assessment/v1/progress.php",
        score: "/assessment/v1/score.php",
        gaps: "/assessment/v1/gap_analysis.php",
        performanceDashboard: "/performance/v1/dashboard.php"
    };

    const state = {
        summary: {},
        assessments: [],
        activeAssessment: null,
        progress: null,
        score: null,
        gaps: null,
        performance: null,
        isLoading: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function notify(type, message) {
        if (SQ.notification && typeof SQ.notification[type] === "function") {
            SQ.notification[type](message);
            return;
        }

        if (SQ.toast) {
            SQ.toast(message, type);
        }
    }

    async function apiGet(endpoint, params = {}) {
        return SQ.api.get(endpoint, params, {
            loader: false,
            showError: false
        });
    }

    function setText(id, value) {
        const el = $(id);

        if (el) {
            el.textContent = value;
        }
    }

    function num(value, fallback = 0) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function percent(value) {
        return num(value).toFixed(2).replace(/\.00$/, "") + "%";
    }

    function statusLabel(status) {
        const value = String(status || "").toUpperCase();

        if (value === "ACTIVE") {
            return "In Progress";
        }

        if (value === "COMPLETED") {
            return "Completed";
        }

        if (value === "CANCELLED") {
            return "Cancelled";
        }

        return value || "-";
    }

    function statusClass(status) {
        const value = String(status || "").toLowerCase();

        if (value === "active") {
            return "is-active";
        }

        if (value === "completed") {
            return "is-completed";
        }

        if (value === "cancelled") {
            return "is-cancelled";
        }

        return "";
    }

    function renderSummary() {
        const summary = state.summary || {};
        const gaps = state.gaps || {};
        const openGaps = Number.isFinite(num(gaps.summary?.open_gaps, NaN))
            ? num(gaps.summary?.open_gaps)
            : (Array.isArray(gaps.open_gaps) ? gaps.open_gaps.length : 0);

        setText("reportTotalAssessments", num(summary.total));
        setText("reportCompletedAssessments", num(summary.completed));
        setText("reportActiveAssessments", num(summary.active));
        setText("reportAverageScore", percent(summary.average_score));
        setText("reportOpenGaps", openGaps);
        setText("reportKpiMonths", num(state.performance?.summary?.kpi_months));
        setText("reportOutcomeMonths", num(state.performance?.summary?.outcome_months));
    }

    function renderCurrentAssessment() {
        const target = $("reportCurrentAssessment");

        if (!target) {
            return;
        }

        const assessment = state.activeAssessment;

        if (!assessment || !assessment.assessment_id) {
            target.innerHTML = `<div class="sq-report-empty">No active assessment found.</div>`;
            return;
        }

        const departments = state.progress?.summary?.departments || {};
        const gaps = state.progress?.summary?.gaps || {};
        const overall = state.score?.overall_score || {};
        const improved = overall.improved || {};
        const original = overall.original || {};
        const completion = num(departments.completion_percent);

        target.innerHTML = `
            <div class="sq-report-current">
                <div>
                    <div class="sq-report-current-title">
                        <strong>${escapeHtml(assessment.assessment_name || "Assessment")}</strong>
                        <span>${escapeHtml(assessment.framework_code || "-")} | ${escapeHtml(statusLabel(assessment.status))}</span>
                    </div>

                    <div class="sq-report-meta">
                        <div>
                            <span>Departments</span>
                            <strong>${num(departments.completed)}/${num(departments.active_departments)}</strong>
                        </div>
                        <div>
                            <span>Open Gaps</span>
                            <strong>${num(gaps.open_gaps)}</strong>
                        </div>
                        <div>
                            <span>Closed Gaps</span>
                            <strong>${num(gaps.closed_gaps)}</strong>
                        </div>
                    </div>
                </div>

                <div class="sq-report-score-box">
                    <span>Improved Score</span>
                    <strong>${percent(improved.percentage)}</strong>
                    <span class="sq-report-subtext">Original ${percent(original.percentage)}</span>
                    <div class="sq-report-progress">
                        <span style="width:${Math.max(0, Math.min(completion, 100))}%"></span>
                    </div>
                    <span class="sq-report-subtext">Progress ${percent(completion)}</span>
                </div>
            </div>
        `;
    }

    function actionButton(assessment) {
        const id = num(assessment.assessment_id);

        return `
            <button type="button" class="sq-btn sq-btn-light sq-btn-sm" data-report-score="${id}">
                Score
            </button>
            <button type="button" class="sq-btn sq-btn-primary sq-btn-sm" data-report-progress="${id}">
                Progress
            </button>
        `;
    }

    function renderRows() {
        const target = $("reportAssessmentRows");

        if (!target) {
            return;
        }

        const filter = $("reportStatusFilter")?.value || "";
        const rows = state.assessments.filter(function (assessment) {
            return !filter || String(assessment.status || "").toUpperCase() === filter;
        });

        if (!rows.length) {
            target.innerHTML = `
                <tr>
                    <td colspan="7" class="sq-text-center sq-text-muted">No reports found.</td>
                </tr>
            `;
            return;
        }

        target.innerHTML = rows.map(function (assessment) {
            const score = num(assessment.score_percent);

            return `
                <tr>
                    <td>
                        <div class="sq-report-name">
                            <strong>${escapeHtml(assessment.assessment_name || "Assessment")}</strong>
                            <span>ID ${escapeHtml(assessment.assessment_id)}</span>
                        </div>
                    </td>
                    <td>
                        <span class="sq-report-status ${statusClass(assessment.status)}">
                            ${escapeHtml(statusLabel(assessment.status))}
                        </span>
                    </td>
                    <td>
                        ${num(assessment.completed_departments)}/${num(assessment.active_departments)}
                        <span class="sq-report-subtext">completed/active</span>
                    </td>
                    <td>
                        ${num(assessment.answered_checkpoints)}/${num(assessment.total_checkpoints)}
                        <span class="sq-report-subtext">done/total</span>
                    </td>
                    <td>
                        <span class="sq-report-score">${percent(score)}</span>
                        <span class="sq-report-subtext">${num(assessment.obtained_score).toFixed(2)} / ${num(assessment.total_score).toFixed(2)}</span>
                    </td>
                    <td>
                        ${escapeHtml(assessment.start_date || "-")}
                        <span class="sq-report-subtext">to ${escapeHtml(assessment.end_date || "-")}</span>
                    </td>
                    <td>
                        <div class="sq-report-row-actions">${actionButton(assessment)}</div>
                    </td>
                </tr>
            `;
        }).join("");
    }

    async function loadAssessmentDetails() {
        const response = await apiGet(API.activeAssessment);
        state.activeAssessment = response.data?.assessment || response.data || null;

        if (!state.activeAssessment?.assessment_id) {
            state.progress = null;
            state.score = null;
            state.gaps = null;
            return;
        }

        const assessmentId = state.activeAssessment.assessment_id;
        const responses = await Promise.all([
            apiGet(API.progress, { assessment_id: assessmentId }),
            apiGet(API.score, { assessment_id: assessmentId }),
            apiGet(API.gaps, { assessment_id: assessmentId })
        ]);

        state.progress = responses[0].data || null;
        state.score = responses[1].data || null;
        state.gaps = responses[2].data || null;
    }

    async function loadPerformanceSummary() {
        const response = await apiGet(API.performanceDashboard, { all_indicators: 0 });
        state.performance = response.data || null;
    }

    async function loadReports() {
        const rows = $("reportAssessmentRows");

        if (rows) {
            rows.innerHTML = `<tr><td colspan="7" class="sq-text-center sq-text-muted">Loading reports...</td></tr>`;
        }

        try {
            const listResponse = await apiGet(API.list);
            state.summary = listResponse.data?.summary || {};
            state.assessments = listResponse.data?.assessments || [];

            await Promise.all([
                loadAssessmentDetails(),
                loadPerformanceSummary()
            ]);

            renderSummary();
            renderCurrentAssessment();
            renderRows();

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load report dashboard.");
            renderCurrentAssessment();

            if (rows) {
                rows.innerHTML = `<tr><td colspan="7" class="sq-text-center sq-text-muted">Unable to load reports.</td></tr>`;
            }
        }
    }

    function bindEvents() {
        $("reportStatusFilter")?.addEventListener("change", renderRows);
        $("btnRefreshReport")?.addEventListener("click", loadReports);

        document.addEventListener("click", function (event) {
            const score = event.target.closest("[data-report-score]");

            if (score) {
                const assessmentId = Number(score.dataset.reportScore || 0);

                if (SQ.router && typeof SQ.router.navigate === "function") {
                    SQ.router.navigate("reports/score", {
                        assessment_id: assessmentId
                    });
                }

                return;
            }

            const progress = event.target.closest("[data-report-progress]");

            if (progress) {
                const assessmentId = Number(progress.dataset.reportProgress || 0);

                if (SQ.router && typeof SQ.router.navigate === "function") {
                    SQ.router.navigate("reports/progress", {
                        assessment_id: assessmentId
                    });
                }
            }

            const performanceReport = event.target.closest("[data-performance-report]");

            if (performanceReport) {
                const type = performanceReport.dataset.performanceReport || "";

                if (SQ.router && typeof SQ.router.navigate === "function") {
                    SQ.router.navigate("performance/trend", {
                        indicator_type: type,
                        all_indicators: 1
                    });
                }
            }
        });
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        bindEvents();

        try {
            await loadReports();
        } finally {
            state.isLoading = false;
        }
    }

    SQ.reportDashboard = {
        init,
        state
    };

})(window, document);
