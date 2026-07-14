/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Outcome Entry
 * outcome.js
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
        const value = $("outcomePeriodFilter")?.value || "";
        const parts = value.split("-");
        return {
            year: num(parts[0]),
            month: num(parts[1])
        };
    }

    function fillPeriods() {
        const select = $("outcomePeriodFilter");

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
        $("outcomeMonth").innerHTML = `<option value="${period.month}">${period.month}</option>`;
        $("outcomeYear").value = period.year || new Date().getFullYear();
    }

    function renderDepartments() {
        const select = $("outcomeDepartmentFilter");
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
        const deptId = $("outcomeDepartmentFilter")?.value || "";
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
        $("outcomeFacilityContext").innerHTML = `
            <div><span>Facility</span><strong>${esc(state.facility?.fac_name || "-")}</strong></div>
            <div><span>Facility Type</span><strong>${esc(state.facility?.facility_type || "-")}</strong></div>
            <div><span>Selected Indicators</span><strong>${state.items.length}</strong></div>
            ${rule.outcome_treated_as_kpi ? `<div><span>Rule</span><strong>Outcome as KPI</strong></div>` : ""}
        `;
    }

    function renderHistory() {
        const rows = state.history.filter(function (row) {
            const period = selectedPeriod();
            const deptId = $("outcomeDepartmentFilter")?.value || "";
            return String(row.dept_id || row.department_id || "") === String(deptId)
                && num(row.entry_month) === period.month
                && num(row.entry_year) === period.year;
        });

        $("outcomeHistoryRows").innerHTML = rows.length
            ? rows.map(row => `
                <div class="sq-performance-row">
                    <strong>${esc(row.indicator_name)}</strong>
                    <span>${esc(row.entry_month)}/${esc(row.entry_year)} | N ${esc(row.numerator_value)} | D ${esc(row.denominator_value)} | Result ${esc(row.result_value)}</span>
                </div>
            `).join("")
            : "No outcome history found for selected month.";
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

        $("outcomeProgressText").textContent = `${stateCount.saved} completed, ${Math.max(0, stateCount.total - stateCount.saved)} remaining`;
        $("outcomeCurrentCounter").textContent = `${index} / ${stateCount.total}`;
        $("outcomeProgressBar").style.width = `${Math.max(0, Math.min(percent, 100))}%`;
    }

    function renderCompletion() {
        const status = completionState();
        const showComplete = status.complete && !state.editMode;
        $("outcomeCompleteMessage").hidden = !showComplete;
        $("outcomeWizard").hidden = showComplete;
    }

    function showItem() {
        fillFormPeriod();
        const item = selectedItem();
        const status = completionState();

        if (!item) {
            $("outcomeSelectedTitle").textContent = "Select department and month";
            $("outcomeSelectedMeta").textContent = "No outcome indicator available.";
            $("btnOutcomeSave").disabled = true;
            $("btnOutcomeBack").disabled = true;
            $("btnOutcomeNext").disabled = true;
            renderProgress();
            renderCompletion();
            return;
        }

        const saved = currentSaved(item);

        $("outcomeIndicatorId").value = item.indicator_id;
        $("outcomeIndicatorCode").value = item.indicator_code;
        $("outcomeIndicatorName").value = item.indicator_name;
        $("outcomeDepartmentId").value = item.department_id || 0;
        $("outcomeFormulaId").value = item.formula_id || 0;
        $("outcomeSelectedTitle").textContent = item.indicator_name || "Outcome Entry";
        $("outcomeSelectedMeta").textContent = `${item.indicator_code || "-"} | ${item.department_name || "General"} | Formula ${item.formula_id || "-"}`;
        const numeratorField = (item.fields || []).find(field => field.field_id === "N") || {};
        const denominatorField = (item.fields || []).find(field => field.field_id === "D") || {};
        $("outcomeNumeratorLabel").textContent = numeratorField.label || "Numerator";
        $("outcomeDenominatorLabel").textContent = denominatorField.label || "Denominator";
        $("outcomeNumerator").value = saved?.numerator_value ?? "";
        $("outcomeDenominator").value = saved?.denominator_value ?? "";
        $("outcomeRemarks").value = saved?.remarks ?? "";

        calculateResult();

        $("btnOutcomeSave").textContent = saved ? "Update & Next" : "Save & Next";
        $("btnOutcomeSave").disabled = false;
        $("btnOutcomeBack").disabled = state.selectedIndex <= 0;
        $("btnOutcomeNext").disabled = state.selectedIndex >= state.items.length - 1;

        renderProgress();
        renderCompletion();

        if (status.complete && state.editMode) {
            $("outcomeProgressText").textContent = "Edit mode: update saved values as required.";
        }
    }

    function calculateResult() {
        const d = num($("outcomeDenominator").value);
        const n = num($("outcomeNumerator").value);
        $("outcomeResult").value = d > 0 ? ((n / d) * 100).toFixed(2) : "";
    }

    async function loadHistory() {
        const period = selectedPeriod();
        const deptId = $("outcomeDepartmentFilter")?.value || "";
        const response = await SQ.api.get("/performance/v1/outcome_history.php", {
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

    async function loadOutcomes() {
        const deptId = $("outcomeDepartmentFilter")?.value || "";
        const response = await SQ.api.get("/performance/v1/outcome_list.php", deptId ? {
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
        await loadHistory();
        showItem();
    }

    async function saveOutcome(event) {
        event.preventDefault();

        const item = selectedItem();

        if (!item) {
            return;
        }

        const period = selectedPeriod();
        const payload = {
            indicator_id: num($("outcomeIndicatorId").value),
            indicator_code: $("outcomeIndicatorCode").value,
            indicator_name: $("outcomeIndicatorName").value,
            department_id: num($("outcomeDepartmentId").value),
            formula_id: num($("outcomeFormulaId").value),
            month: period.month,
            year: period.year,
            numerator: num($("outcomeNumerator").value),
            denominator: num($("outcomeDenominator").value),
            remarks: $("outcomeRemarks").value
        };

        const response = await SQ.api.post("/performance/v1/outcome_save.php", payload, {
            loader: false,
            showError: false
        });

        notify("success", response?.message || "Outcome saved.");
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
        $("btnOutcomeRefresh")?.addEventListener("click", loadOutcomes);
        $("outcomeDepartmentFilter")?.addEventListener("change", loadOutcomes);
        $("outcomePeriodFilter")?.addEventListener("change", loadOutcomes);
        $("outcomeNumerator")?.addEventListener("input", calculateResult);
        $("outcomeDenominator")?.addEventListener("input", calculateResult);
        $("outcomeEntryForm")?.addEventListener("submit", saveOutcome);
        $("btnOutcomeNext")?.addEventListener("click", goNext);
        $("btnOutcomeBack")?.addEventListener("click", goBack);
        $("btnOutcomeEditCompleted")?.addEventListener("click", startEditMode);
    }

    function init() {
        fillPeriods();
        bind();
        loadOutcomes().catch(error => {
            console.error(error);
            notify("error", error.message || "Unable to load outcome page.");
        });
    }

    SQ.performanceOutcome = { init };
})(window, document);
