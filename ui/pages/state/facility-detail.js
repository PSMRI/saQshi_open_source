/*!
 * ==========================================================
 * SaQshi Open Source
 * State Facility Drill-down
 * facility-detail.js
 * Version 1.2.0 | Updated 2026-07-13
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    const state = {
        hierarchy: [],
        nodeMap: new Map()
    };

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function html(id, value) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = value;
    }

    function text(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function empty(message) {
        return `<div class="sq-state-empty">${esc(message || "No data available.")}</div>`;
    }

    function nodeId(prefix, path) {
        return `${prefix}-${path.join("-").replace(/[^a-zA-Z0-9_-]/g, "_")}`;
    }

    function registerNode(id, type, node) {
        state.nodeMap.set(id, { type, node });
        return id;
    }

    function renderToggleRow(id, label, count, level) {
        return `
            <div class="sq-state-block-row sq-state-tree-row" style="margin-left:${level * 10}px">
                <button class="sq-state-plus" type="button" data-tree-toggle="${esc(id)}" aria-expanded="false">+</button>
                <div>
                    <strong>${esc(label || "-")}</strong>
                    <small>${esc(count || 0)} facilities</small>
                </div>
            </div>
            <div id="${esc(id)}" class="sq-state-tree-children" hidden></div>
        `;
    }

    function renderFacilityRow(facility, level) {
        return `
            <button class="sq-state-block-row sq-state-tree-row sq-state-facility-row" type="button" data-facility-id="${esc(facility.fac_id)}" style="margin-left:${level * 10}px">
                <div>
                    <strong>${esc(facility.fac_name || "-")}</strong>
                    <small>${esc(facility.facility_type || "-")} | NIN ${esc(facility.nin || "-")}</small>
                </div>
            </button>
        `;
    }

    function childHtml(id) {
        const entry = state.nodeMap.get(id);
        if (!entry) return empty("No child records found.");

        if (entry.type === "state") {
            return (entry.node.divisions || []).map((division, index) => {
                const childId = registerNode(nodeId("division", [id, index]), "division", division);
                return renderToggleRow(childId, division.name, division.count, 1);
            }).join("") || empty("No divisions found.");
        }

        if (entry.type === "division") {
            return (entry.node.districts || []).map((district, index) => {
                const childId = registerNode(nodeId("district", [id, index]), "district", district);
                return renderToggleRow(childId, district.name, district.count, 2);
            }).join("") || empty("No districts found.");
        }

        if (entry.type === "district") {
            return (entry.node.blocks || []).map((block, index) => {
                const childId = registerNode(nodeId("block", [id, index]), "block", block);
                return renderToggleRow(childId, block.name, block.count, 3);
            }).join("") || empty("No blocks found.");
        }

        if (entry.type === "block") {
            return (entry.node.facilities || []).map(facility => renderFacilityRow(facility, 4)).join("") || empty("No facilities found.");
        }

        return empty("No child records found.");
    }

    function renderTree() {
        state.nodeMap.clear();
        html("stateFacilityTree", state.hierarchy.length
            ? `<div class="sq-state-block-list">${state.hierarchy.map((item, index) => {
                const id = registerNode(nodeId("state", [index]), "state", item);
                return renderToggleRow(id, item.name, item.count, 0);
            }).join("")}</div>`
            : empty("No facility hierarchy found."));
    }

    async function loadHierarchy() {
        const response = await SQ.api.get("/state/v1/facility_detail.php", {
            mode: "hierarchy",
            search: document.getElementById("stateFacilitySearch")?.value || ""
        }, {
            loader: false,
            showError: false
        });
        const data = response.data || {};
        state.hierarchy = data.states || [];
        text("stateFacilityTreeCount", `${data.total_facilities || 0} facilities`);
        renderTree();
    }

    function renderFacilityInfo(facility) {
        html("stateFacilityInfo", Object.keys(facility).length
            ? `<div class="sq-state-list">
                <div class="sq-state-row"><span>Name</span><b>${esc(facility.fac_name)}</b></div>
                <div class="sq-state-row"><span>State</span><b>${esc(facility.state_name)}</b></div>
                <div class="sq-state-row"><span>Division</span><b>${esc(facility.division)}</b></div>
                <div class="sq-state-row"><span>District</span><b>${esc(facility.Dist_Name)}</b></div>
                <div class="sq-state-row"><span>Block</span><b>${esc(facility.Block_Name)}</b></div>
                <div class="sq-state-row"><span>NIN</span><b>${esc(facility.NIN_no)}</b></div>
            </div>`
            : empty("Facility not found."));
    }

    function renderSummary(summary) {
        const assessments = summary.assessments || {};
        const performance = summary.performance || {};
        const cqi = summary.cqi || {};

        html("stateFacilitySummary", `
            <div><span>Assessments</span><strong>${esc(assessments.total || 0)}</strong></div>
            <div><span>Completed</span><strong>${esc(assessments.completed || 0)}</strong></div>
            <div><span>In Progress</span><strong>${esc((assessments.active || 0) + (assessments.in_progress || 0))}</strong></div>
            <div><span>Cancelled</span><strong>${esc(assessments.cancelled || 0)}</strong></div>
            <div><span>KPI Entries</span><strong>${esc(performance.kpi_entries || 0)}</strong></div>
            <div><span>Outcome Entries</span><strong>${esc(performance.outcome_entries || 0)}</strong></div>
            <div><span>Open Gaps</span><strong>${esc(cqi.open_gaps || 0)}</strong></div>
            <div><span>CQI Overdue</span><strong>${esc(cqi.overdue || 0)}</strong></div>
        `);
    }

    function renderAssessments(assessments, performance) {
        html("stateFacilityAssessments", `
            <div class="sq-state-grid">
                <div>
                    <h3>Assessment History</h3>
                    ${assessments.length
                        ? `<table class="sq-state-table">
                            <thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Period</th></tr></thead>
                            <tbody>${assessments.map(item => `
                                <tr>
                                    <td>${esc(item.assessment_id)}</td>
                                    <td>${esc(item.assessment_name)}</td>
                                    <td><span class="sq-state-badge">${esc(item.status)}</span></td>
                                    <td>${esc(item.start_date || "-")} to ${esc(item.end_date || "-")}</td>
                                </tr>
                            `).join("")}</tbody>
                        </table>`
                        : empty("No assessments found.")}
                </div>
                <div>
                    <h3>Performance Entries</h3>
                    ${performance.length
                        ? `<table class="sq-state-table">
                            <thead><tr><th>Type</th><th>Month</th><th>Entries</th></tr></thead>
                            <tbody>${performance.map(item => `
                                <tr>
                                    <td>${esc(item.indicator_type)}</td>
                                    <td>${esc(item.entry_year)}-${String(item.entry_month || "").padStart(2, "0")}</td>
                                    <td>${esc(item.entries || 0)}</td>
                                </tr>
                            `).join("")}</tbody>
                        </table>`
                        : empty("No KPI or Outcome entries found.")}
                </div>
            </div>
        `);
    }

    async function loadFacility(facilityId) {
        const response = await SQ.api.get("/state/v1/facility_detail.php", { fac_id: facilityId }, {
            loader: false,
            showError: false
        });
        const data = response.data || {};
        const facility = data.facility || {};

        text("stateFacilitySelected", facility.fac_name || "Selected facility");
        renderFacilityInfo(facility);
        renderSummary(data.summary || {});
        renderAssessments(data.assessments || [], data.performance || []);
    }

    function bindTree() {
        document.getElementById("stateFacilityTree")?.addEventListener("click", function (event) {
            const toggle = event.target.closest("[data-tree-toggle]");
            if (toggle) {
                const id = toggle.getAttribute("data-tree-toggle");
                const target = document.getElementById(id);
                if (!target) return;

                const willOpen = target.hidden;
                if (willOpen && !target.dataset.rendered) {
                    target.innerHTML = childHtml(id);
                    target.dataset.rendered = "1";
                }
                target.hidden = !willOpen;
                toggle.textContent = willOpen ? "-" : "+";
                toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
                return;
            }

            const facilityButton = event.target.closest("[data-facility-id]");
            if (facilityButton) {
                loadFacility(facilityButton.getAttribute("data-facility-id")).catch(error => {
                    html("stateFacilityInfo", empty(error.message || "Unable to load facility details."));
                });
            }
        });
    }

    async function init() {
        bindTree();
        document.getElementById("stateFacilityLoad")?.addEventListener("click", loadHierarchy);
        document.getElementById("stateFacilitySearch")?.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                loadHierarchy();
            }
        });
        html("stateFacilityInfo", empty("Select a facility from the hierarchy."));
        html("stateFacilitySummary", "");
        html("stateFacilityAssessments", "");
        await loadHierarchy();
    }

    SQ.stateFacilityDetail = { init };
})(window, document);
