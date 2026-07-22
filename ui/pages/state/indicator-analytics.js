/*!
 * ==========================================================
 * SaQshi Open Source
 * State Indicator Analytics
 * indicator-analytics.js
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

    function params() {
        return state.pager.params({
            search: document.getElementById("stateIndicatorSearch")?.value || "",
            min_facilities: document.getElementById("stateIndicatorMinFacilities")?.value || 1
        });
    }

    function createPager() {
        if (SQ.pagination && typeof SQ.pagination.create === "function") {
            return SQ.pagination.create({ page: 1, perPage: 25, onChange: load });
        }
        return {
            params(extra) {
                return Object.assign({ page: 1, per_page: 25 }, extra || {});
            },
            set() {
                return this;
            },
            render() {
                return this;
            },
            reset() {
                return this;
            }
        };
    }

    function renderSummary(data) {
        const a = data.assessment?.summary || {};
        document.getElementById("stateIndicatorSummary").innerHTML = `
            <div><span>Assessment Indicators</span><strong>${esc(a.indicators || 0)}</strong></div>
            <div><span>Assessment Facilities</span><strong>${esc(a.facilities || 0)}</strong></div>
            <div><span>Total Responses</span><strong>${esc(a.responses || 0)}</strong></div>
            <div><span>Minimum Facilities</span><strong>${esc(document.getElementById("stateIndicatorMinFacilities")?.value || 1)}</strong></div>
        `;
    }

    function renderAssessment(rows) {
        if (!rows || !rows.length) {
            document.getElementById("stateAssessmentIndicators").innerHTML = `<div class="sq-state-empty">No assessment indicator analytics available.</div>`;
            return;
        }

        document.getElementById("stateAssessmentIndicators").innerHTML = `
            <table class="sq-state-table">
                <thead>
                    <tr>
                        <th>Checkpoint</th>
                        <th>Department</th>
                        <th>Standard</th>
                        <th>Facilities Scored 0</th>
                        <th>Zero Responses</th>
                        <th>Download</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(function (row) {
                        return `
                            <tr>
                                <td><b>${esc(row.indicator_name)}</b><div>${esc(row.area_of_concern || "")}</div></td>
                                <td>${esc(row.department || "")}</td>
                                <td>${esc(row.standard || "")}</td>
                                <td><b>${esc(row.zero_facility_count || 0)}</b></td>
                                <td>${esc(row.zero_count || 0)}</td>
                                <td>
                                    <button class="sq-btn sq-btn-primary" type="button" data-zero-download="${esc(row.download_key || row.checkpoint_id)}">
                                        Facilities
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join("")}
                </tbody>
            </table>
        `;
    }

    async function load() {
        const response = await SQ.api.get("/state/v1/indicator_analytics.php", params(), {
            loader: false,
            showError: false
        });
        const data = response.data || {};
        renderSummary(data);
        renderAssessment(data.assessment?.rows || []);
        state.pager.set(data.assessment?.pagination || {}).render("stateIndicatorPager", "Showing indicators");
    }

    async function downloadFacilities(checkpointId) {
        await SQ.api.download("/state/v1/indicator_analytics.php", Object.assign({}, params(), {
            download: "zero_facilities",
            checkpoint_id: checkpointId
        }), `zero-score-facilities-${checkpointId}.csv`);
    }

    async function init() {
        state.pager = createPager();
        document.getElementById("stateIndicatorRefresh")?.addEventListener("click", function () {
            state.pager.reset();
            load();
        });
        let timer = null;
        document.getElementById("stateIndicatorSearch")?.addEventListener("input", function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                state.pager.reset();
                load();
            }, 350);
        });
        document.getElementById("stateIndicatorMinFacilities")?.addEventListener("change", function () {
            state.pager.reset();
            load();
        });
        document.getElementById("stateAssessmentIndicators")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-zero-download]");
            if (!button) {
                return;
            }
            downloadFacilities(button.getAttribute("data-zero-download"));
        });
        await load();
    }

    SQ.stateIndicatorAnalytics = { init };
})(window, document);
