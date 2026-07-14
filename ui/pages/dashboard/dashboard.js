/*!
 * ==========================================================
 * SaQshi Dashboard Page v1.0
 * ----------------------------------------------------------
 * Project : SaQshi Open Source
 * Module  : Dashboard
 * File    : dashboard.js
 * License : GPL-3.0
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    const API = {
        activeAssessment: "/assessment/v1/active_assessment.php",
        progress: "/assessment/v1/progress.php",
        score: "/assessment/v1/score.php",
        gapAnalysis: "/assessment/v1/gap_analysis.php",
        insights: "/assessment/v1/dashboard_insights.php",
        performanceDashboard: "/performance/v1/dashboard.php"
    };

    const state = {
        activeAssessment: null,
        progress: null,
        score: null,
        gaps: null,
        insights: null,
        performance: null
    };

    function setText(id, value) {
        const el = document.getElementById(id);

        if (el) {
            el.textContent = value;
        }
    }

    function badge(status) {
        const value = String(status || "").toUpperCase();

        if (value === "COMPLETED") {
            return `<span class="sq-badge sq-status-completed">Completed</span>`;
        }

        if (value === "ACTIVE" || value === "IN_PROGRESS") {
            return `<span class="sq-badge sq-status-in-progress">In Progress</span>`;
        }

        if (value === "CANCELLED") {
            return `<span class="sq-badge sq-status-cancelled">Cancelled</span>`;
        }

        return `<span class="sq-badge sq-status-not-started">Not Started</span>`;
    }

    async function loadActiveAssessment() {
        const response = await SQ.api.get(
            API.activeAssessment,
            {},
            {
                loader: false,
                showError: false
            }
        );

        state.activeAssessment =
            response.data?.assessment ||
            response.data ||
            null;

        renderActiveAssessment();

        if (state.activeAssessment?.assessment_id) {
            await Promise.all([
                loadProgress(state.activeAssessment.assessment_id),
                loadScore(state.activeAssessment.assessment_id),
                loadGapAnalysis(state.activeAssessment.assessment_id),
                loadInsights(state.activeAssessment.assessment_id),
                loadPerformanceDashboard()
            ]);
        }
    }

    async function loadProgress(assessmentId) {
        const response = await SQ.api.get(
            API.progress,
            {
                assessment_id: assessmentId
            },
            {
                loader: false,
                showError: false
            }
        );

        state.progress = response.data || null;
        renderProgress();
    }

    async function loadScore(assessmentId) {
        const response = await SQ.api.get(
            API.score,
            {
                assessment_id: assessmentId
            },
            {
                loader: false,
                showError: false
            }
        );

        state.score = response.data || null;
        renderScore();
    }

    async function loadGapAnalysis(assessmentId) {
        const response = await SQ.api.get(
            API.gapAnalysis,
            {
                assessment_id: assessmentId
            },
            {
                loader: false,
                showError: false
            }
        );

        state.gaps = response.data || null;
        renderGaps();
    }

    async function loadInsights(assessmentId) {
        const response = await SQ.api.get(
            API.insights,
            {
                assessment_id: assessmentId
            },
            {
                loader: false,
                showError: false
            }
        );

        state.insights = response.data || null;
        renderAreaConcerns();
    }

    async function loadPerformanceDashboard() {
        const response = await SQ.api.get(
            API.performanceDashboard,
            {
                all_indicators: 0
            },
            {
                loader: false,
                showError: false
            }
        );

        state.performance = response.data || null;
        renderOutcomeMonths();
    }

    function renderActiveAssessment() {
        const target = document.getElementById("active-assessment-card");

        if (!target) {
            return;
        }

        const assessment = state.activeAssessment;

        if (!assessment || !assessment.assessment_id) {
            target.innerHTML = `
                <div class="sq-empty-message">
                    <div>
                        <strong>No active assessment found.</strong>
                        <br>
                        <a href="#" data-sq-route="assessment/create" class="sq-btn sq-btn-primary sq-mt-3">
                            Create Assessment
                        </a>
                    </div>
                </div>
            `;
            return;
        }

        target.innerHTML = `
            <div class="sq-assessment-card">
                <div class="sq-assessment-card-header">
                    <div>
                        <div class="sq-assessment-title">
                            ${escapeHtml(assessment.assessment_name || "Assessment")}
                        </div>
                        <div class="sq-assessment-meta">
                            Framework: ${escapeHtml(assessment.framework_code || "N/A")}
                        </div>
                    </div>
                    ${badge(assessment.status)}
                </div>

                <div class="sq-grid sq-grid-2 sq-mt-4">
                    <div>
                        <div class="sq-text-muted sq-text-sm">Start Date</div>
                        <strong>${escapeHtml(assessment.start_date || "-")}</strong>
                    </div>

                    <div>
                        <div class="sq-text-muted sq-text-sm">End Date</div>
                        <strong>${escapeHtml(assessment.end_date || "-")}</strong>
                    </div>
                </div>

                <div class="sq-assessment-footer">
                    <a href="#" data-sq-route="assessment/departments"
                       class="sq-btn sq-btn-outline-primary sq-btn-sm">
                        View Progress
                    </a>

                    <a href="#" data-sq-route="assessment/checklist"
                       class="sq-btn sq-btn-primary sq-btn-sm">
                        Continue Assessment
                    </a>
                </div>
            </div>
        `;
    }

    function renderProgress() {
        const summary = state.progress?.summary || {};
        const departments = summary.departments || summary;

        const active = Number(departments.active_departments || 0);
        const completed = Number(departments.completed || 0);
        const pending = Math.max(active - completed, 0);
        const percent = Number(
            departments.completion_percent ||
            summary.department_completion_percent ||
            0
        );

        setText("active-departments", active);
        setText("completed-departments", completed);
        setText("pending-departments", pending);
        setText("overall-progress-text", percent + "%");

        const bar = document.getElementById("overall-progress-bar");

        if (bar) {
            bar.style.width = percent + "%";
        }

        setText("metric-in-progress", Number(departments.in_progress || 0));
        setText("metric-completed", completed);
    }

    function renderScore() {
        const overall =
            state.score?.overall_score ||
            state.score?.score ||
            {};

        const original = overall.original || {};
        const improved = overall.improved || {};
        const improvement = overall.improvement || {};
        const baselinePercent = Number(original.percentage || overall.percentage || 0);
        const finalPercent = Number(improved.percentage || baselinePercent || 0);
        const gainPercent = Number(improvement.percentage_gain || (finalPercent - baselinePercent) || 0);

        const el = document.getElementById("dashboard-score");

        if (el) {
            el.textContent = finalPercent + "%";
        }

        setText("baseline-score", baselinePercent + "%");
        setText("final-score", finalPercent + "%");
        setText("score-improvement", gainPercent + "%");
        setText("answered-checkpoints", Number(overall.answered_checkpoints || 0));
        setText("revised-checkpoints", Number(overall.revised_checkpoints || 0));
        setText("score-gain", Number(improvement.score_gain || 0));
        renderScoreCharts(baselinePercent, finalPercent, gainPercent);
    }

    function renderScoreCharts(baseline, finalScore, gain) {
        const trend = document.getElementById("score-trend-chart");
        const pie = document.getElementById("score-pie-chart");
        const progress = document.getElementById("final-progress-chart");

        if (trend) {
            const max = Math.max(100, baseline, finalScore);
            const baseY = 78 - ((baseline / max) * 58);
            const finalY = 78 - ((finalScore / max) * 58);
            trend.innerHTML = `
                <svg viewBox="0 0 220 92" class="sq-score-svg" role="img" aria-label="Baseline to final score trend">
                    <line x1="20" y1="78" x2="200" y2="78"></line>
                    <polyline points="45,${baseY} 175,${finalY}"></polyline>
                    <circle cx="45" cy="${baseY}" r="5"></circle>
                    <circle cx="175" cy="${finalY}" r="5"></circle>
                    <text x="45" y="88" text-anchor="middle">Baseline</text>
                    <text x="175" y="88" text-anchor="middle">Final</text>
                    <text x="45" y="${Math.max(12, baseY - 8)}" text-anchor="middle">${escapeHtml(baseline)}%</text>
                    <text x="175" y="${Math.max(12, finalY - 8)}" text-anchor="middle">${escapeHtml(finalScore)}%</text>
                </svg>
            `;
        }

        if (pie) {
            const clamped = Math.max(0, Math.min(finalScore, 100));
            pie.innerHTML = `
                <div class="sq-score-donut" style="--sq-score:${clamped}">
                    <span>${escapeHtml(finalScore)}%</span>
                </div>
                <div class="sq-chart-note">Final score<br>Gain ${escapeHtml(gain)}%</div>
            `;
        }

        if (progress) {
            const clamped = Math.max(0, Math.min(finalScore, 100));
            progress.innerHTML = `
                <div class="sq-final-progress-head">
                    <strong>${escapeHtml(finalScore)}%</strong>
                    <span>Final Score</span>
                </div>
                <div class="sq-final-progress-track">
                    <span style="width:${clamped}%"></span>
                </div>
                <div class="sq-final-progress-meta">
                    <span>Baseline ${escapeHtml(baseline)}%</span>
                    <span>Improvement ${escapeHtml(gain)}%</span>
                </div>
            `;
        }
    }

    function renderGaps() {
        const gaps = state.gaps || {};
        const summaryOpenGaps = gaps.summary?.open_gaps;
        const openGaps = Number.isFinite(Number(summaryOpenGaps))
            ? Number(summaryOpenGaps)
            : (
                Array.isArray(gaps.open_gaps)
                    ? gaps.open_gaps.length
                    : Number(gaps.open_gaps || 0)
            );

        setText(
            "metric-open-gaps",
            Number.isFinite(openGaps) ? openGaps : 0
        );
    }

    function renderAreaConcerns() {
        const target = document.getElementById("area-concern-status");

        if (!target) {
            return;
        }

        const rows = state.insights?.area_concerns || [];

        if (!rows.length) {
            target.innerHTML = `<div class="sq-empty-message">No area of concern status available.</div>`;
            return;
        }

        target.innerHTML = rows.slice(0, 12).map(row => {
            const total = Number(row.total_checkpoints || 0);
            const completed = Number(row.completed_checkpoints || 0);
            const pending = Number(row.pending_checkpoints || 0);
            const percent = Number(row.completion_percent || 0);

            return `
                <div class="sq-area-status-row">
                    <div class="sq-area-status-main">
                        <strong>${escapeHtml(row.area_name || "Area of Concern")}</strong>
                        <span>Department ${escapeHtml(row.dept_id || "-")} | ${completed}/${total} completed</span>
                    </div>
                    <div class="sq-area-status-counts">
                        <span class="is-complete">${completed} completed</span>
                        <span class="is-pending">${pending} pending</span>
                    </div>
                    <div class="sq-area-status-bar">
                        <span style="width:${Math.max(0, Math.min(percent, 100))}%"></span>
                    </div>
                </div>
            `;
        }).join("");
    }

    function shortMonth(period) {
        const parts = String(period || "").split("-");

        if (parts.length !== 2) {
            return period || "-";
        }

        const month = Number(parts[1]);
        const names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

        return `${names[month - 1] || parts[1]} ${String(parts[0]).slice(-2)}`;
    }

    function renderOutcomeMonths() {
        const summary = state.performance?.summary || {};
        const rows = (state.performance?.month_status || [])
            .filter(row => Number(row.outcome_entries || 0) > 0);
        const latest = rows[rows.length - 1] || null;
        const totalEntries = rows.reduce(
            (sum, row) => sum + Number(row.outcome_entries || 0),
            0
        );

        setText("outcome-month-count", summary.outcome_months || rows.length || 0);
        setText("outcome-entry-count", totalEntries);
        setText("outcome-latest-month", latest ? shortMonth(latest.period) : "-");

        const target = document.getElementById("outcome-month-list");

        if (!target) {
            return;
        }

        if (!rows.length) {
            target.innerHTML = `<div class="sq-empty-message">No outcome data filled yet.</div>`;
            return;
        }

        target.innerHTML = rows.slice(-6).reverse().map(row => `
            <div class="sq-outcome-month-chip">
                <strong>${escapeHtml(shortMonth(row.period))}</strong>
                <span>${escapeHtml(row.outcome_entries || 0)} outcome entries filled</span>
            </div>
        `).join("");
    }

    function renderRecentAssessments() {
        const target = document.getElementById("recent-assessment-list");

        if (!target) {
            return;
        }

        const assessment = state.activeAssessment;

        if (!assessment || !assessment.assessment_id) {
            target.innerHTML = `
                <tr>
                    <td colspan="7" class="sq-text-center sq-text-muted">
                        No assessment found.
                    </td>
                </tr>
            `;
            return;
        }

        const overall = state.score?.overall_score || {};
        const finalPercent = Number(
            overall.improved?.percentage ||
            overall.original?.percentage ||
            0
        );

        target.innerHTML = `
            <tr>
                <td>${escapeHtml(assessment.assessment_name || "Assessment")}</td>
                <td>${escapeHtml(assessment.framework_code || "-")}</td>
                <td>${badge(assessment.status)}</td>
                <td>${escapeHtml(assessment.start_date || "-")}</td>
                <td>${escapeHtml(assessment.end_date || "-")}</td>
                <td id="recent-score">${escapeHtml(finalPercent)}%</td>
                <td class="sq-td-right">
                    <a href="#" data-sq-route="assessment/departments"
                       class="sq-btn sq-btn-sm sq-btn-outline-primary">
                        Open
                    </a>
                </td>
            </tr>
        `;
    }

    function renderMetrics() {
        setText("metric-total-assessments", state.activeAssessment ? 1 : 0);
        renderRecentAssessments();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function bindQuickActions() {
        const actions = document.getElementById("sq-page-actions");

        if (!actions || actions.dataset.dashboardQuickActionsBound === "1") {
            return;
        }

        actions.dataset.dashboardQuickActionsBound = "1";
        actions.addEventListener("click", function (event) {
            const link = event.target.closest("[data-sq-route]");

            if (!link || !actions.contains(link)) {
                return;
            }

            const route = link.getAttribute("data-sq-route");

            if (!route || route === "#") {
                return;
            }

            event.preventDefault();

            if (SQ.router && typeof SQ.router.navigate === "function") {
                SQ.router.navigate(route);
            }
        });
    }

    async function init() {
        try {
            bindQuickActions();

            if (SQ.breadcrumb) {
                SQ.breadcrumb.render([
                    {
                        label: "Dashboard"
                    }
                ]);
            }

            await loadActiveAssessment();
            renderMetrics();

        } catch (error) {
            if (SQ.notification) {
                SQ.notification.error(
                    error.message || "Unable to load dashboard"
                );
            }

            renderRecentAssessments();
        }
    }

    SQ.dashboard = {
        init,
        state
    };

})(window, document);
