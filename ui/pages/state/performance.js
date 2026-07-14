/*!
 * ==========================================================
 * SaQshi Open Source
 * State Performance Monitoring
 * performance.js
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

    function renderSummary(summary) {
        document.getElementById("statePerfSummary").innerHTML = `
            <div><span>Facilities Submitted</span><strong>${esc(summary.facilities || 0)}</strong></div>
            <div><span>Submitted Months</span><strong>${esc(summary.submitted_months || 0)}</strong></div>
            <div><span>Completed</span><strong>${esc(summary.completed || 0)}</strong></div>
            <div><span>In Progress</span><strong>${esc(summary.in_progress || 0)}</strong></div>
        `;
    }

    function renderRows(rows) {
        document.getElementById("statePerfRows").innerHTML = rows.length
            ? `<table class="sq-state-table sq-state-performance-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Facility</th>
                        <th>District</th>
                        <th>Block</th>
                        <th>Type</th>
                        <th>Months</th>
                        <th>Status</th>
                        <th>Latest Month</th>
                    </tr>
                </thead>
                <tbody>${rows.map((row, index) => renderFacilityRow(row, index)).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No performance submissions available.</div>`;
    }

    function renderFacilityRow(row, index) {
        const targetId = `statePerfDetails${index}`;
        const details = Array.isArray(row.details) ? row.details : [];

        return `
            <tr>
                <td class="sq-state-toggle-cell">
                    <button class="sq-state-plus" type="button" data-state-performance-toggle="${esc(targetId)}" aria-expanded="false">+</button>
                </td>
                <td><strong>${esc(row.fac_name || "-")}</strong><br><small>ID ${esc(row.fac_id || "-")}</small></td>
                <td>${esc(row.district || "-")}</td>
                <td>${esc(row.block || "-")}</td>
                <td>${esc(row.facility_type || "-")}<br><small>${esc(row.effective_indicator_label || row.effective_indicator_type || "KPI")}</small></td>
                <td>${esc(row.submitted_months || row.months_submitted || 0)}</td>
                <td><span class="sq-state-badge">${esc(row.completion_status || "IN_PROGRESS")}</span></td>
                <td>${esc(row.latest_month || "-")}</td>
            </tr>
            <tr id="${esc(targetId)}" class="sq-state-district-detail" hidden>
                <td></td>
                <td colspan="7">${renderDetails(details)}</td>
            </tr>
        `;
    }

    function renderDetails(details) {
        return details.length
            ? `<div class="sq-state-block-list">
                ${details.map(detail => `
                    <div class="sq-state-block-row sq-state-performance-detail">
                        <div>
                            <strong>${esc(detail.indicator_type || "-")} | ${esc(detail.period || "-")}</strong>
                            <small>${esc(detail.indicator_type === "OUTCOME" ? detail.department_name : "KPI")}</small>
                        </div>
                        <div class="sq-state-type-chips">
                            <span>Entries: <b>${esc(detail.entries || 0)}</b></span>
                            ${detail.indicator_type === "OUTCOME" ? `<span>Department: <b>${esc(detail.department_name || "-")}</b></span>` : ""}
                        </div>
                    </div>
                `).join("")}
            </div>`
            : `<div class="sq-state-empty">No KPI or Outcome detail found for this facility.</div>`;
    }

    function queryParams() {
        return state.pager.params({
            search: document.getElementById("statePerfSearch")?.value || ""
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

    function bindToggle() {
        document.getElementById("statePerfRows")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-state-performance-toggle]");
            if (!button) return;

            const target = document.getElementById(button.dataset.statePerformanceToggle);
            if (!target) return;

            const willOpen = target.hidden;
            target.hidden = !willOpen;
            button.textContent = willOpen ? "-" : "+";
            button.setAttribute("aria-expanded", willOpen ? "true" : "false");
        });
    }

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/performance_summary.php", queryParams(), {
                loader: false,
                showError: false
            });
            const data = response.data || {};
            renderSummary(data.summary || {});
            renderRows(data.rows || []);
            state.pager.set(data.pagination || {}).render("statePerfPager", "Showing");
        } catch (error) {
            document.getElementById("statePerfSummary").innerHTML = "";
            document.getElementById("statePerfRows").innerHTML =
                `<div class="sq-state-empty">${esc(error.message || "Unable to load performance monitoring data.")}</div>`;
            state.pager.set({ page: 1, total_pages: 1, total_rows: 0 }).render("statePerfPager", "Showing");
        }
    }

    async function init() {
        state.pager = SQ.pagination.create({ page: 1, perPage: 50, onChange: load });
        bindToggle();
        document.getElementById("statePerfRefresh")?.addEventListener("click", function () {
            state.pager.reset();
            load();
        });
        bindSearch("statePerfSearch");
        await load();
    }

    SQ.statePerformance = { init };
})(window, document);
