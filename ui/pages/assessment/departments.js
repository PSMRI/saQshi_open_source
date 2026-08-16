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

    function label(key, fallback) {
        return SQ.deployment?.label ? SQ.deployment.label(key, fallback) : fallback;
    }

    const API = {
        assessment: "/assessment/v1/active_assessment.php",
        departments: "/framework/v1/my_departments.php",
        status: "/assessment/v1/department-status/list.php",
        assignments: "/assessment/v1/section_assignment.php",
        save: "/assessment/v1/department-status/save.php"
    };

    const state = {
        assessment: null,
        departments: [],
        statusMap: {},
        assignmentMap: {},
        currentAssessorId: 0,
        isAssessorSession: false,
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
        renderEmpty(`Loading ${label("departments", "departments").toLowerCase()}...`);
    }

    function renderDepartments() {
        const tbody = $("departmentTable");

        if (!tbody) {
            return;
        }

        if (!state.departments.length) {
            renderEmpty(`No ${label("departments", "departments").toLowerCase()} found for this ${label("assessment", "assessment").toLowerCase()}.`);
            return;
        }

        tbody.innerHTML = "";

        state.departments.forEach(function (dept, index) {
            const active = isDepartmentActive(dept);
            const assignment = dept.assignment || null;
            const completed = assignment && String(assignment.status || "").toUpperCase() === "COMPLETED";
            const assignedToAnother = assignment && Number(assignment.assessor_id) !== Number(state.currentAssessorId);
            const isAssessor = state.isAssessorSession || state.currentAssessorId > 0;
            const assessorHasActiveClass = isAssessor && Object.values(state.assignmentMap).some(function (item) {
                return Number(item.assessor_id) === Number(state.currentAssessorId)
                    && String(item.status || "").toUpperCase() === "IN_PROGRESS";
            });
            const assignedToCurrentAssessor = assignment && Number(assignment.assessor_id) === Number(state.currentAssessorId);
            const blockedByCurrentClass = assessorHasActiveClass && !assignedToCurrentAssessor;
            const canActivate = !active && !assignedToAnother && !blockedByCurrentClass && dept.can_activate !== false;
            const unit = label("department", "Department");

            tbody.insertAdjacentHTML(
                "beforeend",
                `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <strong>${escapeHtml(dept.dept_name || "-")}</strong>
                            ${assignedToAnother ? "" : `<div class="sq-dept-meta">${escapeHtml(dept.program_tag || "General")}</div>`}
                        </td>
                        <td>${Number(dept.concern_count || 0)}</td>
                        <td>
                            <span class="sq-status ${active ? "sq-status-active" : "sq-status-inactive"}">
                                ${completed ? "Completed" : (assignment ? (assignedToAnother ? "Assigned to another assessor" : "Assigned to you") : (blockedByCurrentClass ? "Complete current class first" : (active ? "Activated" : "Inactive")))}
                            </span>
                        </td>
                        <td>
                            <div class="sq-action">
                                ${assignedToAnother || completed || blockedByCurrentClass ? "" : (!active ? `<button
                                    type="button"
                                    class="sq-btn ${active ? "sq-btn-light" : "sq-btn-primary"}"
                                    data-sq-activate-department="${Number(dept.dept_id || 0)}"
                                    ${canActivate ? "" : "disabled"}>
                                    ${active ? `Continue ${unit} Assessment` : `Start ${unit} Assessment`}
                                </button>` : (isAssessor && !assignment ? `<button type="button" class="sq-btn sq-btn-primary" data-sq-activate-department="${Number(dept.dept_id || 0)}">Claim ${unit}</button>` : ""))}
                                ${active && !assignedToAnother && !completed ? `
                                    <button
                                        type="button"
                                        class="sq-btn sq-btn-primary"
                                        data-sq-assessor-info="${Number(dept.dept_id || 0)}">
                                        ${assignment && !assignedToAnother ? `Continue ${escapeHtml(unit)} Assessment` : escapeHtml(label("assessor_info", "Assessor Info"))}
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
        const requestedAssessmentId = Number(new URLSearchParams(window.location.search).get("assessment_id") || 0);
        const response = await apiGet(API.assessment, requestedAssessmentId ? { assessment_id: requestedAssessmentId } : {});
        const assessment = getAssessment(response);

        if (!assessment || !assessment.assessment_id) {
            state.assessment = null;
            renderAssessment();
            renderEmpty("No active assessment found. Please create an assessment first.");
            return false;
        }

        state.assessment = assessment;
        state.isAssessorSession = Boolean(assessment.is_assessor_session);
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
            fac_id: assessment.fac_id || assessment.fac_id_fk,
            assessment_id: assessment.assessment_id
        });

        state.statusMap = {};
        state.assignmentMap = {};

        getStatusRows(statusResponse).forEach(function (row) {
            state.statusMap[Number(row.dept_id)] = row;
        });

        try {
            const assignmentResponse = await apiGet(API.assignments, { assessment_id: assessment.assessment_id });
            state.currentAssessorId = Number(assignmentResponse?.data?.assessor_id || 0);
            (assignmentResponse?.data?.assignments || []).forEach(function (row) {
                state.assignmentMap[Number(row.dept_id)] = row;
            });
        } catch (_) {
            state.currentAssessorId = 0;
        }

        state.departments = getDepartments(departmentsResponse).map(function (dept) {
            const deptId = Number(dept.dept_id || dept.fac_dept_id || 0);
            const status = state.statusMap[deptId] || {};

            return Object.assign({}, dept, {
                dept_id: deptId,
                is_active: status.is_active ?? dept.is_active ?? 0,
                assignment: state.assignmentMap[deptId] || null,
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
            let response;
            let claimedByAssessor = false;
            try {
                response = await apiPost(API.assignments, {
                    assessment_id: state.assessment.assessment_id,
                    dept_id: deptId,
                    assessment_date: new Date().toISOString().slice(0, 10)
                });
                claimedByAssessor = true;
            } catch (assignmentError) {
                if (state.isAssessorSession || state.currentAssessorId > 0) throw assignmentError;
                response = await apiPost(API.save, { assessment_id: state.assessment.assessment_id, dept_id: deptId, is_active: 1 });
            }

            notify("success", response.message || `${label("department", "Department")} activated.`);

            await loadDepartments();
            renderDepartments();

            if (claimedByAssessor) {
                if (SQ.router && typeof SQ.router.navigate === "function") {
                    SQ.router.navigate("assessment/assessor-info", { dept_id: deptId });
                } else {
                    window.location.href = "/ui/dashboard.html?route=assessment/assessor-info&dept_id=" + deptId;
                }
            }

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
        if (SQ.deployment?.load) {
            await SQ.deployment.load();
            SQ.deployment.applyLabels(document);
        }
        const pageTitle = $("sq-page-title");
        if (pageTitle) {
            pageTitle.textContent = `${label("assessment", "Assessment")} ${label("departments", "Departments")}`;
        }
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
