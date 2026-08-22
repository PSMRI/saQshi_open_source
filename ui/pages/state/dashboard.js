/*!
 * ==========================================================
 * SaQshi Open Source
 * State Monitoring Dashboard
 * dashboard.js
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    let latestCategorySummary = {};

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

    function setHtml(id, value) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = value;
    }

    function empty(message) {
        return `<div class="sq-state-empty">${esc(message || "No data available.")}</div>`;
    }

    function list(rows, label, value) {
        return rows && rows.length
            ? `<div class="sq-state-list">${rows.map(row => `
                <div class="sq-state-row">
                    <span>${esc(row[label] || "-")}</span>
                    <b>${esc(row[value] || 0)}</b>
                </div>
            `).join("")}</div>`
            : empty("No data available.");
    }

    function renderSchoolCategoryCards(summary) {
        const container = document.getElementById("stateSchoolCategoryCards");
        if (!container) return;
        const categories = Array.isArray(summary?.categories) ? summary.categories : [];
        latestCategorySummary = summary || {};
        container.hidden = categories.length === 0;
        container.innerHTML = categories.map(row => `
            <div class="sq-state-category-card" style="--category-color:${esc(row.color || "#64748b")}">
                <strong>${esc(row.count || 0)}</strong>
                <span>Schools in ${esc(row.category || "Category")}</span>
            </div>
        `).join("");
        renderCategoryBreakdown(summary);
        renderCategoryGraphs(summary);
    }

    function categoryColor(category) {
        return category === "Jagriti" ? "#16a34a" : category === "Pragati" ? "#f59e0b" : "#dc2626";
    }

    function renderCategoryBreakdown(summary) {
        const container = document.getElementById("stateCategoryBreakdown");
        if (!container) return;
        const section = (title, rows, showDivision) => `
            <details class="sq-state-breakdown-panel">
                <summary><span>${esc(title)}</span><button type="button" class="sq-state-download-image" data-export-scope="${showDivision ? "districts" : "divisions"}" data-export-view="table" title="Download as image"><i class="bi bi-download"></i> Image</button></summary>
                <div class="sq-state-breakdown-table">
                    ${rows.length ? rows.map(row => `
                        <div class="sq-state-breakdown-row">
                            <div class="sq-state-breakdown-name">
                                <strong>${esc(row.name || "-")}</strong>
                                ${showDivision ? `<small>${esc(row.division || "")}</small>` : ""}
                            </div>
                            <span>${esc(row.percentage || 0)}%</span>
                            <b class="sq-state-category-label" style="--category-color:${categoryColor(row.category)}">${esc(row.category || "Not Categorised")}</b>
                            <div class="sq-state-distribution">
                                <i class="is-abhilasha">Abhilasha ${esc(row.distribution?.Abhilasha || 0)}</i>
                                <i class="is-pragati">Pragati ${esc(row.distribution?.Pragati || 0)}</i>
                                <i class="is-jagriti">Jagriti ${esc(row.distribution?.Jagriti || 0)}</i>
                            </div>
                        </div>
                    `).join("") : empty("No assessed schools available.")}
                </div>
            </details>`;
        const divisions = Array.isArray(summary?.divisions) ? summary.divisions : [];
        const districts = Array.isArray(summary?.districts) ? summary.districts : [];
        container.innerHTML = section("Division-wise Categorisation", divisions, false) + section("District-wise Categorisation", districts, true);
    }

    function renderCategoryGraphs(summary) {
        const container = document.getElementById("stateCategoryGraphs");
        if (!container) return;
        const graph = (title, sourceRows, showDivision, districtGraph) => {
            const rows = [...sourceRows].sort((a, b) => Number(b.percentage || 0) - Number(a.percentage || 0));
            return `
                <details class="sq-state-graph-panel${districtGraph ? " is-district" : ""}">
                    <summary><span>${esc(title)}</span><button type="button" class="sq-state-download-image" data-export-scope="${districtGraph ? "districts" : "divisions"}" data-export-view="graph" title="Download as image"><i class="bi bi-download"></i> Image</button></summary>
                    <div class="sq-state-graph-legend">
                        <span class="is-abhilasha">Abhilasha</span><span class="is-pragati">Pragati</span><span class="is-jagriti">Jagriti</span>
                    </div>
                    <div class="sq-state-bar-chart">
                        ${rows.length ? rows.map(row => {
                            const percentage = Math.max(0, Math.min(100, Number(row.percentage || 0)));
                            const tooltip = `${row.name}: ${percentage}% (${row.category}); ${row.school_count || 0} assessed schools; Abhilasha ${row.distribution?.Abhilasha || 0}, Pragati ${row.distribution?.Pragati || 0}, Jagriti ${row.distribution?.Jagriti || 0}`;
                            return `
                                <div class="sq-state-bar-row" title="${esc(tooltip)}">
                                    <div class="sq-state-bar-name"><strong>${esc(row.name || "-")}</strong>${showDivision ? `<small>${esc(row.division || "")}</small>` : ""}</div>
                                    <div class="sq-state-bar-track">
                                        <div class="sq-state-bar-fill" style="width:${percentage}%;--category-color:${categoryColor(row.category)}">
                                            <span>${esc(percentage)}%</span>
                                        </div>
                                    </div>
                                    <b style="color:${categoryColor(row.category)}">${esc(row.category || "Not Categorised")}</b>
                                </div>`;
                        }).join("") : empty("No assessed schools available.")}
                    </div>
                </details>`;
        };
        const divisions = Array.isArray(summary?.divisions) ? summary.divisions : [];
        const districts = Array.isArray(summary?.districts) ? summary.districts : [];
        container.innerHTML = graph("Division Graph", divisions, false, false) + graph("District Graph", districts, true, true);
    }

    function downloadCategoryImage(scope, view) {
        const source = Array.isArray(latestCategorySummary?.[scope]) ? latestCategorySummary[scope] : [];
        if (!source.length) return;
        const rows = view === "graph"
            ? [...source].sort((a, b) => Number(b.percentage || 0) - Number(a.percentage || 0))
            : source;
        const width = 1600;
        const rowHeight = view === "graph" ? 52 : 48;
        const canvas = document.createElement("canvas");
        canvas.width = width;
        canvas.height = 150 + rows.length * rowHeight;
        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = "#10213b";
        ctx.font = "700 30px Arial";
        ctx.fillText(`${scope === "divisions" ? "Division" : "District"}-wise Categorisation${view === "graph" ? " Graph" : ""}`, 40, 48);
        ctx.font = "600 17px Arial";
        [["Abhilasha", "#dc2626"], ["Pragati", "#f59e0b"], ["Jagriti", "#16a34a"]].forEach((item, index) => {
            const x = 40 + index * 190;
            ctx.fillStyle = item[1];
            ctx.fillRect(x, 73, 18, 18);
            ctx.fillStyle = "#42536d";
            ctx.fillText(item[0], x + 27, 89);
        });
        rows.forEach((row, index) => {
            const y = 125 + index * rowHeight;
            const percentage = Math.max(0, Math.min(100, Number(row.percentage || 0)));
            const color = categoryColor(row.category);
            if (index % 2 === 0) {
                ctx.fillStyle = "#f7f9fc";
                ctx.fillRect(25, y - 25, width - 50, rowHeight);
            }
            ctx.fillStyle = "#172b4d";
            ctx.font = "700 16px Arial";
            ctx.fillText(String(row.name || "-").slice(0, 34), 40, y + 4);
            if (scope === "districts") {
                ctx.fillStyle = "#64748b";
                ctx.font = "13px Arial";
                ctx.fillText(String(row.division || "").slice(0, 34), 40, y + 21);
            }
            if (view === "graph") {
                const barX = 365;
                const barWidth = 770;
                ctx.fillStyle = "#e8edf5";
                ctx.fillRect(barX, y - 17, barWidth, 27);
                ctx.fillStyle = color;
                ctx.fillRect(barX, y - 17, Math.max(3, barWidth * percentage / 100), 27);
                ctx.fillStyle = "#172b4d";
                ctx.font = "700 16px Arial";
                ctx.fillText(`${percentage.toFixed(2)}%`, 1155, y + 3);
                ctx.fillStyle = color;
                ctx.fillText(String(row.category || "Not Categorised"), 1270, y + 3);
            } else {
                ctx.fillStyle = "#172b4d";
                ctx.font = "700 16px Arial";
                ctx.fillText(`${percentage.toFixed(2)}%`, 430, y + 4);
                ctx.fillStyle = color;
                ctx.fillText(String(row.category || "Not Categorised"), 555, y + 4);
                ctx.fillStyle = "#42536d";
                ctx.font = "600 15px Arial";
                ctx.fillText(`Schools: ${row.school_count || 0}`, 735, y + 4);
                ctx.fillStyle = "#dc2626";
                ctx.fillText(`Abhilasha ${row.distribution?.Abhilasha || 0}`, 900, y + 4);
                ctx.fillStyle = "#b77900";
                ctx.fillText(`Pragati ${row.distribution?.Pragati || 0}`, 1110, y + 4);
                ctx.fillStyle = "#16a34a";
                ctx.fillText(`Jagriti ${row.distribution?.Jagriti || 0}`, 1290, y + 4);
            }
        });
        canvas.toBlob(blob => {
            if (!blob) return;
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = `${scope}-${view}-categorisation.png`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }, "image/png");
    }

    function searchParams() {
        return {
            search: document.getElementById("stateDashboardSearch")?.value || ""
        };
    }

    function moduleEnabled(key) {
        return !SQ.deployment || typeof SQ.deployment.moduleEnabled !== "function" || SQ.deployment.moduleEnabled(key);
    }

    function isEducationProfile() {
        const profile = SQ.deployment?.current?.domain?.profile_code || SQ.deployment?.current?.modules?.active_profile || "";
        return String(profile).toLowerCase() === "education";
    }

    function applyMonitoringTitle() {
        const user = SQ.auth && typeof SQ.auth.getUser === "function" ? SQ.auth.getUser() : null;
        const roleId = Number(user && user.role_id);
        const baseLabel =
            roleId === 5 ? "Regional Monitoring Dashboard" :
            roleId === 4 ? "District Monitoring Dashboard" :
            roleId === 8 ? "Block Monitoring Dashboard" :
            "State Monitoring Dashboard";
        const facility = SQ.deployment && typeof SQ.deployment.label === "function"
            ? SQ.deployment.label("facility", "Facility")
            : "Facility";
        const isSchool = String(facility).toLowerCase() === "school";
        const label = isSchool ? baseLabel.replace(" Monitoring Dashboard", " School Monitoring Dashboard") : baseLabel;
        setText("stateMonitoringTitle", label);
        const pageTitle = document.getElementById("sq-page-title");
        const pageSubtitle = document.getElementById("sq-page-subtitle");
        if (pageTitle) pageTitle.textContent = label;
        if (pageSubtitle) pageSubtitle.textContent = `${roleId === 5 ? "Regional" : roleId === 4 ? "District" : roleId === 8 ? "Block" : "State"} admin view for ${String(facility).toLowerCase()} monitoring.`;
    }

    function applyModuleVisibility() {
        const certificationCard = document.getElementById("stateCertificationCard");
        if (certificationCard) certificationCard.hidden = !moduleEnabled("certification");

        // Abhilasha/Pragati/Jagriti are education assessment categories.
        // They are not applicable to the healthcare dashboard.
        ["stateSchoolCategoryCards", "stateCategoryBreakdown", "stateCategoryGraphs"].forEach(function (id) {
            const section = document.getElementById(id);
            if (section) section.hidden = !isEducationProfile();
        });
    }

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/dashboard.php", searchParams(), {
                loader: false,
                showError: false,
                redirectOnUnauthorized: false
            });
            const data = response.data || {};
            const current = data.current_month_status || {};
            const assessmentMonth = current.assessment || {};
            const assessmentSummary = data.assessment_summary || {};
            const performanceMonth = current.performance || {};

            setText("stateTotalFacilities", data.facility_category?.total_facilities || 0);
            setText("stateMonthAssessmentStarted", assessmentSummary.total || 0);
            setText("stateMonthAssessmentProgress", assessmentSummary.active || 0);
            setText("stateMonthAssessmentCompleted", assessmentSummary.completed || 0);
            if (isEducationProfile()) {
                renderSchoolCategoryCards(data.school_category_summary || {});
            }

            setHtml("stateFacilityTypes", list(data.facility_category?.facility_types || [], "facility_type", "count"));
            setHtml("stateCertification", list(data.certification_summary?.status || [], "status", "count"));
            setHtml("statePerformance", `<div class="sq-state-list">
                    <div class="sq-state-row">
                        <span>Assessment started</span>
                        <b>${esc(assessmentMonth.started || 0)}</b>
                    </div>
                    <div class="sq-state-row">
                        <span>Assessment completed</span>
                        <b>${esc(assessmentMonth.completed || 0)}</b>
                    </div>
                    <div class="sq-state-row">
                        <span>Assessment in progress</span>
                        <b>${esc(assessmentMonth.in_progress || 0)}</b>
                    </div>
                    ${moduleEnabled("performance") ? `<div class="sq-state-row">
                        <span>Performance entries</span>
                        <b>${esc((performanceMonth.kpi_filled || 0) + (performanceMonth.outcome_filled || 0))}</b>
                    </div>` : ""}
                </div>`);
        } catch (error) {
            console.error("[State Dashboard]", error);
            setHtml("stateFacilityTypes", empty(error.message || "State dashboard API failed."));
            setHtml("stateCertification", empty("Unable to load certification summary."));
            setHtml("statePerformance", empty("Unable to load performance summary."));
            if (isEducationProfile()) renderSchoolCategoryCards({});
            if (SQ.notification) SQ.notification.error(error.message || "Unable to load state dashboard.");
        }
    }

    async function init() {
        if (SQ.deployment && typeof SQ.deployment.load === "function") {
            await SQ.deployment.load();
            SQ.deployment.applyLabels(document);
        }
        applyModuleVisibility();
        applyMonitoringTitle();
        document.getElementById("stateRefresh")?.addEventListener("click", load);
        document.querySelector(".sq-state-page")?.addEventListener("click", function (event) {
            const button = event.target.closest(".sq-state-download-image");
            if (!button) return;
            event.preventDefault();
            event.stopPropagation();
            downloadCategoryImage(button.dataset.exportScope, button.dataset.exportView);
        });
        let searchTimer = null;
        document.getElementById("stateDashboardSearch")?.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(load, 300);
        });
        await load();
    }

    SQ.stateDashboard = { init };
})(window, document);
