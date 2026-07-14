/*!
 * ==========================================================
 * SaQshi Open Source
 * Score Report
 * score.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        list: "/assessment/v1/list.php",
        activeAssessment: "/assessment/v1/active_assessment.php",
        score: "/assessment/v1/score.php",
        scoreCard: "/reports/v1/checkpoint_scorecard.php"
    };

    const state = {
        assessments: [],
        selectedAssessmentId: 0,
        score: null,
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

        if (value === "IN_PROGRESS") {
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
        const value = String(status || "").toUpperCase();

        if (value === "COMPLETED") {
            return "is-completed";
        }

        if (value === "ACTIVE" || value === "IN_PROGRESS") {
            return "is-progress";
        }

        return "";
    }

    function setText(id, value) {
        const el = $(id);

        if (el) {
            el.textContent = value;
        }
    }

    function renderAssessmentSelect() {
        const select = $("scoreAssessmentSelect");

        if (!select) {
            return;
        }

        select.innerHTML = `<option value="">Select assessment</option>`;

        state.assessments.forEach(function (assessment) {
            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${escapeHtml(assessment.assessment_id)}">${escapeHtml(assessment.assessment_name || "Assessment")} - ${escapeHtml(statusLabel(assessment.status))}</option>`
            );
        });

        if (state.selectedAssessmentId) {
            select.value = String(state.selectedAssessmentId);
        }
    }

    function renderContext() {
        const target = $("scoreAssessmentContext");
        const assessment = state.score?.assessment || {};

        if (!target) {
            return;
        }

        target.innerHTML = `
            <div>
                <span>Assessment</span>
                <strong>${escapeHtml(assessment.assessment_name || "-")}</strong>
            </div>
            <div>
                <span>Framework</span>
                <strong>${escapeHtml(assessment.framework_code || "-")}</strong>
            </div>
            <div>
                <span>Status</span>
                <strong>${escapeHtml(statusLabel(assessment.status))}</strong>
            </div>
        `;
    }

    function renderSummary() {
        const overall = state.score?.overall_score || {};
        const original = overall.original || {};
        const improved = overall.improved || {};
        const improvement = overall.improvement || {};

        setText("scoreOriginalPercent", percent(original.percentage));
        setText("scoreOriginalRaw", num(original.obtained_score).toFixed(2) + " / " + num(original.total_score).toFixed(2));
        setText("scoreImprovedPercent", percent(improved.percentage));
        setText("scoreImprovedRaw", num(improved.obtained_score).toFixed(2) + " / " + num(improved.total_score).toFixed(2));
        setText("scoreGainPercent", percent(improvement.percentage_gain));
        setText("scoreGainRaw", num(improvement.score_gain).toFixed(2) + " points");
        setText("scoreAnsweredCheckpoints", num(overall.answered_checkpoints));
        setText("scoreRevisedCheckpoints", num(overall.revised_checkpoints));
    }

    function renderRows() {
        const target = $("scoreDepartmentRows");

        if (!target) {
            return;
        }

        const rows = state.score?.departments || [];

        if (!rows.length) {
            target.innerHTML = `
                <tr>
                    <td colspan="7" class="sq-text-center sq-text-muted">No department score found.</td>
                </tr>
            `;
            return;
        }

        target.innerHTML = rows.map(function (row) {
            const original = row.original || {};
            const improved = row.improved || {};
            const improvement = row.improvement || {};

            return `
                <tr>
                    <td>
                        <div class="sq-score-dept">
                            <strong>Department ${escapeHtml(row.dept_id)}</strong>
                            <span class="sq-score-subtext">ID ${escapeHtml(row.dept_id)}</span>
                        </div>
                    </td>
                    <td>
                        <span class="sq-score-status ${statusClass(row.department_status)}">
                            ${escapeHtml(statusLabel(row.department_status))}
                        </span>
                    </td>
                    <td>${num(row.answered_checkpoints)}</td>
                    <td>
                        <span class="sq-score-pill">${percent(original.percentage)}</span>
                        <span class="sq-score-subtext">${num(original.obtained_score).toFixed(2)} / ${num(original.total_score).toFixed(2)}</span>
                    </td>
                    <td>
                        <span class="sq-score-pill">${percent(improved.percentage)}</span>
                        <span class="sq-score-subtext">${num(improved.obtained_score).toFixed(2)} / ${num(improved.total_score).toFixed(2)}</span>
                    </td>
                    <td>
                        <span class="sq-score-pill is-gain">${percent(improvement.percentage_gain)}</span>
                        <span class="sq-score-subtext">${num(improvement.score_gain).toFixed(2)} points</span>
                    </td>
                    <td>${num(row.revised_checkpoints)}</td>
                </tr>
            `;
        }).join("");
    }

    function resetReport(message) {
        state.score = null;
        renderContext();
        renderSummary();

        const target = $("scoreDepartmentRows");

        if (target) {
            target.innerHTML = `<tr><td colspan="7" class="sq-text-center sq-text-muted">${escapeHtml(message || "Select assessment to view score.")}</td></tr>`;
        }
    }

    async function loadAssessments() {
        const response = await apiGet(API.list);
        state.assessments = response.data?.assessments || [];

        let selectedId = Number(new URLSearchParams(window.location.search).get("assessment_id") || 0);

        if (!selectedId) {
            const activeResponse = await apiGet(API.activeAssessment);
            selectedId = Number(activeResponse.data?.assessment?.assessment_id || activeResponse.data?.assessment_id || 0);
        }

        if (!selectedId && state.assessments.length) {
            selectedId = Number(state.assessments[0].assessment_id || 0);
        }

        state.selectedAssessmentId = selectedId;
        renderAssessmentSelect();
    }

    async function loadScore() {
        const assessmentId = Number($("scoreAssessmentSelect")?.value || state.selectedAssessmentId || 0);

        if (!assessmentId) {
            resetReport("Select assessment to view score.");
            return;
        }

        state.selectedAssessmentId = assessmentId;

        const target = $("scoreDepartmentRows");

        if (target) {
            target.innerHTML = `<tr><td colspan="7" class="sq-text-center sq-text-muted">Loading score report...</td></tr>`;
        }

        const response = await apiGet(API.score, {
            assessment_id: assessmentId
        });

        state.score = response.data || null;
        renderContext();
        renderSummary();
        renderRows();
    }

    function bindEvents() {
        $("scoreAssessmentSelect")?.addEventListener("change", loadScore);
        $("btnRefreshScoreReport")?.addEventListener("click", loadScore);
        $("btnDownloadScoreCard")?.addEventListener("click", function () {
            const assessmentId = Number($("scoreAssessmentSelect")?.value || state.selectedAssessmentId || 0);

            if (!assessmentId) {
                notify("warning", "Please select assessment first.");
                return;
            }

            if (!SQ.api || typeof SQ.api.download !== "function") {
                notify("error", "Download service is not available.");
                return;
            }

            SQ.api.download(
                API.scoreCard,
                { assessment_id: assessmentId },
                "checkpoint_scorecard_assessment_" + assessmentId + ".xlsx"
            ).catch(function (error) {
                console.error(error);
                notify("error", error.message || "Unable to download score card.");
            });
        });
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        bindEvents();

        try {
            await loadAssessments();
            await loadScore();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load score report.");
            resetReport("Unable to load score report.");
        } finally {
            state.isLoading = false;
        }
    }

    SQ.scoreReport = {
        init,
        state
    };

})(window, document);
