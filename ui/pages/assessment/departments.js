/*!
 * ==========================================================
 * SaQshi Open Source
 * Assessment Departments
 * departments.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        assessment: "/assessment/v1/active_assessment.php",
        departments: "/framework/v1/my_departments.php",
        status: "/assessment/v1/department-status/list.php",
        save: "/assessment/v1/department-status/save.php"
    };

    const state = {
        assessment: null,
        departments: [],
        statusMap: {},
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

        Object.keys(params).forEach(function (key) {
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

    async function apiPost(endpoint, payload) {
        if (SQ.api && typeof SQ.api.post === "function") {
            return SQ.api.post(endpoint, payload, { loader: false });
        }

        const response = await fetch("/api" + endpoint, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        return response.json();
    }

    function getAssessment(response) {
        return (
            response?.data?.assessment ||
            response?.assessment ||
            null
        );
    }

    function getDepartments(response) {
        return (
            response?.data?.departments ||
            response?.departments ||
            []
        );
    }

    function getStatusRows(response) {
        if (Array.isArray(response?.data)) {
            return response.data;
        }

        return (
            response?.data?.departments ||
            response?.departments ||
            []
        );
    }

    function isDepartmentActive(dept) {
        const value =
            dept?.is_active ??
            dept?.active ??
            dept?.activated ??
            dept?.status_active ??
            0;

        if (typeof value === "boolean") {
            return value;
        }

        const text = String(value).trim().toLowerCase();

        return (
            text === "1" ||
            text === "true" ||
            text === "yes" ||
            text === "active" ||
            text === "activated"
        );
    }

    function renderAssessment() {
        const assessment = state.assessment || {};

        const name = $("assessmentName");
        const status = $("assessmentStatus");
        const framework = $("assessmentFramework");

        if (name) {
            name.textContent = assessment.assessment_name || "-";
        }

        if (status) {
            status.textContent = assessment.status || "-";
        }

        if (framework) {
            framework.textContent = assessment.framework_code || "saqshi-nqas";
        }
    }

    function renderEmpty(message) {
        const tbody = $("departmentTable");

        if (!tbody) {
            return;
        }

        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="sq-text-center sq-muted-row">
                    ${escapeHtml(message)}
                </td>
            </tr>
        `;
    }

    function renderLoading() {
        renderEmpty("Loading departments...");
    }

    function renderDepartments() {
        const tbody = $("departmentTable");

        if (!tbody) {
            return;
        }

        if (!state.departments.length) {
            renderEmpty("No departments found for this assessment.");
            return;
        }

        tbody.innerHTML = "";

        state.departments.forEach(function (dept, index) {
            const active = isDepartmentActive(dept);
            const canActivate = !active && dept.can_activate !== false;

            tbody.insertAdjacentHTML(
                "beforeend",
                `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <strong>${escapeHtml(dept.dept_name || "-")}</strong>
                            <div class="sq-dept-meta">
                                ${escapeHtml(dept.program_tag || "General")}
                            </div>
                        </td>
                        <td>${Number(dept.concern_count || 0)}</td>
                        <td>
                            <span class="sq-status ${active ? "sq-status-active" : "sq-status-inactive"}">
                                ${active ? "Activated" : "Inactive"}
                            </span>
                        </td>
                        <td>
                            <div class="sq-action">
                                <button
                                    type="button"
                                    class="sq-btn ${active ? "sq-btn-light" : "sq-btn-primary"}"
                                    data-sq-activate-department="${Number(dept.dept_id || 0)}"
                                    ${canActivate ? "" : "disabled"}>
                                    ${active ? "Locked" : "Activate"}
                                </button>
                                ${active ? `
                                    <button
                                        type="button"
                                        class="sq-btn sq-btn-primary"
                                        data-sq-assessor-info="${Number(dept.dept_id || 0)}">
                                        Details
                                    </button>
                                ` : ""}
                            </div>
                        </td>
                    </tr>
                `
            );
        });
    }

    async function loadAssessment() {
        const response = await apiGet(API.assessment);
        const assessment = getAssessment(response);

        if (!assessment || !assessment.assessment_id) {
            state.assessment = null;
            renderAssessment();
            renderEmpty("No active assessment found. Please create an assessment first.");
            return false;
        }

        state.assessment = assessment;
        renderAssessment();
        return true;
    }

    async function loadDepartments() {
        const assessment = state.assessment;

        if (!assessment || !assessment.assessment_id) {
            return;
        }

        const departmentsResponse = await apiGet(API.departments, {
            framework: assessment.framework_code || "saqshi-nqas"
        });

        const statusResponse = await apiGet(API.status, {
            fac_id: assessment.fac_id,
            ass_period: assessment.assessment_id
        });

        state.statusMap = {};

        getStatusRows(statusResponse).forEach(function (row) {
            state.statusMap[Number(row.dept_id)] = row;
        });

        state.departments = getDepartments(departmentsResponse).map(function (dept) {
            const deptId = Number(dept.dept_id || dept.fac_dept_id || 0);
            const status = state.statusMap[deptId] || {};

            return Object.assign({}, dept, {
                dept_id: deptId,
                is_active: status.is_active ?? dept.is_active ?? 0,
                activated_by: status.activated_by ?? dept.activated_by ?? null,
                activated_on: status.activated_on ?? dept.activated_on ?? null
            });
        });
    }

    async function activateDepartment(deptId, button) {
        if (!state.assessment || !state.assessment.assessment_id || !deptId) {
            return;
        }

        button.disabled = true;
        button.textContent = "Saving...";

        try {
            const response = await apiPost(API.save, {
                ass_period: state.assessment.assessment_id,
                dept_id: deptId,
                is_active: 1
            });

            notify("success", response.message || "Department activated.");

            await loadDepartments();
            renderDepartments();

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to activate department.");
            button.disabled = false;
            button.textContent = "Activate";
        }
    }

    function bindEvents() {
        const tbody = $("departmentTable");

        if (!tbody || tbody.dataset.bound === "1") {
            return;
        }

        tbody.dataset.bound = "1";

        tbody.addEventListener("click", function (event) {
            const infoButton = event.target.closest("[data-sq-assessor-info]");

            if (infoButton) {
                const deptId = Number(infoButton.dataset.sqAssessorInfo || 0);

                if (SQ.router && typeof SQ.router.navigate === "function") {
                    SQ.router.navigate("assessment/assessor-info", {
                        dept_id: deptId
                    });
                } else {
                    window.location.href = "/ui/dashboard.html?route=assessment/assessor-info&dept_id=" + deptId;
                }

                return;
            }

            const button = event.target.closest("[data-sq-activate-department]");

            if (!button || button.disabled) {
                return;
            }

            const deptId = Number(button.dataset.sqActivateDepartment || 0);
            activateDepartment(deptId, button);
        });
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        renderLoading();
        bindEvents();

        try {
            const hasAssessment = await loadAssessment();

            if (hasAssessment) {
                await loadDepartments();
                renderDepartments();
            }

        } catch (error) {
            console.error(error);
            renderEmpty(error.message || "Unable to load departments.");
            notify("error", error.message || "Unable to load departments.");
        } finally {
            state.isLoading = false;
        }
    }

    SQ.assessmentDepartments = {
        init,
        state
    };

})(window, document);
