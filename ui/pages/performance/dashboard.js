/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Dashboard
 * dashboard.js
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    const state = {
        trends: { KPI: [], OUTCOME: [], EFFECTIVE: [] },
        effectiveLabel: "KPI",
        effectiveType: "KPI",
        departmentId: "",
        departments: []
    };

    function currentPeriod() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
    }

    function setText(id, value) {
        const el = document.getElementById(id);
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

    function renderMonthStatus(rows) {
        const target = document.getElementById("perfMonthRows");
        if (!target) return;

        target.innerHTML = rows.length
            ? rows.slice(-12).map(row => `
                <div class="sq-performance-month-card">
                    <strong>${esc(shortMonth(row.period))}</strong>
                    <span>${esc(state.effectiveLabel || "Performance")} <b>${esc(row.total_entries || 0)}</b></span>
                    <em>Total ${esc(row.total_entries || 0)}</em>
                </div>
            `).join("")
            : `<div class="sq-performance-empty">No month wise performance entries available.</div>`;
    }

    function chartSvg(points, style) {
        const width = 260;
        const height = 86;
        const padX = 16;
        const padY = 12;
        const values = points.map(point => num(point.result));
        const max = Math.max(...values, 0);
        const min = Math.min(...values, 0);
        const range = Math.max(1, max - min);

        if (!points.length) {
            return `<div class="sq-performance-empty">No trend data.</div>`;
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
            <svg class="sq-performance-sparkline is-${esc(style)}" viewBox="0 0 ${width} ${height}" role="img" aria-label="Month wise result trend">
                <line x1="${padX}" y1="${height - padY}" x2="${width - padX}" y2="${height - padY}"></line>
                ${style === "area" ? `<polygon points="${areaPath}"></polygon>` : ""}
                ${style === "bar" ? bars : `<polyline points="${path}"></polyline>${circles}`}
            </svg>
        `;
    }

    function renderCharts(targetId, series) {
        const target = document.getElementById(targetId);
        if (!target) return;
        const style = document.getElementById("perfChartStyle")?.value || "line";
        const summaryTarget = document.getElementById(targetId === "perfOutcomeCharts" ? "perfOutcomeChartSummary" : "perfKpiChartSummary");

        target.innerHTML = series.length
            ? series.map(item => {
                const points = item.points || [];
                const latest = points[points.length - 1] || {};
                const meta = `${item.indicator_code || "-"} | ${item.department_name || "-"}`;

                return `
                    <div class="sq-performance-chart-card">
                        <div class="sq-performance-chart-head">
                            <strong>${esc(item.indicator_name || "Indicator")}</strong>
                            <span>${esc(meta)}</span>
                        </div>
                        ${chartSvg(points, style)}
                        <div class="sq-performance-chart-foot">
                            <span>${esc(points.length)} month${points.length === 1 ? "" : "s"}</span>
                            <b>${esc(shortMonth(latest.period))}: ${esc(fmt(latest.result))}</b>
                        </div>
                    </div>
                `;
            }).join("")
            : `<div class="sq-performance-empty">No trend data available.</div>`;

        if (summaryTarget) {
            summaryTarget.textContent = series.length
                ? series.map(item => {
                    const points = item.points || [];
                    const latest = points[points.length - 1] || {};
                    return `${item.indicator_name || "Indicator"} has ${points.length} month${points.length === 1 ? "" : "s"} of trend data. Latest ${shortMonth(latest.period)} result is ${fmt(latest.result)}.`;
                }).join(" ")
                : "No trend data available.";
        }

        if (SQ.a11y) {
            SQ.a11y.enhance(target);
        }
    }

    function filterByDepartment(series) {
        if (!state.departmentId) {
            return series;
        }

        return series.filter(item => String(item.department_id || 0) === state.departmentId);
    }

    function renderDepartmentFilter() {
        const select = document.getElementById("perfDepartmentFilter");
        if (!select) return;

        const departments = new Map(state.departments.map(item => [
            String(item.department_id || 0),
            item.department_name || (Number(item.department_id || 0) === 0 ? "Facility-level KPI" : `Department ${item.department_id}`)
        ]));

        const current = state.departmentId;
        select.innerHTML = `<option value="">All departments</option>` + [...departments.entries()]
            .sort((a, b) => a[1].localeCompare(b[1]))
            .map(([id, name]) => `<option value="${esc(id)}">${esc(name)}</option>`)
            .join("");
        state.departmentId = departments.has(current) ? current : "";
        select.value = state.departmentId;
    }

    function renderTrendSections() {
        const outcomeSeries = state.effectiveType === "OUTCOME"
            ? (state.trends.EFFECTIVE || state.trends.OUTCOME || [])
            : (state.trends.OUTCOME || []);
        setText("perfOutcomeTrendTitle", `${state.effectiveType === "OUTCOME" ? (state.effectiveLabel || "Outcome as KPI") : "Outcome"} Indicator Trends`);
        renderCharts("perfOutcomeCharts", filterByDepartment(outcomeSeries));
        renderCharts("perfKpiCharts", filterByDepartment(state.trends.KPI || []));
    }

    async function loadDashboard() {
        const response = await SQ.api.get("/performance/v1/dashboard.php", {
            all_indicators: document.getElementById("perfAllIndicators")?.checked ? 1 : 0,
            department_id: state.departmentId,
            period: document.getElementById("perfPeriodFilter")?.value || currentPeriod()
        }, { loader: false, showError: false });
        const summary = response?.data?.summary || {};
        const monthStatus = response?.data?.month_status || [];
        const trends = response?.data?.indicator_trends || {};
        const outcomeDepartmentStatus = response?.data?.outcome_department_status || {};
        state.trends = trends;
        state.departments = response?.data?.trend_departments || [];
        state.effectiveType = response?.data?.effective_indicator_type || "KPI";
        state.effectiveLabel = response?.data?.effective_indicator_label || response?.data?.effective_indicator_type || "Performance";

        setText("perfTotalMonths", summary.total_months || 0);
        setText("perfTotalOutcomeDepartments", outcomeDepartmentStatus.total_departments || 0);
        setText("perfCompletedOutcomeDepartments", outcomeDepartmentStatus.completed_departments || 0);
        setText("perfKpiIndicators", state.effectiveType === "OUTCOME" ? (summary.outcome_indicators || 0) : (summary.kpi_indicators || 0));
        setText("perfOutcomeIndicators", summary.outcome_indicators || 0);
        setText("perfKpiIndicatorLabel", state.effectiveLabel === "Outcome as KPI" ? "Outcome as KPI Indicators" : "KPI Indicators");
        setText("perfOutcomeIndicatorLabel", "Outcome Indicators");
        setText("perfLatestPeriod", `Selected month: ${shortMonth(outcomeDepartmentStatus.period || currentPeriod())}`);

        renderDepartmentFilter();
        renderMonthStatus(monthStatus);
        renderTrendSections();
    }

    function init() {
        const periodFilter = document.getElementById("perfPeriodFilter");
        if (periodFilter && !periodFilter.value) periodFilter.value = currentPeriod();
        document.getElementById("btnPerformanceRefresh")?.addEventListener("click", loadDashboard);
        document.getElementById("perfAllIndicators")?.addEventListener("change", loadDashboard);
        periodFilter?.addEventListener("change", loadDashboard);
        document.getElementById("perfDepartmentFilter")?.addEventListener("change", event => {
            state.departmentId = event.target.value;
            loadDashboard().catch(console.error);
        });
        document.getElementById("perfChartStyle")?.addEventListener("change", renderTrendSections);
        loadDashboard().catch(console.error);
    }

    SQ.performanceDashboard = { init };
})(window, document);
