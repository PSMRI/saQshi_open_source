/*!
 * ==========================================================
 * SaQshi Open Source
 * Gap Analysis
 * gap-analysis.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        activeAssessment: "/assessment/v1/active_assessment.php",
        departments: "/assessment/v1/department-status/list.php",
        gapAnalysis: "/assessment/v1/gap_analysis.php"
    };

    const state = {
        assessment: null,
        facility: null,
        departments: [],
        gaps: [],
        summary: {},
        isLoading: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function notify(type, message) {
        if (SQ.notification && typeof SQ.notification[type] === "function") {
            SQ.notification[type](message);
            return;
        }

        if (SQ.toast) {
            SQ.toast(message, type);
        }
    }

    async function apiGet(endpoint, params = {}) {
        if (SQ.api && typeof SQ.api.get === "function") {
            return SQ.api.get(endpoint, params, { loader: false });
        }

        const url = new URL("/api" + endpoint, window.location.origin);

        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== null && params[key] !== undefined && params[key] !== "") {
                url.searchParams.set(key, params[key]);
            }
        });

        const response = await fetch(url.toString(), {
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });

        return response.json();
    }

    function getUrlAssessmentId() {
        const params = new URLSearchParams(window.location.search);
        return Number(params.get("assessment_id") || sessionStorage.getItem("sq_active_assessment_id") || 0);
    }

    function getAssessment(response) {
        return response?.data?.assessment || response?.assessment || null;
    }

    function getDepartmentRows(response) {
        if (Array.isArray(response?.data)) {
            return response.data;
        }

        return response?.data?.departments || response?.departments || [];
    }

    function setText(id, value) {
        const el = $(id);

        if (el) {
            el.textContent = value;
        }
    }

    function statusText(value) {
        const status = String(value || "").toUpperCase();
        return status === "ACTIVE" ? "In Progress" : (status || "-");
    }

    function gapTypeText(value) {
        return String(value || "") === "NON_COMPLIANT"
            ? "Non Compliance"
            : "Partial Compliance";
    }

    function renderContext() {
        const assessment = state.assessment || {};
        const facility = state.facility || {};

        setText("assessmentName", assessment.assessment_name || "-");
        setText("assessmentStatus", statusText(assessment.status));
        setText("assessmentFramework", assessment.framework_code || "-");
        setText("facilityName", facility.fac_name || facility.facility_name || "-");
    }

    function renderSummary() {
        const summary = state.summary || {};

        setText("gapTotal", Number(summary.total_original_gaps || 0));
        setText("gapOpen", Number(summary.open_gaps || 0));
        setText("gapClosed", Number(summary.closed_gaps || 0));
        setText("gapNonCompliance", Number(summary.non_compliant || 0));
        setText("gapPartial", Number(summary.partially_compliant || 0));
        setText("gapClosure", Number(summary.closure_percent || 0).toFixed(2) + "%");
    }

    function renderDepartmentFilter() {
        const select = $("departmentFilter");

        if (!select) {
            return;
        }

        const current = select.value;

        select.innerHTML = '<option value="">All Departments</option>';

        state.departments.forEach(function (dept) {
            const id = dept.dept_id || dept.department_id || "";
            const name = dept.dept_name || dept.department_name || ("Department " + id);

            if (!id) {
                return;
            }

            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`
            );
        });

        if (current) {
            select.value = current;
        }
    }

    function syncDepartmentsFromGaps() {
        const map = {};

        state.departments.forEach(function (dept) {
            const id = Number(dept.dept_id || dept.department_id || 0);

            if (id > 0) {
                map[id] = dept;
            }
        });

        state.gaps.forEach(function (gap) {
            const dept = gap.department || {};
            const id = Number(gap.dept_id || dept.dept_id || 0);

            if (id > 0 && !map[id]) {
                map[id] = {
                    dept_id: id,
                    dept_name: dept.dept_name || ("Department " + id),
                    is_active: 1
                };
            }
        });

        state.departments = Object.keys(map)
            .map(function (id) {
                return map[id];
            })
            .sort(function (a, b) {
                return String(a.dept_name || "").localeCompare(String(b.dept_name || ""));
            });
    }

    function filteredGaps() {
        const status = $("statusFilter")?.value || "";
        const type = $("typeFilter")?.value || "";

        return state.gaps.filter(function (gap) {
            return (!status || gap.gap_status === status) &&
                (!type || gap.gap_type === type);
        });
    }

    function renderRows() {
        const target = $("gapTableBody");

        if (!target) {
            return;
        }

        const rows = filteredGaps();

        if (!rows.length) {
            target.innerHTML = `
                <tr>
                    <td colspan="7" class="sq-gap-empty">
                        No gaps found for selected filters.
                    </td>
                </tr>
            `;
            return;
        }

        target.innerHTML = rows.map(function (gap) {
            const department = gap.department || {};
            const concern = gap.concern || {};
            const subtype = gap.subtype || {};
            const checkpoint = gap.checkpoint || {};
            const score = gap.score || {};
            const response = gap.response || {};
            const closure = gap.action_plan_closure || {};
            const openClass = gap.gap_status === "CLOSED" ? "is-closed" : "is-open";
            const typeClass = gap.gap_type === "NON_COMPLIANT" ? "is-nc" : "is-pc";

            return `
                <tr>
                    <td>
                        <div class="sq-gap-main">
                            <strong>${escapeHtml(department.dept_name || ("Dept " + gap.dept_id))}</strong>
                            <span class="sq-gap-subtext">ID ${escapeHtml(gap.dept_id)}</span>
                        </div>
                    </td>
                    <td>
                        <div class="sq-gap-main">
                            <strong>${escapeHtml(concern.concern_name || concern.concern_des || "-")}</strong>
                            <span class="sq-gap-subtext">${escapeHtml(subtype.Reference_No || "")} ${escapeHtml(subtype.area_of_con_subtypedeatils || "")}</span>
                        </div>
                    </td>
                    <td>
                        <div class="sq-gap-main">
                            <strong>${escapeHtml(checkpoint.Checkpoint || checkpoint.Measurable_Element || ("Checkpoint " + gap.checkpoint_id))}</strong>
                            <span class="sq-gap-subtext">${escapeHtml(checkpoint.csqa_reference_id || "")}</span>
                        </div>
                    </td>
                    <td>${escapeHtml(checkpoint.Assessment_Method || "-")}</td>
                    <td>
                        <div class="sq-gap-score">
                            <strong>${Number(score.original_score || 0).toFixed(2)} / 2</strong>
                            <span class="sq-gap-chip ${typeClass}">${escapeHtml(gapTypeText(gap.gap_type))}</span>
                        </div>
                    </td>
                    <td>
                        <span class="sq-gap-chip ${openClass}">${escapeHtml(gap.gap_status || "OPEN")}</span>
                        <div class="sq-gap-subtext">${escapeHtml(closure.status || "No action plan")}</div>
                    </td>
                    <td>
                        <div class="sq-gap-subtext">${escapeHtml(response.remarks || "-")}</div>
                    </td>
                </tr>
            `;
        }).join("");
    }

    function renderLoading() {
        const target = $("gapTableBody");

        if (target) {
            target.innerHTML = `
                <tr>
                    <td colspan="7" class="sq-gap-empty">
                        Loading gap analysis...
                    </td>
                </tr>
            `;
        }
    }

    async function loadAssessment() {
        const urlAssessmentId = getUrlAssessmentId();

        if (urlAssessmentId > 0) {
            state.assessment = {
                assessment_id: urlAssessmentId
            };
            return;
        }

        const response = await apiGet(API.activeAssessment);

        if (response.status === "error" || response.success === false) {
            throw new Error(response.message || "Unable to load active assessment.");
        }

        state.assessment = getAssessment(response);

        if (!state.assessment || !state.assessment.assessment_id) {
            throw new Error("No active assessment found. Create or select an assessment first.");
        }
    }

    async function loadDepartments() {
        const assessmentId = Number(state.assessment?.assessment_id || 0);
        const facId = Number(
            state.assessment?.fac_id ||
            state.assessment?.fac_id_fk ||
            state.facility?.fac_id ||
            0
        );

        if (!assessmentId || !facId) {
            state.departments = [];
            return;
        }

        try {
            const response = await apiGet(API.departments, {
                fac_id: facId,
                ass_period: assessmentId
            });

            if (response.status === "error" || response.success === false) {
                state.departments = [];
                return;
            }

            state.departments = getDepartmentRows(response)
                .filter(function (dept) {
                    const active = dept.is_active ?? dept.active ?? dept.activated ?? 0;
                    const normalized = String(active).toLowerCase();

                    return normalized === "1" ||
                        normalized === "true" ||
                        normalized === "active" ||
                        normalized === "activated";
                });

            renderDepartmentFilter();
        } catch (error) {
            console.warn("Department status list skipped:", error);
            state.departments = [];
        }
    }

    async function loadGaps() {
        const assessmentId = state.assessment?.assessment_id || getUrlAssessmentId();
        const deptId = $("departmentFilter")?.value || "";

        if (!assessmentId) {
            throw new Error("assessment_id is required for gap analysis.");
        }

        renderLoading();

        const response = await apiGet(API.gapAnalysis, {
            assessment_id: assessmentId,
            dept_id: deptId
        });

        if (response.status === "error" || response.success === false) {
            throw new Error(response.message || "Unable to load gap analysis.");
        }

        const data = response.data || {};

        state.assessment = data.assessment || state.assessment || {};
        state.facility = data.facility || {};
        state.summary = data.summary || {};
        state.gaps = data.all_gaps || [
            ...(data.open_gaps || []),
            ...(data.closed_gaps || [])
        ];

        syncDepartmentsFromGaps();
        renderDepartmentFilter();
        renderContext();
        renderSummary();
        renderRows();
    }

    async function refresh() {
        try {
            await loadGaps();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load gap analysis.");

            const target = $("gapTableBody");
            if (target) {
                target.innerHTML = `
                    <tr>
                        <td colspan="7" class="sq-gap-empty">
                            ${escapeHtml(error.message || "Unable to load gap analysis.")}
                        </td>
                    </tr>
                `;
            }
        }
    }

    function bindEvents() {
        $("departmentFilter")?.addEventListener("change", refresh);
        $("statusFilter")?.addEventListener("change", renderRows);
        $("typeFilter")?.addEventListener("change", renderRows);
        $("btnRefreshGapAnalysis")?.addEventListener("click", refresh);
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        bindEvents();

        try {
            await loadAssessment();
            renderContext();
            await loadDepartments();
            await loadGaps();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to initialize gap analysis.");

            const target = $("gapTableBody");
            if (target) {
                target.innerHTML = `
                    <tr>
                        <td colspan="7" class="sq-gap-empty">
                            ${escapeHtml(error.message || "Unable to initialize gap analysis.")}
                        </td>
                    </tr>
                `;
            }
        } finally {
            state.isLoading = false;
        }
    }

    SQ.gapAnalysis = {
        init,
        refresh,
        state
    };

})(window, document);
