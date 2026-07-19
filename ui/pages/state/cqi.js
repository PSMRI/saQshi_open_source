/*!
 * ==========================================================
 * SaQshi Open Source
 * State CQI Monitoring
 * cqi.js
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

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function num(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function progress(done, total) {
        const safeDone = num(done);
        const safeTotal = num(total);
        const pct = safeTotal > 0 ? Math.round((safeDone / safeTotal) * 100) : 0;

        return `
            <div class="sq-state-mini-progress">
                <div><span style="width:${Math.max(0, Math.min(100, pct))}%"></span></div>
                <small>${esc(safeDone)} / ${esc(safeTotal)}</small>
            </div>
        `;
    }

    function statusBadge(row) {
        if (num(row.overdue) > 0) {
            return `<span class="sq-state-badge sq-state-danger">Overdue</span>`;
        }
        if (num(row.pending) > 0) {
            return `<span class="sq-state-badge sq-state-warning">Pending</span>`;
        }
        return `<span class="sq-state-badge sq-state-latest">Completed</span>`;
    }

    function queryParams() {
        return state.pager.params({
            search: document.getElementById("stateCqiSearch")?.value || ""
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

    function renderRows(rows) {
        document.getElementById("stateCqiRows").innerHTML = rows.length
            ? `<table class="sq-state-table sq-state-cqi-table">
                <thead>
                    <tr>
                        <th>Facility</th>
                        <th>Assessment</th>
                        <th>Status</th>
                        <th>Action Plans</th>
                        <th>Pending</th>
                        <th>Overdue</th>
                        <th>Next Target</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>${rows.map(row => `
                    <tr>
                        <td>
                            <strong>${esc(row.fac_name || "-")}</strong>
                            <small>${esc(row.district || "-")} / ${esc(row.block || "-")}</small>
                        </td>
                        <td>
                            <strong>${esc(row.assessment_name || "-")}</strong>
                            <small>ID ${esc(row.assessment_id || "-")} | ${esc(row.assessment_status || "-")}</small>
                        </td>
                        <td>${statusBadge(row)}</td>
                        <td>${progress(row.completed || 0, row.total_action_plans || 0)}</td>
                        <td>${esc(row.pending || 0)}</td>
                        <td>${esc(row.overdue || 0)}</td>
                        <td>${esc(row.next_target_date || "-")}</td>
                        <td>${esc(row.last_updated_on || "-")}</td>
                    </tr>
                `).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No facility CQI action plans available.</div>`;
    }

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/cqi_summary.php", queryParams(), {
                loader: false,
                showError: false
            });
            const data = response.data || {};

            setText("stateCqiTotal", data.facilities_with_action_plan || 0);
            setText("stateCqiDone", data.completed || 0);
            setText("stateCqiPending", data.pending || 0);
            setText("stateCqiOverdue", data.overdue || 0);
            renderRows(data.rows || []);
            state.pager.set(data.pagination || {}).render("stateCqiPager", "Showing");
        } catch (error) {
            setText("stateCqiTotal", 0);
            setText("stateCqiDone", 0);
            setText("stateCqiPending", 0);
            setText("stateCqiOverdue", 0);
            document.getElementById("stateCqiRows").innerHTML =
                `<div class="sq-state-empty">${esc(error.message || "Unable to load CQI monitoring data.")}</div>`;
            state.pager.set({ page: 1, total_pages: 1, total_rows: 0 }).render("stateCqiPager", "Showing");
        }
    }

    async function init() {
        state.pager = SQ.pagination.create({ page: 1, perPage: 50, onChange: load });
        document.getElementById("stateCqiRefresh")?.addEventListener("click", function () {
            state.pager.reset();
            load();
        });
        bindSearch("stateCqiSearch");
        await load();
    }

    SQ.stateCqi = { init };
})(window, document);
