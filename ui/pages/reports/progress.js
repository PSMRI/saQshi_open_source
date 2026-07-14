/*!
 * ==========================================================
 * SaQshi Open Source
 * Progress Report
 * progress.js
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
        progressChecklist: "/reports/v1/checkpoint_progress_report.php"
    };

    const state = {
        assessments: [],
        selectedAssessmentId: 0,
        progress: null,
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

    function bounded(value) {
        return Math.max(0, Math.min(num(value), 100));
    }

    function statusLabel(status) {
        const value = String(status || "").toUpperCase();

        if (value === "ACTIVE" || value === "IN_PROGRESS") {
            return "In Progress";
        }

        if (value === "COMPLETED") {
            return "Completed";
        }

        if (value === "NOT_STARTED") {
            return "Not Started";
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

        if (value === "NOT_STARTED") {
            return "is-not-started";
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
        const select = $("progressAssessmentSelect");

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
        const target = $("progressAssessmentContext");
        const assessment = state.progress?.assessment || {};

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
            <div>
                <span>Period</span>
                <strong>${escapeHtml(assessment.start_date || "-")} to ${escapeHtml(assessment.end_date || "-")}</strong>
            </div>
        `;
    }

    function renderSummary() {
        const summary = state.progress?.summary || {};
        const departments = summary.departments || {};
        const responses = summary.responses || {};
        const score = summary.score || {};
        const original = score.original || {};
        const improved = score.improved || {};
        const gaps = summary.gaps || {};

        setText("progressDepartmentPercent", percent(departments.completion_percent));
        setText("progressDepartmentRaw", num(departments.completed) + " / " + num(departments.active_departments) + " departments");
        setText("progressSavedResponses", num(responses.total_saved_responses));
        setText("progressImprovedScore", percent(improved.percentage));
        setText("progressOriginalScore", "Original " + percent(original.percentage));
        setText("progressGapClosure", percent(gaps.closure_percent));
        setText("progressGapRaw", num(gaps.open_gaps) + " open / " + num(gaps.total_original_gaps) + " total");
        setText("progressRevisedCheckpoints", num(responses.revised_checkpoints));
    }

    function renderRows() {
        const target = $("progressDepartmentRows");

        if (!target) {
            return;
        }

        const filter = $("progressStatusFilter")?.value || "";
        const rows = (state.progress?.departments || []).filter(function (department) {
            return !filter || String(department.status || "").toUpperCase() === filter;
        });

        if (!rows.length) {
            target.innerHTML = `
                <tr>
                    <td colspan="7" class="sq-text-center sq-text-muted">No department progress found.</td>
                </tr>
            `;
            return;
        }

        target.innerHTML = rows.map(function (department) {
            const assessor = department.assessor_info || {};
            const responses = department.responses || {};
            const score = department.score || {};
            const improved = score.improved || {};
            const gaps = department.gaps || {};

            return `
                <tr>
                    <td>
                        <div class="sq-progress-dept">
                            <strong>Department ${escapeHtml(department.dept_id)}</strong>
                            <span class="sq-progress-subtext">ID ${escapeHtml(department.dept_id)}</span>
                        </div>
                    </td>
                    <td>
                        <span class="sq-progress-status ${statusClass(department.status)}">
                            ${escapeHtml(statusLabel(department.status))}
                        </span>
                    </td>
                    <td>
                        ${escapeHtml(assessor.assessor_name || "-")}
                        <span class="sq-progress-subtext">${escapeHtml(assessor.assessment_type || "No assessor info")}</span>
                    </td>
                    <td>
                        ${num(responses.saved_responses)}
                        <span class="sq-progress-subtext">${num(responses.revised_checkpoints)} revised</span>
                    </td>
                    <td>
                        <span class="sq-progress-pill">${percent(improved.percentage)}</span>
                        <div class="sq-progress-bar-mini">
                            <span style="width:${bounded(improved.percentage)}%"></span>
                        </div>
                    </td>
                    <td>
                        <span class="sq-progress-pill is-gap">${num(gaps.open_gaps)} open</span>
                        <span class="sq-progress-subtext">${percent(gaps.closure_percent)} closed</span>
                    </td>
                    <td>
                        ${escapeHtml(department.started_on || "-")}
                        <span class="sq-progress-subtext">completed ${escapeHtml(department.completed_on || "-")}</span>
                    </td>
                </tr>
            `;
        }).join("");
    }

    function resetReport(message) {
        state.progress = null;
        renderContext();
        renderSummary();

        const target = $("progressDepartmentRows");

        if (target) {
            target.innerHTML = `<tr><td colspan="7" class="sq-text-center sq-text-muted">${escapeHtml(message || "Select assessment to view progress.")}</td></tr>`;
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

    async function loadProgress() {
        const assessmentId = Number($("progressAssessmentSelect")?.value || state.selectedAssessmentId || 0);

        if (!assessmentId) {
            resetReport("Select assessment to view progress.");
            return;
        }

        state.selectedAssessmentId = assessmentId;

        const target = $("progressDepartmentRows");

        if (target) {
            target.innerHTML = `<tr><td colspan="7" class="sq-text-center sq-text-muted">Loading progress report...</td></tr>`;
        }

        const response = await apiGet(API.progress, {
            assessment_id: assessmentId
        });

        state.progress = response.data || null;
        renderContext();
        renderSummary();
        renderRows();
    }

    function bindEvents() {
        $("progressAssessmentSelect")?.addEventListener("change", loadProgress);
        $("btnRefreshProgressReport")?.addEventListener("click", loadProgress);
        $("progressStatusFilter")?.addEventListener("change", renderRows);
        $("btnDownloadProgressChecklist")?.addEventListener("click", function () {
            const assessmentId = Number($("progressAssessmentSelect")?.value || state.selectedAssessmentId || 0);

            if (!assessmentId) {
                notify("warning", "Please select assessment first.");
                return;
            }

            if (!SQ.api || typeof SQ.api.download !== "function") {
                notify("error", "Download service is not available.");
                return;
            }

            SQ.api.download(
                API.progressChecklist,
                { assessment_id: assessmentId },
                "checkpoint_progress_assessment_" + assessmentId + ".xlsx"
            ).catch(function (error) {
                console.error(error);
                notify("error", error.message || "Unable to download progress checklist.");
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
            await loadProgress();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load progress report.");
            resetReport("Unable to load progress report.");
        } finally {
            state.isLoading = false;
        }
    }

    SQ.progressReport = {
        init,
        state
    };

})(window, document);
