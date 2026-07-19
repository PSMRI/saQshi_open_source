/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Indicator Entry
 * indicator.js
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
        rule: null,
        effectiveIndicatorType: "",
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

    function isNotApplicable(value) {
        return String(value || "").trim().toUpperCase().replace(/\./g, "") === "N/A"
            || String(value || "").trim().toUpperCase().replace(/\./g, "") === "NA";
    }

    function notify(type, message) {
        if (SQ.notification && typeof SQ.notification[type] === "function") {
            SQ.notification[type](message);
        } else if (SQ.toast) {
            SQ.toast(message, type);
        }
    }

    function indicatorType() {
        return $("indicatorTypeFilter")?.value || "OUTCOME";
    }

    function effectiveIndicatorType() {
        return state.effectiveIndicatorType || indicatorType();
    }

    function selectedPeriod() {
        const parts = ($("indicatorPeriodFilter")?.value || "").split("-");
        return { year: num(parts[0]), month: num(parts[1]) };
    }

    function applyInitialQuery() {
        const params = new URLSearchParams(window.location.search);
        const type = (params.get("indicator_type") || params.get("type") || "").toUpperCase();
        const deptId = params.get("department_id") || params.get("dept_id") || "";
        const period = params.get("period") || params.get("month") || "";

        if ((type === "KPI" || type === "OUTCOME") && $("indicatorTypeFilter")) {
            $("indicatorTypeFilter").value = type;
        }

        if (/^\d{4}-\d{2}$/.test(period) && $("indicatorPeriodFilter")) {
            $("indicatorPeriodFilter").value = period;
        }

        if (deptId && $("indicatorDepartmentFilter")) {
            $("indicatorDepartmentFilter").innerHTML = `<option value="${esc(deptId)}">Loading selected department...</option>`;
            $("indicatorDepartmentFilter").value = deptId;
        }
    }

    function fillPeriods() {
        const input = $("indicatorPeriodFilter");
        if (!input || input.value) return;

        const now = new Date();
        const min = new Date(now.getFullYear(), now.getMonth() - 5, 1);
        input.max = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
        input.min = `${min.getFullYear()}-${String(min.getMonth() + 1).padStart(2, "0")}`;
        input.value = input.max;
    }

    function fillFormPeriod() {
        const period = selectedPeriod();
        $("indicatorMonth").value = period.month;
        $("indicatorYear").value = period.year || new Date().getFullYear();
    }

    function renderDepartments() {
        const select = $("indicatorDepartmentFilter");
        const existing = select.value;
        const departments = [...new Map(
            state.allItems.map(item => [String(item.department_id || 0), item.department_name || ("Department " + item.department_id)])
        ).entries()].filter(([id]) => id !== "0");

        select.innerHTML = `<option value="">Select activated department</option>` + departments
            .map(([id, name]) => `<option value="${esc(id)}">${esc(name)}</option>`)
            .join("");

        if (existing && departments.some(([id]) => id === existing)) {
            select.value = existing;
        } else if (!existing && departments.length === 1) {
            select.value = departments[0][0];
        }
    }

    function savedKey(indicatorId) {
        const period = selectedPeriod();
        const deptId = $("indicatorDepartmentFilter")?.value || "";
        return [deptId, indicatorId, period.month, period.year, effectiveIndicatorType()].join("|");
    }

    function buildSavedMap() {
        state.savedMap = {};
        state.history.forEach(function (row) {
            const key = [
                String(row.dept_id || row.department_id || 0),
                String(row.indicator_id || 0),
                String(row.entry_month || row.month || 0),
                String(row.entry_year || row.year || 0),
                String(row.indicator_type || indicatorType()).toUpperCase()
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
        const assessment = state.activeAssessment;
        const assessmentText = assessment?.assessment_name
            || (state.activeDepartmentIds?.length ? "Current assessment" : "No active assessment");

        $("indicatorFacilityContext").innerHTML = `
            <div><span>Facility</span><strong>${esc(state.facility?.fac_name || "-")}</strong></div>
            <div><span>Facility Type</span><strong>${esc(state.facility?.facility_type || "-")}</strong></div>
            <div><span>Assessment</span><strong>${esc(assessmentText)}</strong></div>
            ${state.rule?.outcome_treated_as_kpi ? `<div><span>Rule</span><strong>Outcome as KPI</strong></div>` : ""}
        `;
    }

    function renderHistory() {
        const period = selectedPeriod();
        const deptId = $("indicatorDepartmentFilter")?.value || "";
        const rows = state.history.filter(row =>
            String(row.dept_id || row.department_id || "") === String(deptId)
            && num(row.entry_month) === period.month
            && num(row.entry_year) === period.year
            && String(row.indicator_type || "").toUpperCase() === effectiveIndicatorType()
        );

        $("indicatorHistoryRows").innerHTML = rows.length
            ? rows.map(row => `
                <div class="sq-performance-row">
                    <strong>${esc(row.indicator_name)}</strong>
                    <span>${esc(row.entry_month)}/${esc(row.entry_year)} | N ${esc(row.numerator_value)} | D ${esc(row.denominator_value)} | Result ${esc(row.result_value)}</span>
                </div>
            `).join("")
            : "No indicator history found for selected month.";
    }

    function completionState() {
        const total = state.items.length;
        const saved = state.items.filter(item => currentSaved(item)).length;
        return { total, saved, complete: total > 0 && saved >= total };
    }

    function firstPendingIndex(startAt = 0) {
        if (!state.items.length) {
            return 0;
        }

        const start = Math.max(0, Math.min(startAt, state.items.length - 1));

        for (let index = start; index < state.items.length; index += 1) {
            if (!currentSaved(state.items[index])) {
                return index;
            }
        }

        for (let index = 0; index < start; index += 1) {
            if (!currentSaved(state.items[index])) {
                return index;
            }
        }

        return 0;
    }

    function renderProgress() {
        const status = completionState();
        const index = status.total ? state.selectedIndex + 1 : 0;
        const percent = status.total ? Math.round((status.saved / status.total) * 100) : 0;
        $("indicatorProgressText").textContent = `${status.saved} completed, ${Math.max(0, status.total - status.saved)} remaining`;
        $("indicatorCurrentCounter").textContent = `${index} / ${status.total}`;
        $("indicatorProgressBar").style.width = `${Math.max(0, Math.min(percent, 100))}%`;
    }

    function renderCompletion() {
        const showComplete = completionState().complete && !state.editMode;
        $("indicatorCompleteMessage").hidden = !showComplete;
        $("indicatorWizard").hidden = showComplete;
    }

    function calculateResult() {
        const item = selectedItem();
        const denominatorField = (item?.fields || []).find(field => field.field_id === "D") || {};
        const denominatorNA = isNotApplicable(denominatorField.label);
        const d = num($("indicatorDenominator").value);
        const n = num($("indicatorNumerator").value);
        $("indicatorResult").value = denominatorNA ? n.toFixed(2) : (d > 0 ? ((n / d) * 100).toFixed(2) : "0.00");
    }

    function showItem() {
        fillFormPeriod();
        const item = selectedItem();
        const status = completionState();

        if (!item) {
            $("indicatorSelectedTitle").textContent = "Select type, department and month";
            $("indicatorSelectedMeta").textContent = !state.activeAssessment
                ? "No active assessment found for this facility."
                : "No activated department indicator available for the selected type.";
            $("indicatorDetailName").textContent = "-";
            $("indicatorDetailNumerator").textContent = "-";
            $("indicatorDetailDenominator").textContent = "-";
            $("indicatorDenominator").readOnly = false;
            $("indicatorDenominator").required = true;
            $("indicatorDenominator").classList.remove("is-readonly");
            $("indicatorDenominator").placeholder = "";
            $("btnIndicatorSave").disabled = true;
            $("btnIndicatorBack").disabled = true;
            $("btnIndicatorNext").disabled = true;
            renderProgress();
            renderCompletion();
            return;
        }

        const saved = currentSaved(item);
        const numeratorField = (item.fields || []).find(field => field.field_id === "N") || {};
        const denominatorField = (item.fields || []).find(field => field.field_id === "D") || {};
        const numeratorLabel = numeratorField.label || "Numerator";
        const denominatorLabel = denominatorField.label || "Denominator";
        const denominatorNA = isNotApplicable(denominatorLabel);

        $("indicatorId").value = item.indicator_id;
        $("indicatorCode").value = item.indicator_code;
        $("indicatorName").value = item.indicator_name;
        $("indicatorDepartmentId").value = item.department_id || 0;
        $("indicatorFormulaId").value = item.formula_id || 0;
        $("indicatorSelectedTitle").textContent = item.indicator_name || "Indicator Entry";
        $("indicatorSelectedMeta").textContent = `${item.indicator_code || "-"} | ${item.department_name || ("Department " + item.department_id)} | Formula ${item.formula_id || "-"}`;
        $("indicatorDetailName").textContent = item.indicator_name || "-";
        $("indicatorDetailNumerator").textContent = numeratorLabel;
        $("indicatorDetailDenominator").textContent = denominatorLabel;
        $("indicatorNumeratorLabel").textContent = "Numerator";
        $("indicatorDenominatorLabel").textContent = denominatorNA ? "Denominator (N/A)" : "Denominator";
        $("indicatorNumerator").value = saved?.numerator_value ?? "";
        $("indicatorDenominator").value = denominatorNA ? "" : (saved?.denominator_value ?? "");
        $("indicatorDenominator").readOnly = denominatorNA;
        $("indicatorDenominator").required = !denominatorNA;
        $("indicatorDenominator").classList.toggle("is-readonly", denominatorNA);
        $("indicatorDenominator").placeholder = denominatorNA ? "N/A" : "";
        $("indicatorRemarks").value = saved?.remarks ?? "";

        calculateResult();

        $("btnIndicatorSave").textContent = saved ? "Update & Next" : "Save & Next";
        $("btnIndicatorSave").disabled = false;
        $("btnIndicatorBack").disabled = state.selectedIndex <= 0;
        $("btnIndicatorNext").disabled = state.selectedIndex >= state.items.length - 1;

        renderProgress();
        renderCompletion();

        if (status.complete && state.editMode) {
            $("indicatorProgressText").textContent = "Edit mode: update saved values as required.";
        }
    }

    async function loadHistory() {
        const period = selectedPeriod();
        const deptId = $("indicatorDepartmentFilter")?.value || "";
        const response = await SQ.api.get("/performance/v1/indicator_history.php", {
            indicator_type: effectiveIndicatorType(),
            month: period.month,
            year: period.year,
            department_id: deptId
        }, { loader: false, showError: false });

        state.history = response?.data?.items || [];
        buildSavedMap();
        renderHistory();
    }

    async function loadIndicators() {
        let deptId = $("indicatorDepartmentFilter")?.value || "";
        const response = await SQ.api.get("/performance/v1/indicator_list.php", {
            indicator_type: indicatorType(),
            department_id: deptId
        }, { loader: false, showError: false });

        state.facility = response?.data?.facility || {};
        state.rule = response?.data?.rule || {};
        state.effectiveIndicatorType = response?.data?.effective_indicator_type || indicatorType();
        if ($("indicatorTypeFilter") && $("indicatorTypeFilter").value !== state.effectiveIndicatorType) {
            $("indicatorTypeFilter").value = state.effectiveIndicatorType;
        }
        state.activeAssessment = response?.data?.active_assessment || null;
        state.activeDepartmentIds = response?.data?.active_department_ids || [];
        state.allItems = response?.data?.items || [];

        if (!deptId) {
            renderDepartments();
            deptId = $("indicatorDepartmentFilter")?.value || "";
        }

        state.items = deptId
            ? state.allItems.filter(item => String(item.department_id || "") === String(deptId))
            : [];

        if (!state.activeAssessment || !state.allItems.length) {
            state.items = [];
        }

        state.editMode = false;

        renderFacility();
        await loadHistory();
        state.selectedIndex = firstPendingIndex(0);
        showItem();
    }

    async function saveIndicator(event) {
        event.preventDefault();
        const item = selectedItem();
        if (!item) return;

        const period = selectedPeriod();
        const payload = {
            indicator_type: effectiveIndicatorType(),
            indicator_id: num($("indicatorId").value),
            indicator_code: $("indicatorCode").value,
            indicator_name: $("indicatorName").value,
            department_id: num($("indicatorDepartmentId").value),
            formula_id: num($("indicatorFormulaId").value),
            month: period.month,
            year: period.year,
            numerator: num($("indicatorNumerator").value),
            denominator: $("indicatorDenominator").readOnly ? 0 : num($("indicatorDenominator").value),
            denominator_na: $("indicatorDenominator").readOnly ? 1 : 0,
            remarks: $("indicatorRemarks").value
        };

        const response = await SQ.api.post("/performance/v1/indicator_save.php", payload, {
            loader: false,
            showError: false
        });

        notify("success", response?.message || "Indicator saved.");
        await loadHistory();

        if (completionState().complete) {
            state.editMode = false;
        } else {
            state.selectedIndex = firstPendingIndex(state.selectedIndex + 1);
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
        $("btnIndicatorRefresh")?.addEventListener("click", loadIndicators);
        $("indicatorTypeFilter")?.addEventListener("change", loadIndicators);
        $("indicatorDepartmentFilter")?.addEventListener("change", loadIndicators);
        $("indicatorPeriodFilter")?.addEventListener("change", loadIndicators);
        $("indicatorNumerator")?.addEventListener("input", calculateResult);
        $("indicatorDenominator")?.addEventListener("input", calculateResult);
        $("indicatorEntryForm")?.addEventListener("submit", saveIndicator);
        $("btnIndicatorNext")?.addEventListener("click", goNext);
        $("btnIndicatorBack")?.addEventListener("click", goBack);
        $("btnIndicatorEditCompleted")?.addEventListener("click", startEditMode);
    }

    function init() {
        fillPeriods();
        applyInitialQuery();
        bind();
        loadIndicators().catch(error => {
            console.error(error);
            notify("error", error.message || "Unable to load indicator page.");
        });
    }

    SQ.performanceIndicator = { init };
})(window, document);
