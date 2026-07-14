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

    function searchParams() {
        return {
            search: document.getElementById("stateDashboardSearch")?.value || ""
        };
    }

    function applyMonitoringTitle() {
        const user = SQ.auth && typeof SQ.auth.getUser === "function" ? SQ.auth.getUser() : null;
        const roleId = Number(user && user.role_id);
        const label =
            roleId === 5 ? "Regional Monitoring Dashboard" :
            roleId === 4 ? "District Monitoring Dashboard" :
            roleId === 8 ? "Block Monitoring Dashboard" :
            "State Monitoring Dashboard";
        setText("stateMonitoringTitle", label);
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
            const performanceMonth = current.performance || {};

            setText("stateTotalFacilities", data.facility_category?.total_facilities || 0);
            setText("stateMonthAssessmentStarted", assessmentMonth.started || 0);
            setText("stateMonthAssessmentProgress", assessmentMonth.in_progress || 0);
            setText("stateMonthAssessmentCompleted", assessmentMonth.completed || 0);

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
                    <div class="sq-state-row">
                        <span>KPI filled</span>
                        <b>${esc(performanceMonth.kpi_filled || 0)}</b>
                    </div>
                    <div class="sq-state-row">
                        <span>Outcome filled</span>
                        <b>${esc(performanceMonth.outcome_filled || 0)}</b>
                    </div>
                </div>`);
        } catch (error) {
            console.error("[State Dashboard]", error);
            setHtml("stateFacilityTypes", empty(error.message || "State dashboard API failed."));
            setHtml("stateCertification", empty("Unable to load certification summary."));
            setHtml("statePerformance", empty("Unable to load performance summary."));
            if (SQ.notification) SQ.notification.error(error.message || "Unable to load state dashboard.");
        }
    }

    async function init() {
        applyMonitoringTitle();
        document.getElementById("stateRefresh")?.addEventListener("click", load);
        let searchTimer = null;
        document.getElementById("stateDashboardSearch")?.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(load, 300);
        });
        await load();
    }

    SQ.stateDashboard = { init };
})(window, document);
