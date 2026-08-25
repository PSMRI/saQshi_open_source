(function (window, document) {
    "use strict";
    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    let reportRows = [];

    function domainLabel(key, fallback) {
        return SQ.deployment?.label ? SQ.deployment.label(key, fallback) : fallback;
    }

    function esc(value) {
        return String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function statusBadge(value) {
        const label = String(value || "Not started");
        const status = label.toUpperCase();
        let tone = "not-started";

        if (status.includes("COMPLETE")) tone = "completed";
        else if (status.includes("ACTIVE") || status.includes("PROGRESS")) tone = "progress";
        else if (status.includes("CLOSED")) tone = "closed";
        else if (status.includes("CANCEL") || status.includes("EXPIRE")) tone = "attention";

        return `<span class="sq-assessor-status sq-assessor-status--${tone}">${esc(label)}</span>`;
    }

    function render(rows) {
        const target = document.getElementById("assessorReportRows");
        const facilityLabel = domainLabel("facility", "Facility");
        const departmentLabel = domainLabel("department", "Department");
        target.innerHTML = rows.length ? `<table class="sq-assessor-table"><thead><tr><th>${esc(facilityLabel)}</th><th>Assessment</th><th>Status</th><th>Checklist</th><th>Score</th><th>Period</th></tr></thead><tbody>${rows.map(row => `<tr>
            <td><strong>${esc(row.fac_name)}</strong><small>${esc(row.fac_code || "-")} | ${esc(row.district || "-")}</small></td>
            <td>${esc(row.assessment_name || "Not started")}<small>${esc(row.framework_code || "")}</small>${row.assessor_name ? `<small>Assessor: ${esc(row.assessor_name)}${row.assessor_code ? ` (${esc(row.assessor_code)})` : ""}</small>` : ""}${row.classes ? `<small>${esc(departmentLabel)}: ${esc(row.classes)}</small>` : ""}</td>
            <td>${statusBadge(row.status)}</td>
            <td>${esc(row.saved_checkpoints || 0)} / ${esc(row.total_checkpoints || 0)}</td>
            <td>${esc(row.score_percent || 0)}%</td>
            <td>${esc(row.start_date || "-")}<small>Planned end ${esc(row.end_date || "-")}</small>${row.completed_on ? `<small>Completed ${esc(row.completed_on)}</small>` : ""}${row.cancelled_on ? `<small>Cancelled ${esc(row.cancelled_on)}</small>` : ""}</td>
        </tr>`).join("")}</tbody></table>` : '<div class="sq-assessor-empty">No assessment report data is available.</div>';
        renderTrends(rows);
    }

    function renderTrends(rows) {
        const target = document.getElementById("assessorTrendRows");
        const overallTarget = document.getElementById("assessorOverallTrendRows");
        const classLabel = domainLabel("departments", "Departments");
        const overallGroups = new Map();
        rows.filter(row => row.assessment_name).forEach(function (row) {
            const key = `${row.fac_name}|${row.fac_code}|${row.round_id || row.start_date}`;
            const weight = Number(row.total_checkpoints || 0);
            if (!overallGroups.has(key)) overallGroups.set(key, { fac_name: row.fac_name, fac_code: row.fac_code, start_date: row.start_date, round_no: Number(row.round_no || 0), round_status: row.round_status || "OPEN", weighted: 0, total: 0, classes: 0, expected: Number(row.available_class_count || 0), completed_on: row.round_completed_on || "" });
            const group = overallGroups.get(key);
            group.expected = Math.max(group.expected, Number(row.available_class_count || 0));
            if (String(row.status || "").toUpperCase().includes("COMPLETE")) { group.weighted += Number(row.score_percent || 0) * weight; group.total += weight; group.classes += 1; if (String(row.completed_on || "") > String(group.completed_on || "")) group.completed_on = row.completed_on; }
        });
        const overallRows = Array.from(overallGroups.values()).map(function (row) {
            row.score = row.total ? row.weighted / row.total : 0;
            return row;
        });
        const byFacility = new Map();
        overallRows.forEach(function (row) { const key = `${row.fac_name}|${row.fac_code}`; if (!byFacility.has(key)) byFacility.set(key, []); byFacility.get(key).push(row); });
        if (overallTarget) overallTarget.innerHTML = Array.from(byFacility.values()).map(function (history) {
            history.sort((a, b) => String(a.start_date).localeCompare(String(b.start_date)));
            const baseline = history[0].score;
            return `<div class="sq-assessor-summary-section"><h4>${esc(history[0].fac_name)} <small>${esc(history[0].fac_code || "")}</small></h4><table class="sq-assessor-table sq-assessor-summary-table"><thead><tr><th>Round</th><th>Date</th><th>Completed ${esc(classLabel)}</th><th>Overall Score</th><th>Status</th><th>Change from Baseline</th></tr></thead><tbody>${history.map(function (row, index) { const change = row.score - baseline; const status = String(row.round_status).toUpperCase() === "COMPLETED" ? `Completed ${row.completed_on || ""}` : `In Progress · ${Math.max((row.expected || 0) - row.classes, 0)} pending`; return `<tr><td>Round ${row.round_no || index + 1}</td><td>${esc(row.start_date)}</td><td>${row.classes} of ${row.expected || "-"}</td><td>${row.total ? row.score.toFixed(2) + "%" : "Pending"}</td><td>${esc(status)}</td><td>${index === 0 ? "Baseline" : `${change > 0 ? "+" : ""}${change.toFixed(2)}%`}</td></tr>`; }).join("")}</tbody></table></div>`;
        }).join("") || '<div class="sq-assessor-empty">Overall comparison appears after all relevant Classes/Departments are completed.</div>';
        const groups = new Map();
        rows.filter(row => row.assessment_name).forEach(function (row) {
            // A baseline belongs to the same facility and department.
            // A first assessment of another department must never be compared with it.
            const key = `${row.fac_name}|${row.fac_code}|${row.classes || "unassigned"}`;
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
                    return `<tr><td>${index + 1}</td><td>${esc(row.assessment_name)}</td><td>${esc(row.score_percent || 0)}%</td><td>${esc(changeText)}</td><td>${statusBadge(row.status)}</td></tr>`;
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
