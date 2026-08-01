(function (window, document) {
    "use strict";
    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    let reportRows = [];

    function esc(value) {
        return String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function render(rows) {
        const target = document.getElementById("assessorReportRows");
        target.innerHTML = rows.length ? `<table class="sq-assessor-table"><thead><tr><th>School / Facility</th><th>Assessment</th><th>Status</th><th>Checklist</th><th>Score</th><th>Period</th></tr></thead><tbody>${rows.map(row => `<tr>
            <td><strong>${esc(row.fac_name)}</strong><small>${esc(row.fac_code || "-")} | ${esc(row.district || "-")}</small></td>
            <td>${esc(row.assessment_name || "Not started")}<small>${esc(row.framework_code || "")}</small></td>
            <td>${esc(row.status || "-")}</td>
            <td>${esc(row.saved_checkpoints || 0)} / ${esc(row.total_checkpoints || 0)}</td>
            <td>${esc(row.score_percent || 0)}%</td>
            <td>${esc(row.start_date || "-")}<small>${esc(row.end_date || "")}</small></td>
        </tr>`).join("")}</tbody></table>` : '<div class="sq-assessor-empty">No assessment report data is available.</div>';
        renderTrends(rows);
    }

    function renderTrends(rows) {
        const target = document.getElementById("assessorTrendRows");
        const groups = new Map();
        rows.filter(row => row.assessment_name).forEach(function (row) {
            const key = `${row.fac_name}|${row.fac_code}`;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(row);
        });
        const trends = Array.from(groups.values()).map(function (history) {
            history.sort((a, b) => String(a.start_date || "").localeCompare(String(b.start_date || "")));
            const first = Number(history[0].score_percent || 0);
            return `<div class="sq-assessor-summary-section"><h4>${esc(history[0].fac_name)} <small>${esc(history[0].fac_code || "")}</small></h4>
                <table class="sq-assessor-table sq-assessor-summary-table"><thead><tr><th>Round</th><th>Assessment</th><th>Score</th><th>Change from first</th><th>Status</th></tr></thead><tbody>${history.map(function (row, index) {
                    const change = Number(row.score_percent || 0) - first;
                    const changeText = index === 0 ? "Baseline" : `${change > 0 ? "+" : ""}${change.toFixed(2)}%`;
                    return `<tr><td>${index + 1}</td><td>${esc(row.assessment_name)}</td><td>${esc(row.score_percent || 0)}%</td><td>${esc(changeText)}</td><td>${esc(row.status)}</td></tr>`;
                }).join("")}</tbody></table>
                ${history.length < 2 ? '<div class="sq-assessor-empty">Complete the next assessment round to see improvement comparison.</div>' : ''}
            </div>`;
        });
        target.innerHTML = trends.join("") || '<div class="sq-assessor-empty">No completed assessment trend is available yet.</div>';
    }

    async function load() {
        try {
            const response = await SQ.api.get("/assessor/v1/assessment_report.php", { format: "json" }, { loader: true, showError: false });
            reportRows = response.data?.rows || [];
            const codeLabel = response.data?.facility_code_label || "NIN";
            const search = document.getElementById("assessorTrendSearch");
            if (search) search.placeholder = `Search name or ${codeLabel}`;
            render(reportRows);
        } catch (error) {
            if (SQ.notification) SQ.notification.error(error.message || "Unable to load assessment report.");
        }
    }

    function init() {
        document.getElementById("assessorReportRefresh")?.addEventListener("click", load);
        document.getElementById("assessorTrendSearch")?.addEventListener("input", function (event) {
            const term = String(event.target.value || "").trim().toLowerCase();
            const trendRows = !term ? reportRows : reportRows.filter(function (row) {
                return [row.fac_name, row.fac_code].some(function (value) {
                    return String(value || "").toLowerCase().includes(term);
                });
            });
            renderTrends(trendRows);
        });
        document.getElementById("assessorReportDownload")?.addEventListener("click", function () {
            window.location.assign("/api/assessor/v1/assessment_report.php");
        });
        load();
    }

    SQ.assessorReports = { init };
})(window, document);
