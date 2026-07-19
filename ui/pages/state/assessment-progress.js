/*!
 * ==========================================================
 * SaQshi Open Source
 * State Assessment Progress
 * assessment-progress.js
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    const state = { pager: null };

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function number(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function percent(value) {
        return Math.max(0, Math.min(100, number(value)));
    }

    function progress(done, total) {
        const safeDone = number(done);
        const safeTotal = number(total);
        const pct = safeTotal > 0 ? Math.round((safeDone / safeTotal) * 100) : 0;

        return `
            <div class="sq-state-mini-progress">
                <div><span style="width:${percent(pct)}%"></span></div>
                <small>${esc(safeDone)} / ${esc(safeTotal)}</small>
            </div>
        `;
    }

    function scoreCell(row) {
        const finalScore = number(row.score_percent).toFixed(2);
        const baseline = number(row.baseline_score_percent).toFixed(2);

        return `
            <strong>${esc(finalScore)}%</strong>
            <small>Baseline ${esc(baseline)}%</small>
            <small>${esc(row.final_obtained_score || 0)} / ${esc(row.total_score || 0)}</small>
        `;
    }

    function queryParams() {
        return state.pager.params({
            search: document.getElementById("stateAssessSearch")?.value || ""
        });
    }

    function bindSearch(inputId) {
        let timer = null;
        document.getElementById(inputId)?.addEventListener("input", function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                state.pager.reset();
                load();
            }, 300);
        });
    }

    function renderSummary(summary) {
        document.getElementById("stateAssessSummary").innerHTML = `
            <div><span>Total</span><strong>${esc(summary.total || 0)}</strong></div>
            <div><span>Active</span><strong>${esc(summary.active || 0)}</strong></div>
            <div><span>Completed</span><strong>${esc(summary.completed || 0)}</strong></div>
            <div><span>Cancelled</span><strong>${esc(summary.cancelled || 0)}</strong></div>
        `;
    }

    function renderRows(rows) {
        document.getElementById("stateAssessRows").innerHTML = rows.length
            ? `<table class="sq-state-table sq-state-assess-table">
                <thead>
                    <tr>
                        <th>Facility</th>
                        <th>Assessment</th>
                        <th>Status</th>
                        <th>Departments</th>
                        <th>Checkpoints</th>
                        <th>Action Plans</th>
                        <th>Score</th>
                        <th>Timeline</th>
                    </tr>
                </thead>
                <tbody>${rows.map(renderRow).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No assessment records available.</div>`;
    }

    function renderRow(row) {
        return `
            <tr>
                <td>
                    <strong>${esc(row.fac_name || "-")}</strong>
                    <small>${esc(row.district || "-")} / ${esc(row.block || "-")}</small>
                    <small>NIN ${esc(row.NIN_no || "-")}</small>
                </td>
                <td>
                    <strong>${esc(row.assessment_name || "-")}</strong>
                    <small>ID ${esc(row.assessment_id || "-")} | ${esc(row.framework_code || "-")}</small>
                    ${row.is_latest ? `<span class="sq-state-badge sq-state-latest">Latest</span>` : ""}
                </td>
                <td><span class="sq-state-badge">${esc(row.status || "-")}</span></td>
                <td>
                    ${progress(row.completed_departments || 0, row.total_departments || 0)}
                    <small>Left ${esc(row.pending_departments || 0)}</small>
                </td>
                <td>
                    ${progress(row.checkpoint_done || 0, row.total_checkpoints || 0)}
                    <small>Left ${esc(row.checkpoint_left || 0)}</small>
                </td>
                <td>
                    ${progress(row.completed_action_plans || 0, row.total_action_plans || 0)}
                    <small>Left ${esc(row.pending_action_plans || 0)}</small>
                </td>
                <td>${scoreCell(row)}</td>
                <td>
                    <small>Start ${esc(row.start_date || "-")}</small>
                    <small>End ${esc(row.end_date || "-")}</small>
                </td>
            </tr>
        `;
    }

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/assessment_progress.php", queryParams(), {
                loader: false,
                showError: false
            });
            const data = response.data || {};

            renderSummary(data.summary || {});
            renderRows(data.rows || []);
            state.pager.set(data.pagination || {}).render("stateAssessPager", "Showing");
        } catch (error) {
            document.getElementById("stateAssessSummary").innerHTML = "";
            document.getElementById("stateAssessRows").innerHTML =
                `<div class="sq-state-empty">${esc(error.message || "Unable to load assessment progress.")}</div>`;
            state.pager.set({ page: 1, total_pages: 1, total_rows: 0 }).render("stateAssessPager", "Showing");
        }
    }

    async function init() {
        state.pager = SQ.pagination.create({ page: 1, perPage: 50, onChange: load });
        document.getElementById("stateAssessRefresh")?.addEventListener("click", function () {
            state.pager.reset();
            load();
        });
        bindSearch("stateAssessSearch");
        await load();
    }

    SQ.stateAssessmentProgress = { init };
})(window, document);
