/*!
 * ==========================================================
 * SaQshi Open Source
 * Assessment List
 * list.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        list: "/assessment/v1/list.php"
    };

    const state = {
        assessments: [],
        summary: null,
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
        if (SQ.api && typeof SQ.api.get === "function") {
            return SQ.api.get(endpoint, params, { loader: false });
        }

        const url = new URL("/api" + endpoint, window.location.origin);

        Object.keys(params).forEach(function (key) {
            if (params[key] !== null && params[key] !== undefined && params[key] !== "") {
                url.searchParams.set(key, params[key]);
            }
        });

        const response = await fetch(url.toString(), {
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });

        return response.json();
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

    function setText(id, value) {
        const el = $(id);

        if (el) {
            el.textContent = value;
        }
    }

    function renderSummary() {
        const summary = state.summary || {};

        setText("totalAssessments", Number(summary.total || 0));
        setText("activeAssessments", Number(summary.active || 0));
        setText("completedAssessments", Number(summary.completed || 0));
        setText("cancelledAssessments", Number(summary.cancelled || 0));
        setText("averageScore", Number(summary.average_score || 0).toFixed(2) + "%");
    }

    function actionButton(assessment) {
        if (assessment.status === "ACTIVE") {
            return `
                <button type="button" class="sq-btn sq-btn-primary sq-btn-sm" data-sq-route="assessment/checklist">
                    Continue
                </button>
            `;
        }

        return `
            <button type="button" class="sq-btn sq-btn-light sq-btn-sm" data-sq-route="dashboard">
                View
            </button>
        `;
    }

    function renderRows() {
        const target = $("assessmentListBody");

        if (!target) {
            return;
        }

        const filter = $("statusFilter")?.value || "";
        const rows = state.assessments.filter(function (assessment) {
            return !filter || assessment.status === filter;
        });

        if (!rows.length) {
            target.innerHTML = `
                <tr>
                    <td colspan="8" class="sq-text-center sq-text-muted">
                        No assessments found.
                    </td>
                </tr>
            `;
            return;
        }

        target.innerHTML = rows.map(function (assessment) {
            const activeDepartments = Number(assessment.active_departments || 0);
            const completedDepartments = Number(assessment.completed_departments || 0);
            const answered = Number(assessment.answered_checkpoints || 0);
            const totalCheckpoints = Number(assessment.total_checkpoints || 0);
            const obtainedScore = Number(assessment.obtained_score || 0);
            const totalScore = Number(assessment.total_score || 0);
            const score = Number(assessment.score_percent || 0);

            return `
                <tr>
                    <td>
                        <div class="sq-assessment-name">
                            <strong>${escapeHtml(assessment.assessment_name || "Assessment")}</strong>
                            <span>ID ${escapeHtml(assessment.assessment_id)}</span>
                        </div>
                    </td>
                    <td>${escapeHtml(assessment.framework_code || "-")}</td>
                    <td>
                        <span class="sq-status-chip ${statusClass(assessment.status)}">
                            ${escapeHtml(statusLabel(assessment.status))}
                        </span>
                    </td>
                    <td>
                        <div>${escapeHtml(assessment.start_date || "-")}</div>
                        <div class="sq-table-subtext">to ${escapeHtml(assessment.end_date || "-")}</div>
                    </td>
                    <td>
                        <div>${completedDepartments}/${activeDepartments}</div>
                        <div class="sq-table-subtext">completed/active</div>
                    </td>
                    <td>
                        <div>${answered}/${totalCheckpoints}</div>
                        <div class="sq-table-subtext">done/total</div>
                    </td>
                    <td>
                        <span class="sq-score-pill">${score.toFixed(2)}%</span>
                        <div class="sq-table-subtext">${obtainedScore.toFixed(2)} / ${totalScore.toFixed(2)}</div>
                    </td>
                    <td>${actionButton(assessment)}</td>
                </tr>
            `;
        }).join("");
    }

    async function loadAssessments() {
        const target = $("assessmentListBody");

        if (target) {
            target.innerHTML = `
                <tr>
                    <td colspan="8" class="sq-text-center sq-text-muted">
                        Loading assessments...
                    </td>
                </tr>
            `;
        }

        try {
            const response = await apiGet(API.list);

            if (response.status === "error" || response.success === false) {
                throw new Error(response.message || "Unable to load assessments.");
            }

            state.summary = response.data?.summary || {};
            state.assessments = response.data?.assessments || [];

            renderSummary();
            renderRows();

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load assessments.");

            if (target) {
                target.innerHTML = `
                    <tr>
                        <td colspan="8" class="sq-text-center sq-text-muted">
                            Unable to load assessments.
                        </td>
                    </tr>
                `;
            }
        }
    }

    function bindEvents() {
        $("statusFilter")?.addEventListener("change", renderRows);
        $("btnRefreshAssessments")?.addEventListener("click", loadAssessments);
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        bindEvents();

        try {
            await loadAssessments();
        } finally {
            state.isLoading = false;
        }
    }

    SQ.assessmentList = {
        init,
        state
    };

})(window, document);
