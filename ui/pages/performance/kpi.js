/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance KPI Entry
 * kpi.js
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const state = {
        allItems: [],
        items: [],
        history: [],
        savedMap: {},
        selectedIndex: 0,
        facility: null,
        editMode: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    function esc(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function num(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function notify(type, message) {
        if (SQ.notification && typeof SQ.notification[type] === "function") {
            SQ.notification[type](message);
        } else if (SQ.toast) {
            SQ.toast(message, type);
        }
    }

    function selectedPeriod() {
        const value = $("kpiPeriodFilter")?.value || "";
        const parts = value.split("-");
        return {
            year: num(parts[0]),
            month: num(parts[1])
        };
    }

    function fillPeriods() {
        const select = $("kpiPeriodFilter");

        if (!select || select.options.length) {
            return;
        }

        const now = new Date();

        for (let i = 0; i < 6; i++) {
            const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const label = date.toLocaleString("en-IN", {
                month: "short",
                year: "numeric"
            });

            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${year}-${String(month).padStart(2, "0")}">${label}</option>`
            );
        }
    }

    function fillFormPeriod() {
        const period = selectedPeriod();
        $("kpiMonth").innerHTML = `<option value="${period.month}">${period.month}</option>`;
        $("kpiYear").value = period.year || new Date().getFullYear();
    }

    function renderDepartments() {
        const select = $("kpiDepartmentFilter");
        const existing = select.value;
        const departments = [...new Map(
            state.allItems.map(item => [String(item.department_id || 0), item.department_name || "General"])
        ).entries()].filter(([id]) => id !== "0");

        select.innerHTML = `<option value="">Select department</option>` + departments
            .map(([id, name]) => `<option value="${esc(id)}">${esc(name)}</option>`)
            .join("");

        if (existing && departments.some(([id]) => id === existing)) {
            select.value = existing;
        }
    }

    function savedKey(indicatorId) {
        const period = selectedPeriod();
        const deptId = $("kpiDepartmentFilter")?.value || "";
        return [deptId, indicatorId, period.month, period.year].join("|");
    }

    function buildSavedMap() {
        state.savedMap = {};

        state.history.forEach(function (row) {
            const key = [
                String(row.dept_id || row.department_id || 0),
                String(row.indicator_id || 0),
                String(row.entry_month || row.month || 0),
                String(row.entry_year || row.year || 0)
            ].join("|");

            state.savedMap[key] = row;
        });
    }

    function selectedItem() {
        return state.items[state.selectedIndex] || null;
    }

    function currentSaved(item) {
        return item ? state.savedMap[savedKey(item.indicator_id)] || null : null;
    }

    function renderFacility() {
        const rule = state.rule || {};
        $("kpiFacilityContext").innerHTML = `
            <div><span>Facility</span><strong>${esc(state.facility?.fac_name || "-")}</strong></div>
            <div><span>Facility Type</span><strong>${esc(state.facility?.facility_type || "-")}</strong></div>
            <div><span>Selected KPI</span><strong>${state.items.length}</strong></div>
            ${rule.outcome_treated_as_kpi ? `<div><span>Rule</span><strong>Outcome as KPI</strong></div>` : ""}
        `;
    }

    function renderRuleBlock() {
        const rule = state.rule || {};
        const blocked = rule.kpi_applicable === false || rule.block_kpi_entry === true;
        const message = rule.message || "KPI entry is not applicable for this facility type.";

        if ($("kpiCompleteMessage")) {
            $("kpiCompleteMessage").hidden = !blocked;
            $("kpiCompleteMessage").innerHTML = blocked
                ? `<strong>${esc(message)}</strong><br><span>Please use Outcome Indicators for this facility type.</span>`
                : `<strong>All KPI indicators are already entered for this month.</strong>`;
        }
        if ($("kpiWizard")) $("kpiWizard").hidden = blocked;
        if ($("kpiHistoryRows") && blocked) $("kpiHistoryRows").innerHTML = "KPI is not applicable for this facility type.";

        return blocked;
    }

    function renderHistory() {
        const rows = state.history.filter(function (row) {
            const period = selectedPeriod();
            const deptId = $("kpiDepartmentFilter")?.value || "";
            return String(row.dept_id || row.department_id || "") === String(deptId)
                && num(row.entry_month) === period.month
                && num(row.entry_year) === period.year;
        });

        $("kpiHistoryRows").innerHTML = rows.length
            ? rows.map(row => `
                <div class="sq-performance-row">
                    <strong>${esc(row.indicator_name)}</strong>
                    <span>${esc(row.entry_month)}/${esc(row.entry_year)} | N ${esc(row.numerator_value)} | D ${esc(row.denominator_value)} | Result ${esc(row.result_value)}</span>
                </div>
            `).join("")
            : "No KPI history found for selected month.";
    }

    function completionState() {
        const total = state.items.length;
        const saved = state.items.filter(item => currentSaved(item)).length;
        return {
            total,
            saved,
            complete: total > 0 && saved >= total
        };
    }

    function renderProgress() {
        const stateCount = completionState();
        const index = stateCount.total ? state.selectedIndex + 1 : 0;
        const percent = stateCount.total ? Math.round((stateCount.saved / stateCount.total) * 100) : 0;

        $("kpiProgressText").textContent = `${stateCount.saved} completed, ${Math.max(0, stateCount.total - stateCount.saved)} remaining`;
        $("kpiCurrentCounter").textContent = `${index} / ${stateCount.total}`;
        $("kpiProgressBar").style.width = `${Math.max(0, Math.min(percent, 100))}%`;
    }

    function renderCompletion() {
        const status = completionState();
        const showComplete = status.complete && !state.editMode;
        $("kpiCompleteMessage").hidden = !showComplete;
        $("kpiWizard").hidden = showComplete;
    }

    function showItem() {
        fillFormPeriod();
        const item = selectedItem();
        const status = completionState();

        if (!item) {
            $("kpiSelectedTitle").textContent = "Select department and month";
            $("kpiSelectedMeta").textContent = "No KPI indicator available.";
            $("btnKpiSave").disabled = true;
            $("btnKpiBack").disabled = true;
            $("btnKpiNext").disabled = true;
            renderProgress();
            renderCompletion();
            return;
        }

        const saved = currentSaved(item);

        $("kpiIndicatorId").value = item.indicator_id;
        $("kpiIndicatorCode").value = item.indicator_code;
        $("kpiIndicatorName").value = item.indicator_name;
        $("kpiDepartmentId").value = item.department_id || 0;
        $("kpiFormulaId").value = item.formula_id || 0;
        $("kpiSelectedTitle").textContent = item.indicator_name || "KPI Entry";
        $("kpiSelectedMeta").textContent = `${item.indicator_code || "-"} | ${item.department_name || "General"} | Formula ${item.formula_id || "-"}`;

        const numeratorField = (item.fields || []).find(field => field.field_id === "N") || {};
        const denominatorField = (item.fields || []).find(field => field.field_id === "D") || {};
        $("kpiNumeratorLabel").textContent = numeratorField.label || "Numerator";
        $("kpiDenominatorLabel").textContent = denominatorField.label || "Denominator";
        $("kpiNumerator").value = saved?.numerator_value ?? "";
        $("kpiDenominator").value = saved?.denominator_value ?? "";
        $("kpiRemarks").value = saved?.remarks ?? "";

        calculateResult();

        $("btnKpiSave").textContent = saved ? "Update & Next" : "Save & Next";
        $("btnKpiSave").disabled = false;
        $("btnKpiBack").disabled = state.selectedIndex <= 0;
        $("btnKpiNext").disabled = state.selectedIndex >= state.items.length - 1;

        renderProgress();
        renderCompletion();

        if (status.complete && state.editMode) {
            $("kpiProgressText").textContent = "Edit mode: update saved values as required.";
        }
    }

    function calculateResult() {
        const d = num($("kpiDenominator").value);
        const n = num($("kpiNumerator").value);
        $("kpiResult").value = d > 0 ? ((n / d) * 100).toFixed(2) : "";
    }

    async function loadHistory() {
        const period = selectedPeriod();
        const deptId = $("kpiDepartmentFilter")?.value || "";
        const response = await SQ.api.get("/performance/v1/kpi_history.php", {
            month: period.month,
            year: period.year,
            department_id: deptId
        }, {
            loader: false,
            showError: false
        });

        state.history = response?.data?.items || [];
        buildSavedMap();
        renderHistory();
    }

    async function loadKpis() {
        const deptId = $("kpiDepartmentFilter")?.value || "";
        const response = await SQ.api.get("/performance/v1/kpi_list.php", deptId ? {
            department_id: deptId
        } : {}, {
            loader: false,
            showError: false
        });

        state.facility = response?.data?.facility || {};
        state.rule = response?.data?.rule || {};
        state.allItems = response?.data?.items || [];

        if (!deptId) {
            renderDepartments();
        }

        state.items = deptId
            ? state.allItems.filter(item => String(item.department_id || "") === String(deptId))
            : [];

        state.selectedIndex = 0;
        state.editMode = false;

        renderFacility();
        if (renderRuleBlock()) {
            return;
        }
        await loadHistory();
        showItem();
    }

    async function saveKpi(event) {
        event.preventDefault();

        const item = selectedItem();

        if (!item) {
            return;
        }

        const period = selectedPeriod();
        const payload = {
            indicator_id: num($("kpiIndicatorId").value),
            indicator_code: $("kpiIndicatorCode").value,
            indicator_name: $("kpiIndicatorName").value,
            department_id: num($("kpiDepartmentId").value),
            formula_id: num($("kpiFormulaId").value),
            month: period.month,
            year: period.year,
            numerator: num($("kpiNumerator").value),
            denominator: num($("kpiDenominator").value),
            remarks: $("kpiRemarks").value
        };

        const response = await SQ.api.post("/performance/v1/kpi_save.php", payload, {
            loader: false,
            showError: false
        });

        notify("success", response?.message || "KPI saved.");
        await loadHistory();

        if (state.selectedIndex < state.items.length - 1) {
            state.selectedIndex += 1;
        } else {
            state.editMode = false;
        }

        showItem();
    }

    function goNext() {
        if (state.selectedIndex < state.items.length - 1) {
            state.selectedIndex += 1;
            showItem();
        }
    }

    function goBack() {
        if (state.selectedIndex > 0) {
            state.selectedIndex -= 1;
            showItem();
        }
    }

    function startEditMode() {
        state.editMode = true;
        state.selectedIndex = 0;
        showItem();
    }

    function bind() {
        $("btnKpiRefresh")?.addEventListener("click", loadKpis);
        $("kpiDepartmentFilter")?.addEventListener("change", loadKpis);
        $("kpiPeriodFilter")?.addEventListener("change", loadKpis);
        $("kpiNumerator")?.addEventListener("input", calculateResult);
        $("kpiDenominator")?.addEventListener("input", calculateResult);
        $("kpiEntryForm")?.addEventListener("submit", saveKpi);
        $("btnKpiNext")?.addEventListener("click", goNext);
        $("btnKpiBack")?.addEventListener("click", goBack);
        $("btnKpiEditCompleted")?.addEventListener("click", startEditMode);
    }

    function init() {
        fillPeriods();
        bind();
        loadKpis().catch(error => {
            console.error(error);
            notify("error", error.message || "Unable to load KPI page.");
        });
    }

    SQ.performanceKpi = { init };
})(window, document);
