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

    function domainLabel(key, fallback) {
        return SQ.deployment && typeof SQ.deployment.label === "function"
            ? SQ.deployment.label(key, fallback)
            : fallback;
    }

    function moduleEnabled(key) {
        return !SQ.deployment || typeof SQ.deployment.moduleEnabled !== "function" || SQ.deployment.moduleEnabled(key);
    }

    function nodeId(prefix, path) {
        return `${prefix}-${path.join("-").replace(/[^a-zA-Z0-9_-]/g, "_")}`;
    }

    function registerNode(id, type, node) {
        state.nodeMap.set(id, { type, node });
        return id;
    }

    function categoryBadge(category, percentage) {
        const name = String(category?.name || "");
        if (!name) return "";
        const css = name.toLowerCase() === "abhilasha" ? "is-abhilasha" : name.toLowerCase() === "pragati" ? "is-pragati" : "is-jagriti";
        const score = Number.isFinite(Number(percentage)) ? ` ${Number(percentage).toFixed(2).replace(/\.00$/, "")}%` : "";
        return `<span class="sq-score-category ${css}">${esc(name + score)}</span>`;
    }

    function domainLink(id, node, force) {
        return (force || (Array.isArray(node?.domains) && node.domains.length))
            ? `<button class="sq-state-domain-link" type="button" data-hierarchy-domain="${esc(id)}">View domain-wise scores</button>`
            : "";
    }

    function renderToggleRow(id, node, level) {
        const label = node?.name;
        const count = node?.count;
        return `
            <div class="sq-state-block-row sq-state-tree-row" style="margin-left:${level * 10}px">
                <button class="sq-state-plus" type="button" data-tree-toggle="${esc(id)}" aria-expanded="false">+</button>
                <div>
                    <strong>${esc(label || "-")}</strong>
                    <small>${esc(count || 0)} ${esc(domainLabel("facilities", "facilities").toLowerCase())}</small>
                </div>
                ${categoryBadge(node?.category, node?.score_percent)}
                ${domainLink(id, node)}
            </div>
            <div id="${esc(id)}" class="sq-state-tree-children" hidden></div>
        `;
    }

    function renderFacilityRow(id, facility, level) {
        return `
            <div class="sq-state-block-row sq-state-tree-row sq-state-facility-row" data-facility-id="${esc(facility.fac_id)}" style="margin-left:${level * 10}px">
                <div>
                    <strong>${esc(facility.fac_name || "-")}</strong>
                    <small>${esc(facility.facility_type || "-")} | ${esc(domainLabel("facility_code", "NIN"))} ${esc(facility.nin || "-")}</small>
                </div>
                ${categoryBadge(facility.category, facility.score_percent)}
                ${domainLink(id, facility, true)}
            </div>
        `;
    }

    function childHtml(id) {
        const entry = state.nodeMap.get(id);
        if (!entry) return empty("No child records found.");

        if (entry.type === "state") {
            return (entry.node.divisions || []).map((division, index) => {
                const childId = registerNode(nodeId("division", [id, index]), "division", division);
                return renderToggleRow(childId, division, 1);
            }).join("") || empty("No divisions found.");
        }

        if (entry.type === "division") {
            return (entry.node.districts || []).map((district, index) => {
                const childId = registerNode(nodeId("district", [id, index]), "district", district);
                return renderToggleRow(childId, district, 2);
            }).join("") || empty("No districts found.");
        }

        if (entry.type === "district") {
            return (entry.node.blocks || []).map((block, index) => {
                const childId = registerNode(nodeId("block", [id, index]), "block", block);
                return renderToggleRow(childId, block, 3);
            }).join("") || empty("No blocks found.");
        }

        if (entry.type === "block") {
            return (entry.node.facilities || []).map((facility, index) => {
                const childId = registerNode(nodeId("facility", [id, index]), "facility", facility);
                return renderFacilityRow(childId, facility, 4);
            }).join("") || empty("No facilities found.");
        }

        return empty("No child records found.");
    }

    function renderTree() {
        state.nodeMap.clear();
        html("stateFacilityTree", state.hierarchy.length
            ? `<div class="sq-state-block-list">${state.hierarchy.map((item, index) => {
                const id = registerNode(nodeId("state", [index]), "state", item);
                return renderToggleRow(id, item, 0);
            }).join("")}</div>`
            : empty("No facility hierarchy found."));
    }

    function openDomainDialog(node) {
        if (!node?.domains?.length) return;
        document.getElementById("stateHierarchyDomainDialog")?.remove();
        const value = Math.max(0, Math.min(100, Number(node.score_percent || 0)));
        const modal = document.createElement("div");
        modal.id = "stateHierarchyDomainDialog";
        modal.className = "sq-state-modal";
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.innerHTML = `<div class="sq-state-modal-panel sq-state-domain-dialog"><div class="sq-card-header"><div><h3>${esc(node.name || "School")} Domain-wise Scores</h3><p>Latest completed assessment round aggregate</p></div><button type="button" class="sq-btn sq-btn-light" data-close-hierarchy-dialog>Close</button></div><div class="sq-card-body">${node.domains.map((domain, index) => { const percent = Math.max(0, Math.min(100, Number(domain.percentage || 0))); return `<div class="sq-domain-score ${["is-domain-blue","is-domain-teal","is-domain-purple","is-domain-orange","is-domain-red"][index % 5]}"><div class="sq-domain-score-title"><strong>${esc(domain.model_name)}</strong><b>${esc(percent.toFixed(2).replace(/\.00$/, ""))}%</b></div><div class="sq-domain-score-bar"><span style="width:${percent}%"></span></div><div class="sq-domain-score-meta">${esc(Number(domain.obtained_score || 0).toFixed(2))} / ${esc(Number(domain.total_score || 0).toFixed(2))} score</div><div class="sq-domain-score-category">${esc(domain.category?.name || "-")}</div></div>`; }).join("")}<div class="sq-domain-score-total"><span>Overall category</span><strong>${esc(value.toFixed(2))}% · ${esc(node.category?.name || "-")}</strong></div></div></div>`;
        document.body.appendChild(modal);
        modal.addEventListener("click", event => { if (event.target === modal || event.target.closest("[data-close-hierarchy-dialog]")) modal.remove(); });
    }

    async function loadFacilityDomains(facility) {
        const response = await SQ.api.get("/state/v1/facility_detail.php", {
            mode: "hierarchy",
            search: facility.nin || facility.fac_id,
            include_domains: 1
        }, { loader: false, showError: false });
        const stateNode = response.data?.states?.[0];
        const school = stateNode?.divisions?.[0]?.districts?.[0]?.blocks?.[0]?.facilities?.[0];
        if (school?.domains?.length) openDomainDialog(school);
    }

    async function loadHierarchy() {
        text("stateFacilityTreeCount", `Loading ${domainLabel("facilities", "facilities").toLowerCase()}...`);
        try {
            const response = await SQ.api.get("/state/v1/facility_detail.php", {
                mode: "hierarchy",
                search: document.getElementById("stateFacilitySearch")?.value || ""
            }, {
                loader: false,
                showError: false
            });
            const data = response.data || {};
            state.hierarchy = data.states || [];
            text("stateFacilityTreeCount", `${data.total_facilities || 0} ${domainLabel("facilities", "facilities").toLowerCase()}`);
            renderTree();
        } catch (error) {
            state.hierarchy = [];
            text("stateFacilityTreeCount", `Unable to load ${domainLabel("facilities", "facilities").toLowerCase()}`);
            html("stateFacilityTree", empty(error.message || "School hierarchy could not be loaded."));
            SQ.notification?.error(error.message || "Unable to load school hierarchy.");
        }
    }

    function renderFacilityInfo(facility) {
        html("stateFacilityInfo", Object.keys(facility).length
            ? `<div class="sq-state-list">
                <div class="sq-state-row"><span>Name</span><b>${esc(facility.fac_name)}</b></div>
                <div class="sq-state-row"><span>State</span><b>${esc(facility.state_name)}</b></div>
                <div class="sq-state-row"><span>Division</span><b>${esc(facility.division)}</b></div>
                <div class="sq-state-row"><span>District</span><b>${esc(facility.Dist_Name)}</b></div>
                <div class="sq-state-row"><span>Block</span><b>${esc(facility.Block_Name)}</b></div>
                <div class="sq-state-row"><span>${esc(domainLabel("facility_code", "NIN"))}</span><b>${esc(facility.NIN_no)}</b></div>
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
            ${moduleEnabled("performance") ? `<div><span>Performance Entries</span><strong>${esc((performance.kpi_entries || 0) + (performance.outcome_entries || 0))}</strong></div>` : ""}
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
                                    <td>Start ${esc(item.start_date || "-")}<br><small>Planned end ${esc(item.end_date || "-")}</small>${item.completed_on ? `<br><small>Completed ${esc(item.completed_on)}</small>` : ""}${item.cancelled_on ? `<br><small>Cancelled ${esc(item.cancelled_on)}</small>` : ""}</td>
                                </tr>
                            `).join("")}</tbody>
                        </table>`
                        : empty("No assessments found.")}
                </div>
                ${moduleEnabled("performance") ? `<div>
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
                </div>` : ""}
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
            const domainButton = event.target.closest("[data-hierarchy-domain]");
            if (domainButton) {
                event.preventDefault();
                event.stopPropagation();
                const entry = state.nodeMap.get(domainButton.getAttribute("data-hierarchy-domain"));
                if (entry?.type === "facility") {
                    loadFacilityDomains(entry.node).catch(error => html("stateFacilityInfo", empty(error.message || "Unable to load school domain scores.")));
                } else {
                    openDomainDialog(entry?.node);
                }
                return;
            }
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
        if (SQ.deployment && typeof SQ.deployment.load === "function") {
            await SQ.deployment.load();
            SQ.deployment.applyLabels(document);
        }
        bindTree();
        document.getElementById("stateFacilityLoad")?.addEventListener("click", loadHierarchy);
        document.getElementById("stateFacilitySearch")?.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                loadHierarchy();
            }
        });
        html("stateFacilityInfo", empty("Select a school from the hierarchy."));
        html("stateFacilitySummary", "");
        html("stateFacilityAssessments", "");
        await loadHierarchy();
        const selectedFacilityId = Number(new URLSearchParams(window.location.search).get("fac_id") || 0);
        if (selectedFacilityId > 0) await loadFacility(selectedFacilityId);
    }

    SQ.stateFacilityDetail = { init };
})(window, document);
