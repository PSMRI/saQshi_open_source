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
    const state = { pager: null, departmentId: 0, facilityTypeId: 0, district: "", areaOfConcern: "" };

    function domainLabel(key, fallback) {
        return SQ.deployment && typeof SQ.deployment.label === "function"
            ? SQ.deployment.label(key, fallback)
            : fallback;
    }

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
            min_facilities: document.getElementById("stateIndicatorMinFacilities")?.value || 1,
            department_id: state.departmentId || "",
            facility_type: state.facilityTypeId || "",
            district: state.district || "",
            area_of_concern: state.areaOfConcern || ""
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
            <div><span>Low-Score Checkpoints</span><strong>${esc(a.indicators || 0)}</strong></div>
            <div><span>Affected Facilities</span><strong>${esc(a.facilities || 0)}</strong></div>
            <div><span>Low-Score Responses</span><strong>${esc(a.responses || 0)}</strong></div>
            <div><span>Assessment Coverage</span><strong>${esc(a.assessed_facilities || 0)} / ${esc(a.total_facilities || 0)}</strong></div>
        `;
    }

    function renderDepartmentRisks(rows) {
        const target = document.getElementById("stateDepartmentRisks");
        const clearButton = document.getElementById("stateIndicatorClearDepartment");
        if (!target) return;
        if (clearButton) clearButton.hidden = !state.departmentId;

        if (!rows || !rows.length) {
            target.innerHTML = `<div class="sq-state-empty">No department risks match the selected filters.</div>`;
            return;
        }

        target.innerHTML = `
            <div class="sq-state-list">
                ${rows.map(row => `
                    <button class="sq-state-risk-row" type="button" data-department-risk="${esc(row.dept_id)}">
                        <span><strong>${esc(row.department_name)}</strong><small>${esc(row.low_score_checkpoints)} low-score checkpoints</small></span>
                        <span class="sq-state-badge sq-state-danger">${esc(row.affected_facilities)} facilities</span>
                        <span>${esc(row.low_score_responses)} responses</span>
                    </button>
                `).join("")}
            </div>
        `;
    }

    function renderFacilityTypeRisks(rows) {
        const target = document.getElementById("stateFacilityTypeRisks");
        const clearButton = document.getElementById("stateIndicatorClearFacilityType");
        if (!target) return;
        if (clearButton) clearButton.hidden = !state.facilityTypeId;

        if (!rows || !rows.length) {
            target.innerHTML = `<div class="sq-state-empty">No facility type risks match the selected filters.</div>`;
            return;
        }

        target.innerHTML = `
            <div class="sq-state-list">
                ${rows.map(row => `
                    <button class="sq-state-risk-row" type="button" data-facility-type-risk="${esc(row.facility_type_id)}">
                        <span><strong>${esc(row.facility_type_name)}</strong><small>${esc(row.low_score_checkpoints)} low-score checkpoints</small></span>
                        <span class="sq-state-badge sq-state-danger">${esc(row.affected_facilities)} facilities</span>
                        <span>${esc(row.low_score_responses)} responses</span>
                    </button>
                `).join("")}
            </div>
        `;
    }

    function renderDistrictRisks(rows) {
        const target = document.getElementById("stateDistrictRisks");
        const clearButton = document.getElementById("stateIndicatorClearDistrict");
        if (!target) return;
        if (clearButton) clearButton.hidden = !state.district;

        if (!rows || !rows.length) {
            target.innerHTML = `<div class="sq-state-empty">No district risks match the selected filters.</div>`;
            return;
        }

        target.innerHTML = `
            <div class="sq-state-list">
                ${rows.map(row => `
                    <button class="sq-state-risk-row" type="button" data-district-risk="${esc(row.district_name)}">
                        <span><strong>${esc(row.district_name)}</strong><small>${esc(row.low_score_checkpoints)} low-score checkpoints</small></span>
                        <span class="sq-state-badge sq-state-danger">${esc(row.affected_facilities)} facilities</span>
                        <span>${esc(row.low_score_responses)} responses</span>
                    </button>
                `).join("")}
            </div>
        `;
    }

    function renderAreaOfConcernRisks(rows) {
        const target = document.getElementById("stateAreaOfConcernRisks");
        const clearButton = document.getElementById("stateIndicatorClearArea");
        if (!target) return;
        if (clearButton) clearButton.hidden = !state.areaOfConcern;
        if (!rows || !rows.length) {
            target.innerHTML = `<div class="sq-state-empty">No Area of Concern risks match the selected filters.</div>`;
            return;
        }
        target.innerHTML = `
            <div class="sq-state-list">
                ${rows.map(row => `
                    <button class="sq-state-risk-row" type="button" data-area-of-concern-risk="${esc(row.area_of_concern)}">
                        <span><strong>${esc(row.area_of_concern)}</strong><small>${esc(row.low_score_checkpoints)} low-score checkpoints</small></span>
                        <span class="sq-state-badge sq-state-danger">${esc(row.affected_facilities)} facilities</span>
                        <span>${esc(row.low_score_responses)} responses</span>
                    </button>
                `).join("")}
            </div>
        `;
    }

    function renderCapacityBuildingRoadmap(data) {
        const target = document.getElementById("stateCapacityBuildingRoadmap");
        if (!target) return;
        const topics = data?.topics || [];
        if (!topics.length) {
            target.innerHTML = `<div class="sq-state-empty">No capacity-building needs were identified from latest completed assessments in this scope.</div>`;
            return;
        }
        const priorityClass = function (priority) {
            return priority === "HIGH" ? "sq-state-danger" : (priority === "MEDIUM" ? "sq-state-warning" : "sq-state-success");
        };
        target.innerHTML = `
            <p class="sq-state-roadmap-note">Based on ${esc(data.assessed_facilities || 0)} facilities with a latest completed assessment. Themes are ranked using non-compliance, unresolved overdue CQI actions, and repeated gaps.</p>
            <div class="sq-state-list">
                ${topics.map(topic => `
                    <details class="sq-state-roadmap-item">
                        <summary>
                            <span><strong>${esc(topic.theme)}</strong><small>${esc(topic.affected_facilities)} affected facilities · ${esc(topic.low_score_checkpoints)} low-score checkpoints</small></span>
                            <span class="sq-state-badge ${priorityClass(topic.priority)}">${esc(topic.priority)} · ${esc(topic.suggested_planning_window)}</span>
                        </summary>
                        <div class="sq-state-roadmap-metrics">
                            <span><b>${esc(topic.score_0_facilities)}</b> with non-compliance</span>
                            <span><b>${esc(topic.score_1_only_facilities)}</b> partial only</span>
                            <span><b>${esc(topic.recurring_gap_facilities)}</b> recurring</span>
                            <span><b>${esc(topic.overdue_action_plan_facilities)}</b> overdue CQI</span>
                        </div>
                        <div class="sq-state-roadmap-facilities">
                            ${topic.facilities.map(facility => `<div><b>${esc(facility.fac_name || "Facility")}</b><span>${esc(facility.district || "-")} / ${esc(facility.block || "-")} · Score 0: ${esc(facility.score_0_responses)} · Score 1: ${esc(facility.score_1_responses)}${facility.recurring_gap ? " · Recurring" : ""}${facility.overdue_action_plan ? " · Overdue CQI" : ""}</span></div>`).join("")}
                        </div>
                    </details>
                `).join("")}
            </div>
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
                        <th>Risk Checkpoint</th>
                        <th>Department / Package</th>
                        <th>Affected Facilities</th>
                        <th>Low-Score Responses</th>
                        <th>Download</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(function (row) {
                        return `
                            <tr>
                                <td><b>${esc(row.indicator_name)}</b><div>${esc(row.area_of_concern || "")}</div></td>
                                <td><b>${esc(row.class_name || "-")}</b></td>
                                <td><b>${esc(row.low_score_facility_count || 0)}</b></td>
                                <td>${esc(row.low_score_count || 0)}</td>
                                <td>
                                    <button class="sq-btn sq-btn-primary" type="button" data-low-score-download="${esc(row.download_key || row.checkpoint_id)}">
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
        try {
            const response = await SQ.api.get("/state/v1/indicator_analytics.php", params(), {
                loader: false,
                showError: false
            });
            const data = response.data || {};
            renderSummary(data);
            renderCapacityBuildingRoadmap(data.capacity_building_roadmap || {});
            renderAreaOfConcernRisks(data.areas_of_concern || []);
            renderDistrictRisks(data.districts || []);
            renderFacilityTypeRisks(data.facility_types || []);
            renderDepartmentRisks(data.departments || []);
            renderAssessment(data.assessment?.rows || []);
            state.pager.set(data.assessment?.pagination || {}).render("stateIndicatorPager", "Showing indicators");
        } catch (error) {
            const message = esc(error?.message || "Unable to load State risk data.");
            document.getElementById("stateIndicatorSummary").innerHTML = "";
            ["stateCapacityBuildingRoadmap", "stateAreaOfConcernRisks", "stateDistrictRisks", "stateFacilityTypeRisks", "stateDepartmentRisks", "stateAssessmentIndicators"].forEach(function (id) {
                const target = document.getElementById(id);
                if (target) target.innerHTML = `<div class="sq-state-empty">${message}</div>`;
            });
            if (SQ.notification?.error) SQ.notification.error(error?.message || "Unable to load State risk data.");
        }
    }

    async function downloadFacilities(checkpointId) {
        await SQ.api.download("/state/v1/indicator_analytics.php", Object.assign({}, params(), {
            download: "low_score_facilities",
            checkpoint_id: checkpointId
        }), `low-score-facilities-${checkpointId}.csv`);
    }

    async function downloadCapacityBuildingRoadmap() {
        await SQ.api.download("/state/v1/indicator_analytics.php", Object.assign({}, params(), {
            download: "capacity_building_roadmap"
        }), "capacity-building-roadmap.csv");
    }

    async function init() {
        if (SQ.deployment && typeof SQ.deployment.load === "function") {
            await SQ.deployment.load();
            SQ.deployment.applyLabels(document);
        }
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
        document.getElementById("stateCapacityRoadmapDownload")?.addEventListener("click", function () {
            downloadCapacityBuildingRoadmap();
        });
        document.getElementById("stateDepartmentRisks")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-department-risk]");
            if (!button) return;
            state.departmentId = Number(button.getAttribute("data-department-risk")) || 0;
            state.pager.reset();
            load();
        });
        document.getElementById("stateFacilityTypeRisks")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-facility-type-risk]");
            if (!button) return;
            state.facilityTypeId = Number(button.getAttribute("data-facility-type-risk")) || 0;
            state.pager.reset();
            load();
        });
        document.getElementById("stateDistrictRisks")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-district-risk]");
            if (!button) return;
            state.district = button.getAttribute("data-district-risk") || "";
            state.pager.reset();
            load();
        });
        document.getElementById("stateAreaOfConcernRisks")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-area-of-concern-risk]");
            if (!button) return;
            state.areaOfConcern = button.getAttribute("data-area-of-concern-risk") || "";
            state.pager.reset();
            load();
        });
        document.getElementById("stateIndicatorClearDepartment")?.addEventListener("click", function () {
            state.departmentId = 0;
            state.pager.reset();
            load();
        });
        document.getElementById("stateIndicatorClearFacilityType")?.addEventListener("click", function () {
            state.facilityTypeId = 0;
            state.pager.reset();
            load();
        });
        document.getElementById("stateIndicatorClearDistrict")?.addEventListener("click", function () {
            state.district = "";
            state.pager.reset();
            load();
        });
        document.getElementById("stateIndicatorClearArea")?.addEventListener("click", function () {
            state.areaOfConcern = "";
            state.pager.reset();
            load();
        });
        document.getElementById("stateAssessmentIndicators")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-low-score-download]");
            if (!button) {
                return;
            }
            downloadFacilities(button.getAttribute("data-low-score-download"));
        });
        await load();
    }

    SQ.stateIndicatorAnalytics = { init };
})(window, document);
