/*!
 * ==========================================================
 * SaQshi Open Source
 * State Reports
 * reports.js
 * Version 1.2.0 | Updated 2026-07-13
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

    function searchParams() {
        return {
            search: document.getElementById("stateReportsSearch")?.value || ""
        };
    }

    function filenameFor(key) {
        return `saqshi-state-${String(key || "summary").replace(/_/g, "-")}-${new Date().toISOString().slice(0, 10)}.csv`;
    }

    function renderExports(exports) {
        const list = Array.isArray(exports) && exports.length ? exports : [
            { key: "summary", title: "State Summary", description: "Summary counts for all state monitoring sections." },
            { key: "facilities", title: "All Facility List", description: "Facility master details." },
            { key: "assessments", title: "Assessment Details", description: "Assessment status, score and action plan summary." },
            { key: "cqi", title: "CQI Details", description: "Action plan and gap closure details." },
            { key: "performance", title: "Performance Details", description: "KPI and Outcome entries." },
            { key: "certification", title: "Certification History", description: "Facility certification history." }
        ];

        document.getElementById("stateReportExports").innerHTML = list.map(function (item) {
            return `
                <div class="sq-state-export-item">
                    <div>
                        <b>${esc(item.title)}</b>
                        <span>${esc(item.description)}</span>
                    </div>
                    <button class="sq-btn sq-btn-primary" type="button" data-state-report-download="${esc(item.key)}">
                        Download
                    </button>
                </div>
            `;
        }).join("");
    }

    async function load() {
        const response = await SQ.api.get("/state/v1/reports.php", searchParams(), {
            loader: false,
            showError: false
        });
        const data = response.data || {};

        document.getElementById("stateReportSummary").innerHTML = `
            <div class="sq-state-list">
                <div class="sq-state-row"><span>Total Facilities</span><b>${esc(data.facility_category?.total_facilities || 0)}</b></div>
                <div class="sq-state-row"><span>Assessment Records</span><b>${esc(data.assessment_progress?.summary?.total || 0)}</b></div>
                <div class="sq-state-row"><span>CQI Pending</span><b>${esc(data.cqi_summary?.pending || 0)}</b></div>
                <div class="sq-state-row"><span>Performance Months</span><b>${esc((data.performance_summary?.months || []).length)}</b></div>
                <div class="sq-state-row"><span>Certification Records</span><b>${esc(data.certification_summary?.total || 0)}</b></div>
            </div>
        `;

        renderExports(data.exports || []);
    }

    async function downloadReport(key) {
        try {
            await SQ.api.download("/state/v1/reports.php", Object.assign({}, searchParams(), {
                download: key
            }), filenameFor(key));
        } catch (error) {
            if (SQ.toast) {
                SQ.toast("Report download failed. Please try again.", "danger");
            }
        }
    }

    async function init() {
        document.getElementById("stateReportsRefresh")?.addEventListener("click", load);
        let searchTimer = null;
        document.getElementById("stateReportsSearch")?.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(load, 300);
        });
        document.getElementById("stateReportExports")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-state-report-download]");
            if (!button) {
                return;
            }
            downloadReport(button.getAttribute("data-state-report-download"));
        });
        await load();
    }

    SQ.stateReports = { init };
})(window, document);
