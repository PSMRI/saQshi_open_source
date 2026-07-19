/*!
 * ==========================================================
 * SaQshi Open Source
 * Assessment Assessor Info
 * assessor-info.js
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
        getInfo: "/assessment/v1/assessor_info_get.php",
        saveInfo: "/assessment/v1/assessor_info_save.php"
    };

    const state = {
        assessment: null,
        departments: [],
        selectedDeptId: 0,
        currentInfo: null,
        currentAssessor: null,
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

    function show(el, visible) {
        if (el) {
            el.classList.toggle("sq-hidden", !visible);
        }
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

    function getStatusRows(response) {
        return Array.isArray(response?.data) ? response.data : [];
    }

    function setStateMessage(message) {
        const target = $("assessorInfoState");

        if (!target) {
            return;
        }

        target.innerHTML = `
            <div class="sq-card-body">
                <div class="sq-empty-state">${escapeHtml(message)}</div>
            </div>
        `;
    }

    function renderAssessment() {
        const assessment = state.assessment || {};

        if ($("assessmentName")) {
            $("assessmentName").textContent = assessment.assessment_name || "-";
        }

        if ($("assessmentStatus")) {
            $("assessmentStatus").textContent = assessment.status || "-";
        }

        if ($("assessmentFramework")) {
            $("assessmentFramework").textContent = assessment.framework_code || "saqshi-nqas";
        }

        if ($("assessmentId")) {
            $("assessmentId").textContent = assessment.assessment_id || "-";
        }
    }

    function renderDepartmentOptions() {
        const select = $("deptSelect");

        if (!select) {
            return;
        }

        if (!state.departments.length) {
            select.innerHTML = `<option value="">No activated departments</option>`;
            setStateMessage("No activated departments found. Activate a department first.");
            return;
        }

        select.innerHTML = `<option value="">Select department</option>`;

        state.departments.forEach(function (dept) {
            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${Number(dept.dept_id)}">${escapeHtml(dept.dept_name || "Department")}</option>`
            );
        });

        const queryDeptId = Number(new URLSearchParams(window.location.search).get("dept_id") || 0);

        if (queryDeptId && state.departments.some(function (dept) {
            return Number(dept.dept_id) === queryDeptId;
        })) {
            select.value = String(queryDeptId);
            state.selectedDeptId = queryDeptId;
            loadAssessorInfo();
        }
    }

    function renderSavedInfo(info) {
        const grid = $("savedInfoGrid");

        if (!grid) {
            return;
        }

        const rows = [
            ["Assessor Name", info.assessor_name],
            ["Assessee Name", info.assessee_name],
            ["Date of Assessment", info.assessment_date],
            ["Assessment Type", info.assessment_type],
            ["Saved On", info.saved_on || "-"]
        ];

        grid.innerHTML = rows.map(function (row) {
            return `
                <div class="sq-detail-item">
                    <label>${escapeHtml(row[0])}</label>
                    <span>${escapeHtml(row[1] || "-")}</span>
                </div>
            `;
        }).join("");
    }

    function resetForm() {
        const form = $("assessorInfoForm");

        if (form) {
            form.reset();
        }

        applyAssessorContext();

        const date = $("assessment_date");

        if (date && !date.value) {
            date.value = new Date().toISOString().slice(0, 10);
        }
    }

    function applyAssessorContext() {
        const assessor = state.currentAssessor || null;
        const name = $("assessor_name");
        const hint = $("assessorNameHint");

        if (!name) {
            return;
        }

        if (assessor && assessor.assessor_name) {
            name.value = assessor.assessor_name;
            name.readOnly = true;
            name.classList.add("sq-readonly");
            if (hint) {
                hint.textContent = `Using logged-in assessor profile${assessor.assessor_code ? " (" + assessor.assessor_code + ")" : ""}.`;
                hint.classList.remove("sq-hidden");
            }
            return;
        }

        name.readOnly = false;
        name.classList.remove("sq-readonly");
        if (hint) {
            hint.classList.add("sq-hidden");
        }
    }

    function showForm() {
        resetForm();
        show($("assessorInfoState"), false);
        show($("assessorInfoDetails"), false);
        show($("assessorInfoForm"), true);
    }

    function editCurrentInfo() {
        const info = state.currentInfo || {};

        showForm();

        if ($("assessor_name")) {
            $("assessor_name").value = state.currentAssessor?.assessor_name || info.assessor_name || "";
        }

        if ($("assessee_name")) {
            $("assessee_name").value = info.assessee_name || "";
        }

        if ($("assessment_date")) {
            $("assessment_date").value = info.assessment_date || new Date().toISOString().slice(0, 10);
        }

        if ($("assessment_type")) {
            $("assessment_type").value = info.assessment_type || "INTERNAL";
        }

        applyAssessorContext();
    }

    function goToChecklist() {
        if (!state.assessment || !state.selectedDeptId) {
            notify("warning", "Please select department first.");
            return;
        }

        if (SQ.router && typeof SQ.router.navigate === "function") {
            SQ.router.navigate("assessment/checklist", {
                assessment_id: state.assessment.assessment_id,
                dept_id: state.selectedDeptId
            });
            return;
        }

        window.location.href = "/ui/dashboard.html?route=assessment/checklist&assessment_id=" +
            encodeURIComponent(state.assessment.assessment_id) +
            "&dept_id=" +
            encodeURIComponent(state.selectedDeptId);
    }

    function showDetails(info) {
        renderSavedInfo(info);
        show($("assessorInfoState"), false);
        show($("assessorInfoForm"), false);
        show($("assessorInfoDetails"), true);
    }

    async function loadAssessment() {
        const response = await apiGet(API.assessment);
        const assessment = response?.data?.assessment || null;

        if (!assessment || !assessment.assessment_id) {
            state.assessment = null;
            renderAssessment();
            setStateMessage("No active assessment found. Create an assessment first.");
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

        const departmentResponse = await apiGet(API.departments, {
            framework: assessment.framework_code || "saqshi-nqas"
        });

        const statusResponse = await apiGet(API.status, {
            fac_id: assessment.fac_id,
            assessment_id: assessment.assessment_id
        });

        const activeMap = {};

        getStatusRows(statusResponse).forEach(function (row) {
            if (Number(row.is_active) === 1) {
                activeMap[Number(row.dept_id)] = row;
            }
        });

        state.departments = (departmentResponse?.data?.departments || [])
            .map(function (dept) {
                const deptId = Number(dept.dept_id || dept.fac_dept_id || 0);
                return Object.assign({}, dept, {
                    dept_id: deptId
                });
            })
            .filter(function (dept) {
                return Boolean(activeMap[Number(dept.dept_id)]);
            });

        renderDepartmentOptions();
    }

    async function loadAssessorInfo() {
        if (!state.selectedDeptId) {
            show($("assessorInfoDetails"), false);
            show($("assessorInfoForm"), false);
            show($("assessorInfoState"), true);
            setStateMessage("Select an activated department.");
            return;
        }

        setStateMessage("Checking saved assessor information...");
        show($("assessorInfoState"), true);
        show($("assessorInfoDetails"), false);
        show($("assessorInfoForm"), false);

        try {
            const response = await apiGet(API.getInfo, {
                assessment_id: state.assessment.assessment_id,
                dept_id: state.selectedDeptId
            });

            state.currentInfo = response?.data?.info || null;
            state.currentAssessor = response?.data?.current_assessor || null;

            if (response?.data?.has_info && state.currentInfo) {
                showDetails(state.currentInfo);
                return;
            }

            showForm();

        } catch (error) {
            console.error(error);
            setStateMessage(error.message || "Unable to load assessor information.");
            notify("error", error.message || "Unable to load assessor information.");
        }
    }

    function getFormPayload() {
        const form = $("assessorInfoForm");
        const data = new FormData(form);

        return {
            assessment_id: state.assessment.assessment_id,
            dept_id: state.selectedDeptId,
            assessor_name: String(data.get("assessor_name") || "").trim(),
            assessee_name: String(data.get("assessee_name") || "").trim(),
            assessment_date: String(data.get("assessment_date") || "").trim(),
            assessment_type: String(data.get("assessment_type") || "INTERNAL").trim().toUpperCase(),
            assessor_designation: String(state.currentAssessor?.assessor_designation || "").trim(),
            assessor_mobile: String(state.currentAssessor?.assessor_mobile || "").trim(),
            assessor_email: String(state.currentAssessor?.assessor_email || "").trim(),
            assessee_designation: "",
            assessee_mobile: "",
            assessee_email: "",
            remarks: ""
        };
    }

    async function saveAssessorInfo(event) {
        event.preventDefault();

        if (!state.assessment || !state.selectedDeptId) {
            notify("warning", "Please select department.");
            return;
        }

        const payload = getFormPayload();

        if (!payload.assessor_name || !payload.assessee_name || !payload.assessment_date) {
            notify("warning", "Please fill assessor name, assessee name and assessment date.");
            return;
        }

        const btn = $("btnSaveAssessorInfo");

        if (btn) {
            btn.disabled = true;
            btn.textContent = "Saving...";
        }

        try {
            const response = await apiPost(API.saveInfo, payload);
            const info = response?.data?.info || null;

            notify("success", response.message || "Assessor information saved.");

            if (info) {
                state.currentInfo = info;
                showDetails(info);
            } else {
                await loadAssessorInfo();
            }

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to save assessor information.");
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = "Save Details";
            }
        }
    }

    function bindEvents() {
        const select = $("deptSelect");

        if (select && select.dataset.bound !== "1") {
            select.dataset.bound = "1";
            select.addEventListener("change", function () {
                state.selectedDeptId = Number(select.value || 0);
                loadAssessorInfo();
            });
        }

        const form = $("assessorInfoForm");

        if (form && form.dataset.bound !== "1") {
            form.dataset.bound = "1";
            form.addEventListener("submit", saveAssessorInfo);
        }

        const back = $("btnBackDepartments");

        if (back && back.dataset.bound !== "1") {
            back.dataset.bound = "1";
            back.addEventListener("click", function () {
                if (SQ.router && typeof SQ.router.navigate === "function") {
                    SQ.router.navigate("assessment/departments");
                } else {
                    window.location.href = "/ui/dashboard.html";
                }
            });
        }

        const checklist = $("btnStartChecklist");

        if (checklist && checklist.dataset.bound !== "1") {
            checklist.dataset.bound = "1";
            checklist.addEventListener("click", goToChecklist);
        }

        const edit = $("btnEditAssessorInfo");

        if (edit && edit.dataset.bound !== "1") {
            edit.dataset.bound = "1";
            edit.addEventListener("click", editCurrentInfo);
        }
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        bindEvents();
        setStateMessage("Loading assessor information...");

        try {
            const hasAssessment = await loadAssessment();

            if (hasAssessment) {
                await loadDepartments();
            }

        } catch (error) {
            console.error(error);
            setStateMessage(error.message || "Unable to load assessor information.");
            notify("error", error.message || "Unable to load assessor information.");
        } finally {
            state.isLoading = false;
        }
    }

    SQ.assessorInfo = {
        init,
        state
    };

})(window, document);
