/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Trend
 * trend.js
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    const state = {
        facility: {},
        summary: {},
        monthStatus: [],
        trends: { KPI: [], OUTCOME: [], EFFECTIVE: [] },
        effectiveLabel: "Performance",
        effectiveType: "KPI"
    };

    function $(id) {
        return document.getElementById(id);
    }

    function setText(id, value) {
        const el = $(id);
        if (el) el.textContent = value;
    }

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function num(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function fmt(value) {
        const parsed = num(value);
        return Number.isInteger(parsed) ? String(parsed) : parsed.toFixed(2);
    }

    function shortMonth(period) {
        const parts = String(period || "").split("-");
        if (parts.length !== 2) return period || "-";
        const month = Number(parts[1]);
        const names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        return `${names[month - 1] || parts[1]} ${String(parts[0]).slice(-2)}`;
    }

    function indicatorEntryUrl(type, departmentId, period) {
        const params = new URLSearchParams({
            indicator_type: type || "OUTCOME",
            department_id: String(departmentId || ""),
            period: period || ""
        });
        return `/ui/pages/performance/indicator.html?${params.toString()}`;
    }

    function isAssessorUser() {
        const user = SQ.auth && typeof SQ.auth.getUser === "function" ? SQ.auth.getUser() : null;
        const roleName = String(user?.role_name || user?.user_type || "").toLowerCase();
        return Number(user?.role_id || 0) === 10 || roleName.includes("assessor");
    }

    function isReadonly() {
        const params = new URLSearchParams(window.location.search);
        return params.get("readonly") === "1" || params.get("mode") === "view" || isAssessorUser();
    }

    function handleEditLink(event) {
        const link = event.target.closest('a[href*="/ui/pages/performance/indicator.html"]');
        if (!link || !SQ.router || typeof SQ.router.navigate !== "function") {
            return;
        }

        event.preventDefault();

        if (isReadonly()) {
            return;
        }

        const url = new URL(link.href, window.location.origin);
        const params = {};
        url.searchParams.forEach((value, key) => {
            params[key] = value;
        });
        SQ.router.navigate("performance/indicator", params);
    }

    function renderMonthStatus() {
        const target = $("trendMonthRows");
        if (!target) return;

        target.innerHTML = state.monthStatus.length
            ? state.monthStatus.slice(-12).map(row => `
                <div class="sq-trend-month-card">
                    <strong>${esc(shortMonth(row.period))}</strong>
                    <span>${esc(state.effectiveLabel || "Performance")} <b>${esc(row.total_entries || 0)}</b></span>
                    ${isReadonly() ? `<span>View only</span>` : `<a href="${esc(indicatorEntryUrl(state.effectiveType || "OUTCOME", "", row.period))}">Edit month</a>`}
                </div>
            `).join("")
            : `<div class="sq-trend-empty">No month-wise performance entries available.</div>`;
    }

    function chartSvg(points, style) {
        const width = 300;
        const height = 90;
        const padX = 16;
        const padY = 12;
        const values = points.map(point => num(point.result));
        const max = Math.max(...values, 0);
        const min = Math.min(...values, 0);
        const range = Math.max(1, max - min);

        if (!points.length) {
            return `<div class="sq-trend-empty">No trend data.</div>`;
        }

        const coords = points.map((point, index) => {
            const x = points.length === 1
                ? width / 2
                : padX + (index * (width - (padX * 2)) / (points.length - 1));
            const y = height - padY - ((num(point.result) - min) * (height - (padY * 2)) / range);
            return { x, y, point };
        });

        const path = coords.map(coord => `${coord.x.toFixed(1)},${coord.y.toFixed(1)}`).join(" ");
        const areaPath = `${padX},${height - padY} ${path} ${width - padX},${height - padY}`;
        const bars = coords.map((coord, index) => {
            const barWidth = Math.max(8, Math.min(28, (width - (padX * 2)) / Math.max(1, coords.length) * .55));
            const x = coord.x - (barWidth / 2);
            const y = coord.y;
            const barHeight = Math.max(2, height - padY - y);
            return `
                <rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${barWidth.toFixed(1)}" height="${barHeight.toFixed(1)}" rx="3">
                    <title>${esc(shortMonth(coords[index].point.period))}: ${esc(fmt(coords[index].point.result))}</title>
                </rect>
            `;
        }).join("");
        const circles = coords.map(coord => `
            <circle cx="${coord.x.toFixed(1)}" cy="${coord.y.toFixed(1)}" r="3">
                <title>${esc(shortMonth(coord.point.period))}: ${esc(fmt(coord.point.result))}</title>
            </circle>
        `).join("");

        return `
            <svg class="sq-trend-sparkline is-${esc(style)}" viewBox="0 0 ${width} ${height}" role="img" aria-label="Month-wise result trend">
                <line x1="${padX}" y1="${height - padY}" x2="${width - padX}" y2="${height - padY}"></line>
                ${style === "area" ? `<polygon points="${areaPath}"></polygon>` : ""}
                ${style === "bar" ? bars : `<polyline points="${path}"></polyline>${circles}`}
            </svg>
        `;
    }

    function renderCharts(targetId, series) {
        const target = $(targetId);
        if (!target) return;
        const style = $("trendChartStyle")?.value || "line";
        const summaryId = targetId === "trendOutcomeCharts" ? "trendOutcomeSummary" : "trendKpiSummary";
        let summary = $(summaryId);
        if (!summary) {
            summary = document.createElement("div");
            summary.id = summaryId;
            summary.className = "sq-sr-only";
            target.parentNode.insertBefore(summary, target.nextSibling);
            target.setAttribute("aria-describedby", summaryId);
        }

        target.innerHTML = series.length
            ? series.map(item => {
                const points = item.points || [];
                const latest = points[points.length - 1] || {};
                const editUrl = indicatorEntryUrl(item.indicator_type, item.department_id, latest.period);
                const readonly = isReadonly();
                return `
                    <div class="sq-trend-chart-card">
                        <div class="sq-trend-chart-head">
                            <strong>${esc(item.indicator_name || "Indicator")}</strong>
                            <span>${esc(item.indicator_code || "-")} | ${esc(item.department_name || "-")}</span>
                        </div>
                        ${chartSvg(points, style)}
                        <div class="sq-trend-points">
                            ${points.map(point => `
                                ${readonly ? `<span>${esc(shortMonth(point.period))}: ${esc(fmt(point.result))}</span>` : `<a href="${esc(indicatorEntryUrl(item.indicator_type, item.department_id, point.period))}">
                                    ${esc(shortMonth(point.period))}: ${esc(fmt(point.result))}
                                </a>`}
                            `).join("")}
                        </div>
                        <div class="sq-trend-chart-foot">
                            <span>${esc(points.length)} month${points.length === 1 ? "" : "s"}</span>
                            ${readonly ? `<span>View only</span>` : `<a href="${esc(editUrl)}">Edit latest</a>`}
                        </div>
                    </div>
                `;
            }).join("")
            : `<div class="sq-trend-empty">No trend data available.</div>`;

        summary.textContent = series.length
            ? series.map(item => {
                const points = item.points || [];
                const latest = points[points.length - 1] || {};
                return `${item.indicator_name || "Indicator"} has ${points.length} month${points.length === 1 ? "" : "s"} of data. Latest ${shortMonth(latest.period)} result is ${fmt(latest.result)}.`;
            }).join(" ")
            : "No trend data available.";

        if (SQ.a11y) {
            SQ.a11y.enhance(target);
        }
    }

    function renderAll() {
        const readonly = isReadonly();
        setText("trendFacility", state.facility?.fac_name || "-");
        setText("trendTotalMonths", state.summary?.total_months || 0);
        setText("trendTotalEntries", state.summary?.total_entries || 0);
        setText("trendLatestPeriod", state.summary?.latest_period ? shortMonth(state.summary.latest_period) : "-");
        if ($("btnDownloadOutcomeTrend")) $("btnDownloadOutcomeTrend").hidden = readonly;
        if ($("btnDownloadKpiTrend")) $("btnDownloadKpiTrend").hidden = readonly;
        renderMonthStatus();
        setText("trendEffectiveTitle", `${state.effectiveLabel || "Performance"} Trends`);
        renderCharts("trendOutcomeCharts", state.trends.EFFECTIVE || state.trends.OUTCOME || []);
        renderCharts("trendKpiCharts", state.trends.KPI || []);
    }

    function excelText(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function downloadExcel(type) {
        const requestedType = type === "EFFECTIVE" ? (state.effectiveType || "OUTCOME") : type;
        const series = type === "EFFECTIVE" ? (state.trends.EFFECTIVE || []) : (state.trends[type] || []);
        if (!series.length) {
            if (SQ.toast) SQ.toast(`No ${type} trend data to download.`, "warning");
            return;
        }

        const periods = [...new Set(series.flatMap(item => (item.points || []).map(point => point.period)))].sort();
        const colCount = Math.max(4, 3 + periods.length);
        const reportTime = new Date().toLocaleString();
        const title = `${type === "EFFECTIVE" ? state.effectiveLabel : requestedType} Performance Trend`;
        const dataRows = series.map(item => {
            const latest = (item.points || [])[item.points.length - 1] || {};
            const byPeriod = new Map((item.points || []).map(point => [point.period, point]));
            return `
                <tr>
                    <td class="indicator">${excelText(item.indicator_name || "")}</td>
                    <td class="num">${excelText(fmt(latest.numerator ?? 0))}</td>
                    <td class="num">${excelText(fmt(latest.denominator ?? 0))}</td>
                    ${periods.map(period => `<td class="num">${byPeriod.has(period) ? excelText(fmt(byPeriod.get(period).result)) : ""}</td>`).join("")}
                </tr>
            `;
        }).join("");

        const html = `
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <style>
                    table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:11pt}
                    td,th{border:1px solid #000;padding:4px 6px;vertical-align:middle}
                    .label{background:#b8c9e6;font-weight:bold}
                    .value{background:#f4e3d1}
                    .report{background:#d6d900;font-weight:bold;text-align:center;font-size:12pt}
                    .head{background:#156799;color:#000;font-weight:bold;text-align:center}
                    .month{background:#86a86f;font-weight:bold;text-align:center}
                    .indicator{min-width:360px;font-weight:bold}
                    .num{text-align:right}
                </style>
            </head>
            <body>
                <table>
                    <tr>
                        <td class="label">Facility Name</td>
                        <td class="value">${excelText(state.facility?.fac_name || "-")}</td>
                        <td class="label">Report Time</td>
                        <td class="value" colspan="${Math.max(1, colCount - 3)}">${excelText(reportTime)}</td>
                    </tr>
                    <tr>
                        <td class="label">Report Type</td>
                        <td class="report" colspan="${colCount - 1}">${excelText(title)}</td>
                    </tr>
                    <tr>
                        <td class="head">Indicator's Name</td>
                        <td class="head">Numerator</td>
                        <td class="head">Denominator</td>
                        <td class="head" colspan="${Math.max(1, periods.length)}">Month</td>
                    </tr>
                    <tr>
                        <td class="month"></td>
                        <td class="month"></td>
                        <td class="month"></td>
                        ${periods.map(period => `<td class="month">${excelText(shortMonth(period))}</td>`).join("")}
                    </tr>
                    ${dataRows}
                </table>
            </body>
            </html>
        `;

        const blob = new Blob(["\ufeff", html], { type: "application/vnd.ms-excel;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `${String(type === "EFFECTIVE" ? state.effectiveLabel : requestedType).toLowerCase().replace(/[^a-z0-9]+/g, "_")}_performance_trend_${new Date().toISOString().slice(0, 10)}.xls`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    async function loadTrend() {
        const response = await SQ.api.get("/performance/v1/trend.php", {
            all_indicators: $("trendAllIndicators")?.checked ? 1 : 0
        }, { loader: false, showError: false });

        state.facility = response?.data?.facility || {};
        state.summary = response?.data?.summary || {};
        state.monthStatus = response?.data?.month_status || [];
        state.trends = response?.data?.indicator_trends || { KPI: [], OUTCOME: [], EFFECTIVE: [] };
        state.effectiveType = response?.data?.effective_indicator_type || "KPI";
        state.effectiveLabel = response?.data?.effective_indicator_label || state.effectiveType || "Performance";
        if ($("btnDownloadOutcomeTrend")) $("btnDownloadOutcomeTrend").textContent = `${state.effectiveLabel} Excel`;
        renderAll();
    }

    function init() {
        document.removeEventListener("click", handleEditLink);
        document.addEventListener("click", handleEditLink);
        $("btnTrendRefresh")?.addEventListener("click", loadTrend);
        $("trendAllIndicators")?.addEventListener("change", loadTrend);
        $("trendChartStyle")?.addEventListener("change", renderAll);
        $("btnDownloadOutcomeTrend")?.addEventListener("click", () => downloadExcel("EFFECTIVE"));
        $("btnDownloadKpiTrend")?.addEventListener("click", () => downloadExcel("KPI"));
        loadTrend().catch(console.error);
    }

    SQ.performanceTrend = { init };
})(window, document);
