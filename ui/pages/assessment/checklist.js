/*!
 * ==========================================================
 * SaQshi Open Source
 * Assessment Checklist
 * checklist.js
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
        concerns: "/framework/v1/concerns.php",
        subtypes: "/framework/v1/subtypes.php",
        methods: "/framework/v1/assessment_methods.php",
        checkpoints: "/framework/v1/checkpoints.php",
        startDepartment: "/assessment/v1/start_department.php",
        saveResponse: "/assessment/v1/save-response.php",
        saveResponsesBulk: "/assessment/v1/save-responses-bulk.php"
    };

    const state = {
        assessment: null,
        departments: [],
        concerns: [],
        checklistView: "detailed",
        concernChecklist: [],
        activeConcernId: 0,
        current: null,
        scopeCheckpoints: [],
        currentIndex: 0,
        departmentStarted: false,
        readOnly: false,
        concernReadOnly: false,
        assessmentLocked: false,
        selected: {
            deptId: 0,
            concernId: 0,
            subtypeId: 0,
            method: "",
            checkpointId: 0
        },
        answered: new Set(),
        isLoading: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(value) {
        // Keep a valid numeric zero visible (for example, "0 Non Compliant").
        return String(value ?? "")
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

    function domainLabel(key, fallback) {
        return SQ.deployment?.label ? SQ.deployment.label(key, fallback) : fallback;
    }

    function queueUserId() {
        return Number(SQ.auth?.getUser?.()?.u_id || 0);
    }

    async function updateOfflineStatus() {
        const target = $("offlineSyncStatus");
        if (!target || !SQ.offlineResponseQueue) return;
        const count = await SQ.offlineResponseQueue.count(queueUserId());
        target.hidden = count === 0 && navigator.onLine;
        target.textContent = count
            ? `${count} response${count === 1 ? "" : "s"} saved on this device. They will sync when online.`
            : "You are offline. New responses will be saved on this device until connectivity returns.";
    }

    function isNetworkFailure(error) {
        const message = String(error?.message || "").toLowerCase();
        return !navigator.onLine || message.includes("network") || message.includes("fetch") || message.includes("timeout");
    }

    async function syncOfflineResponses() {
        if (!navigator.onLine || !SQ.offlineResponseQueue) return;
        try {
            const sent = await SQ.offlineResponseQueue.flush(queueUserId(), function (payload) {
                return apiPost(API.saveResponse, payload);
            });
            if (sent) notify("success", `${sent} saved response${sent === 1 ? "" : "s"} synchronized.`);
        } catch (error) {
            // Keep every queued item until the server acknowledges it.
            console.warn("Offline response synchronization paused.", error);
        }
        await updateOfflineStatus();
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

    function setOptions(select, placeholder, rows, getValue, getLabel) {
        if (!select) {
            return;
        }

        select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>`;

        rows.forEach(function (row) {
            const value = getValue(row);
            const label = getLabel(row);

            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`
            );
        });
    }

    function getStatusRows(response) {
        return Array.isArray(response?.data) ? response.data : [];
    }

    function selectedScope() {
        return {
            assessment_id: state.assessment?.assessment_id || 0,
            dept_id: state.selected.deptId,
            concern_id: state.selected.concernId,
            subtype_id: state.selected.subtypeId
        };
    }

    function setStateMessage(message) {
        const target = $("checklistState");

        if (!target) {
            return;
        }

        target.innerHTML = `
            <div class="sq-card-body">
                <div class="sq-empty-state">${escapeHtml(message)}</div>
            </div>
        `;
    }

    function isScopeCompleted() {
        const total = state.scopeCheckpoints.length;

        return total > 0 && state.answered.size >= total;
    }

    function checkpointId(checkpoint) {
        return Number(checkpoint?.csqa_id || checkpoint?.checkpoint_id || 0);
    }

    function hasSavedResponse(checkpoint) {
        const value = checkpoint?.saved_response?.response_value;
        return value !== null && value !== undefined && value !== "";
    }

    function firstUnansweredIndex() {
        const index = state.scopeCheckpoints.findIndex(function (checkpoint) {
            const id = checkpointId(checkpoint);
            return id > 0 && !state.answered.has(id);
        });

        return index >= 0 ? index : 0;
    }

    function renderCompletedScopeMessage() {
        const target = $("checklistState");
        const total = state.scopeCheckpoints.length;
        const domain = domainLabel("area_of_concern", "Area of Concern");
        const concern = $("concernSelect")?.selectedOptions?.[0]?.textContent || `this ${domain.toLowerCase()}`;

        if (!target) {
            return;
        }

        target.innerHTML = `
            <div class="sq-card-body">
                <div class="sq-completed-state">
                    <div>
                        <div class="sq-completed-title">You have completed all checkpoints for this ${escapeHtml(domain)}.</div>
                        <p>${escapeHtml(concern)} has ${total} completed checkpoint${total === 1 ? "" : "s"}.</p>
                    </div>
                    <div class="sq-completed-actions">
                        ${state.assessmentLocked ? "" : `<button type="button" class="sq-btn sq-btn-primary" data-sq-edit-completed>
                            Edit / Update Responses
                        </button>`}
                        <button type="button" class="sq-btn sq-btn-light" data-sq-view-completed>
                            View Responses
                        </button>
                        <button type="button" class="sq-btn sq-btn-light" data-sq-reload-scope>
                            Reload Status
                        </button>
                    </div>
                </div>
            </div>
        `;

        show($("checkpointPanel"), false);
        show(target, true);
    }

    function renderLifecycleCompleted(lifecycle) {
        const target = $("checklistState");
        const assessmentCompleted = Boolean(lifecycle?.assessment_completed);

        if (!target) return;

        target.innerHTML = `
            <div class="sq-card-body">
                <div class="sq-completed-state">
                    <div>
                        <div class="sq-completed-title">${assessmentCompleted ? "Assessment completed." : "Department completed."}</div>
                        <p>${assessmentCompleted
                            ? "All active departments and checklist checkpoints are complete. You can start a reassessment from your dashboard when required."
                            : "All checkpoints in this department are complete. Return to the dashboard to continue the next active department."}</p>
                    </div>
                    <div class="sq-completed-actions">
                        <button type="button" class="sq-btn sq-btn-primary" data-sq-back-dashboard>Back to Dashboard</button>
                    </div>
                </div>
            </div>
        `;
        show($("checkpointPanel"), false);
        show(target, true);
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

    function resetBelow(level) {
        if (level === "department") {
            setOptions($("concernSelect"), "Select department first", [], function () {}, function () {});
            $("concernSelect").disabled = true;
        }

        if (level === "department" || level === "concern") {
            setOptions($("subtypeSelect"), "Select concern first", [], function () {}, function () {});
            $("subtypeSelect").disabled = true;
        }

        if (level === "department" || level === "concern" || level === "subtype") {
            setOptions($("methodSelect"), "All methods", [], function () {}, function () {});
            $("methodSelect").disabled = true;
        }

        show($("checkpointPanel"), false);
        show($("checklistState"), true);
    }

    async function loadAssessment() {
        const requestedAssessmentId = Number(new URLSearchParams(window.location.search).get("assessment_id") || 0);
        const response = await apiGet(API.assessment, requestedAssessmentId ? {
            assessment_id: requestedAssessmentId
        } : {});
        const assessment = response?.data?.assessment || null;

        if (!assessment || !assessment.assessment_id) {
            state.assessment = null;
            renderAssessment();
            setStateMessage("No active assessment found. Create an assessment first.");
            return false;
        }

        state.assessment = assessment;
        state.assessmentLocked = String(assessment.status || "").toUpperCase() === "COMPLETED";
        renderAssessment();
        if (state.assessmentLocked) {
            setStateMessage("This assessment is completed and its responses are locked.");
            return false;
        }
        if (assessment.is_assessor_led && !assessment.is_assessor_session) {
            setStateMessage("This assessor-led assessment is read-only for facility users. Use Assessment Progress or Reports to view it.");
            return false;
        }

        return true;
    }

    async function loadDepartments() {
        const assessment = state.assessment;

        const deptResponse = await apiGet(API.departments, {
            framework: assessment.framework_code || "saqshi-nqas"
        });

        let statusResponse = { data: [] };
        let statusLoaded = true;
        try {
            statusResponse = await apiGet(API.status, {
                fac_id: assessment.fac_id || assessment.fac_id_fk,
                assessment_id: assessment.assessment_id
            });
        } catch (error) {
            // Show the available Class/Department master list instead of
            // leaving the selector indefinitely in its initial loading state.
            console.warn("Department status could not be loaded.", error);
            statusLoaded = false;
        }

        const activeMap = {};

        getStatusRows(statusResponse).forEach(function (row) {
            if (Number(row.is_active) === 1) {
                activeMap[Number(row.dept_id)] = row;
            }
        });

        const allDepartments = (deptResponse?.data?.departments || [])
            .map(function (dept) {
                const deptId = Number(dept.dept_id || dept.fac_dept_id || 0);
                return Object.assign({}, dept, { dept_id: deptId });
            });

        state.departments = statusLoaded
            ? allDepartments.filter(function (dept) { return Boolean(activeMap[Number(dept.dept_id)]); })
            : allDepartments;

        setOptions(
            $("deptSelect"),
            state.departments.length ? "Select department" : "No activated departments",
            state.departments,
            function (dept) { return dept.dept_id; },
            function (dept) { return dept.dept_name || "Department"; }
        );

        const queryDeptId = Number(new URLSearchParams(window.location.search).get("dept_id") || 0);

        if (queryDeptId && state.departments.some(function (dept) {
            return Number(dept.dept_id) === queryDeptId;
        })) {
            $("deptSelect").value = String(queryDeptId);
            await loadConcerns();
        }
    }

    async function loadConcerns() {
        state.selected.deptId = Number($("deptSelect").value || 0);
        state.selected.concernId = 0;
        state.selected.subtypeId = 0;
        state.selected.checkpointId = 0;
        state.scopeCheckpoints = [];
        state.currentIndex = 0;
        state.departmentStarted = false;
        state.readOnly = false;
        state.concernReadOnly = false;
        resetBelow("department");

        if (!state.selected.deptId) {
            setStateMessage(`Select ${domainLabel("department", "department").toLowerCase()} to load ${domainLabel("area_of_concern", "area of concern").toLowerCase()}.`);
            return;
        }

        const response = await apiGet(API.concerns, {
            framework: state.assessment.framework_code || "saqshi-nqas",
            dept_id: state.selected.deptId
        });

        const concerns = response?.data?.concerns || [];
        state.concerns = concerns;
        const select = $("concernSelect");

        setOptions(
            select,
            concerns.length ? `Select ${domainLabel("area_of_concern", "area of concern")}` : `No ${domainLabel("area_of_concern", "area of concern")} found`,
            concerns,
            function (row) { return row.concern_id; },
            function (row) { return row.concern_name || row.concern_des || "Concern"; }
        );

        select.disabled = concerns.length === 0;
        setStateMessage(`Select ${domainLabel("area_of_concern", "area of concern")}.`);

        if (state.checklistView === "concern") {
            await loadConcernChecklist();
        }
    }

    async function loadSubtypes() {
        state.selected.concernId = Number($("concernSelect").value || 0);
        state.selected.subtypeId = 0;
        state.selected.checkpointId = 0;
        state.scopeCheckpoints = [];
        state.currentIndex = 0;
        resetBelow("concern");

        if (!state.selected.concernId) {
            setStateMessage(`Select ${domainLabel("area_of_concern", "area of concern").toLowerCase()} to load subtypes.`);
            return;
        }

        const response = await apiGet(API.subtypes, {
            framework: state.assessment.framework_code || "saqshi-nqas",
            dept_id: state.selected.deptId,
            concern_id: state.selected.concernId
        });

        const subtypes = response?.data?.subtypes || [];
        const select = $("subtypeSelect");

        setOptions(
            select,
            subtypes.length ? "Select subtype" : "No subtype found",
            subtypes,
            function (row) { return row.c_subtype_id; },
            function (row) {
                const ref = row.Reference_No ? row.Reference_No + " - " : "";
                return ref + (row.area_of_con_subtypedeatils || "Subtype");
            }
        );

        select.disabled = subtypes.length === 0;
        setStateMessage("Select subtype.");
    }

    async function loadMethods() {
        state.selected.subtypeId = Number($("subtypeSelect").value || 0);
        state.selected.checkpointId = 0;
        state.scopeCheckpoints = [];
        state.currentIndex = 0;
        resetBelow("subtype");

        if (!state.selected.subtypeId) {
            setStateMessage("Select subtype to load checkpoints.");
            return;
        }

        const response = await apiGet(API.methods, {
            framework: state.assessment.framework_code || "saqshi-nqas",
            dept_id: state.selected.deptId,
            concern_id: state.selected.concernId,
            subtype_id: state.selected.subtypeId
        });

        const methods = response?.data?.assessment_methods || [];
        const select = $("methodSelect");

        setOptions(
            select,
            "All methods",
            methods,
            function (row) { return row.code; },
            function (row) { return row.name || row.code; }
        );

        select.disabled = false;
        setStateMessage("Load checkpoint to begin.");
    }

    function responseDefinition(checkpoint) {
        const definition = checkpoint?.response && typeof checkpoint.response === "object"
            ? checkpoint.response
            : {};

        return Object.assign({
            type: "radio",
            mandatory: true,
            label: "Compliance Score"
        }, definition, {
            type: String(definition.type || "radio").toLowerCase()
        });
    }

    function responseOptions(definition) {
        if (Array.isArray(definition.options) && definition.options.length) {
            return definition.options;
        }

        if (definition.type === "yes_no") {
            return [
                { label: "No", value: "0", score: 0 },
                { label: "Yes", value: "1", score: 1 }
            ];
        }

        return [
            { label: "Non Compliance", value: "0", score: 0 },
            { label: "Partial Compliance", value: "1", score: 1 },
            { label: "Fully Compliance", value: "2", score: 2 }
        ];
    }

    function savedJson(saved) {
        if (!saved || !saved.response_json) {
            return {};
        }

        if (typeof saved.response_json === "object") {
            return saved.response_json || {};
        }

        try {
            return JSON.parse(saved.response_json) || {};
        } catch (error) {
            return {};
        }
    }

    function renderChoiceControl(definition, saved) {
        const savedValue = saved?.response_value ?? "";
        const options = responseOptions(definition);
        const legend = escapeHtml(definition.label || "Response");

        return `
            <fieldset class="sq-score-options" data-response-type="${escapeHtml(definition.type)}">
                <legend>${legend}</legend>
                ${options.map(function (option) {
                    const value = String(option.value ?? "");
                    const checked = String(savedValue) === value ? " checked" : "";
                    const scoreLabel = option.score !== undefined && option.score !== null
                        ? `<strong>${escapeHtml(option.score)}</strong>`
                        : "";

                    return `
                        <label class="sq-score-option">
                            <input type="radio" name="response_value" value="${escapeHtml(value)}"${checked}
                                aria-label="${escapeHtml(option.label || value)}">
                            <span>
                                ${scoreLabel}
                                ${escapeHtml(option.label || value)}
                            </span>
                        </label>
                    `;
                }).join("")}
            </fieldset>
        `;
    }

    function renderDropdownControl(definition, saved) {
        const savedValue = saved?.response_value ?? "";
        const options = responseOptions(definition);

        return `
            <div class="sq-form-group" data-response-type="dropdown">
                <label for="checkpointResponseValue">${escapeHtml(definition.label || "Response")}</label>
                <select id="checkpointResponseValue" class="sq-form-control" data-response-value>
                    <option value="">Select response</option>
                    ${options.map(function (option) {
                        const value = String(option.value ?? "");
                        const selected = String(savedValue) === value ? " selected" : "";
                        return `<option value="${escapeHtml(value)}"${selected}>${escapeHtml(option.label || value)}</option>`;
                    }).join("")}
                </select>
            </div>
        `;
    }

    function renderSimpleInputControl(definition, saved) {
        const json = savedJson(saved);
        const savedValue = saved?.response_value ?? json.value ?? "";
        const type = definition.type === "number" ? "number" : "text";

        if (definition.type === "text" && definition.multiline) {
            return `
                <div class="sq-form-group" data-response-type="text">
                    <label for="checkpointResponseValue">${escapeHtml(definition.label || "Text Response")}</label>
                    <textarea id="checkpointResponseValue" class="sq-form-control" data-response-value>${escapeHtml(savedValue)}</textarea>
                </div>
            `;
        }

        return `
            <div class="sq-form-group" data-response-type="${escapeHtml(definition.type)}">
                <label for="checkpointResponseValue">${escapeHtml(definition.label || "Response")}</label>
                <input id="checkpointResponseValue" type="${type}" class="sq-form-control"
                    value="${escapeHtml(savedValue)}" data-response-value>
            </div>
        `;
    }

    function renderFormControl(definition, saved) {
        const json = savedJson(saved);
        const values = json.fields || json || {};
        const fields = Array.isArray(definition.fields) ? definition.fields : [];

        return `
            <div data-response-type="form">
                <div class="sq-response-title">${escapeHtml(definition.label || "Response Details")}</div>
                <div class="sq-response-inline-grid">
                    ${fields.map(function (field) {
                        const key = String(field.key || "");
                        const fieldType = String(field.type || "text").toLowerCase();
                        const inputType = fieldType === "number" ? "number" : fieldType === "date" ? "date" : "text";
                        const value = values[key] ?? "";
                        return `
                            <div class="sq-form-group">
                                <label for="field_${escapeHtml(key)}">${escapeHtml(field.label || key)}</label>
                                <input id="field_${escapeHtml(key)}" type="${inputType}" class="sq-form-control"
                                    value="${escapeHtml(value)}" data-response-field="${escapeHtml(key)}">
                            </div>
                        `;
                    }).join("")}
                </div>
            </div>
        `;
    }

    function renderResponseControl(checkpoint, saved) {
        const target = $("responseControl");
        const definition = responseDefinition(checkpoint);

        if (!target) {
            return;
        }

        if (definition.type === "dropdown") {
            target.innerHTML = renderDropdownControl(definition, saved);
            return;
        }

        if (definition.type === "number" || definition.type === "text") {
            target.innerHTML = renderSimpleInputControl(definition, saved);
            return;
        }

        if (definition.type === "form") {
            target.innerHTML = renderFormControl(definition, saved);
            return;
        }

        target.innerHTML = renderChoiceControl(definition, saved);
    }

    function currentResponsePayload() {
        const definition = responseDefinition(state.current?.checkpoint || {});
        const type = definition.type;

        if (type === "radio" || type === "yes_no") {
            const checked = document.querySelector('input[name="response_value"]:checked');

            return checked
                ? { ok: true, value: checked.value, json: { value: checked.value } }
                : { ok: false, message: "Please select response." };
        }

        if (type === "dropdown") {
            const input = document.querySelector("[data-response-value]");

            return input && input.value !== ""
                ? { ok: true, value: input.value, json: { value: input.value } }
                : { ok: false, message: "Please select response." };
        }

        if (type === "number" || type === "text") {
            const input = document.querySelector("[data-response-value]");
            const value = input ? String(input.value || "").trim() : "";

            if (definition.mandatory !== false && value === "") {
                return { ok: false, message: "Please enter response." };
            }

            return { ok: true, value, json: { value } };
        }

        if (type === "form") {
            const fields = {};

            document.querySelectorAll("[data-response-field]").forEach(function (input) {
                fields[input.dataset.responseField] = input.value;
            });

            return {
                ok: true,
                value: Object.values(fields).find(function (value) {
                    return String(value || "").trim() !== "";
                }) || "",
                json: { fields }
            };
        }

        return { ok: false, message: "Unsupported response type." };
    }

    async function startDepartment() {
        if (state.departmentStarted) {
            return;
        }

        await apiPost(API.startDepartment, {
            assessment_id: state.assessment.assessment_id,
            dept_id: state.selected.deptId
        });

        state.departmentStarted = true;
    }

    async function loadScopeCheckpoints() {
        if (!state.selected.deptId || !state.selected.concernId || !state.selected.subtypeId) {
            notify("warning", "Please select department, area of concern and subtype.");
            return false;
        }

        state.selected.method = $("methodSelect").value || "";
        setStateMessage("Loading checkpoints...");
        show($("checklistState"), true);
        show($("checkpointPanel"), false);

        try {
            await startDepartment();

            const response = await apiGet(API.checkpoints, Object.assign(selectedScope(), {
                framework: state.assessment.framework_code || "saqshi-nqas",
                assessment_method: state.selected.method
            }));

            state.scopeCheckpoints = response?.data?.checkpoints || [];
            state.currentIndex = 0;
            state.answered = new Set(
                state.scopeCheckpoints
                    .filter(function (checkpoint) {
                        return hasSavedResponse(checkpoint);
                    })
                    .map(function (checkpoint) {
                        return Number(checkpoint.csqa_id || 0);
                    })
            );

            if (!state.scopeCheckpoints.length) {
                state.current = null;
                setStateMessage("No checkpoints found for selected scope.");
                return false;
            }

            if (isScopeCompleted()) {
                if (state.readOnly) {
                    renderCheckpointAt(0);
                    return true;
                }
                state.current = null;
                renderCompletedScopeMessage();
                return true;
            }

            renderCheckpointAt(firstUnansweredIndex());
            return true;

        } catch (error) {
            console.error(error);
            setStateMessage(error.message || "Unable to load checkpoints.");
            notify("error", error.message || "Unable to load checkpoints.");
            return false;
        }
    }

    function renderCheckpointAt(index) {
        const checkpoint = state.scopeCheckpoints[index];

        if (!checkpoint) {
            return;
        }

        const total = state.scopeCheckpoints.length;

        state.currentIndex = index;
        state.current = {
            checkpoint: Object.assign({}, checkpoint, {
                checkpoint_id: Number(checkpoint.csqa_id || checkpoint.checkpoint_id || 0)
            }),
            saved_response: checkpoint.saved_response || null,
            position: {
                current: index + 1,
                total: total,
                previous_checkpoint_id: state.scopeCheckpoints[index - 1]?.csqa_id || null,
                next_checkpoint_id: state.scopeCheckpoints[index + 1]?.csqa_id || null,
                is_first: index === 0,
                is_last: index === total - 1
            },
            concern: {
                concern_name: $("concernSelect")?.selectedOptions?.[0]?.textContent || ""
            },
            subtype: {
                Reference_No: $("subtypeSelect")?.selectedOptions?.[0]?.textContent || ""
            }
        };

        state.selected.checkpointId = Number(state.current.checkpoint.checkpoint_id || 0);
        renderCheckpoint();
    }

    async function loadCheckpoint() {
        if (!state.scopeCheckpoints.length) {
            await loadScopeCheckpoints();
            return;
        }

        renderCheckpointAt(firstUnansweredIndex());
    }

    function renderProgress(position) {
        const current = Number(position?.current || 0);
        const total = Number(position?.total || 0);
        const completed = Math.min(state.answered.size, total);
        const remaining = Math.max(total - completed, 0);
        const completedPercent = total ? Math.round((completed / total) * 100) : 0;

        $("progressText").textContent = `Checkpoint ${current} of ${total} | Completed ${completed}`;
        $("remainingText").textContent = `Remaining ${remaining}`;
        $("progressBar").style.width = completedPercent + "%";
    }

    function renderCheckpoint() {
        const data = state.current;
        const checkpoint = data.checkpoint || {};
        const position = data.position || {};
        const saved = data.saved_response || null;

        if (saved && saved.response_value !== null && saved.response_value !== undefined) {
            state.answered.add(Number(checkpoint.checkpoint_id));
        }

        $("checkpointTitle").textContent = "Checkpoint " + (position.current || "-");
        $("checkpointMeta").textContent = [
            data.concern?.concern_name || "",
            data.subtype?.Reference_No || "",
            checkpoint.Assessment_Method ? "Method: " + checkpoint.Assessment_Method : ""
        ].filter(Boolean).join(" | ");

        $("checkpointReference").textContent = checkpoint.csqa_reference_id || checkpoint.csqa_id || "-";
        const measurableElement = String(checkpoint.Measurable_Element || "").trim();
        $("checkpointMeasurableElementValue").textContent = measurableElement;
        $("checkpointMeasurableElement").hidden = !measurableElement;
        $("checkpointText").textContent = checkpoint.Checkpoint || measurableElement || "-";
        $("checkpointVerification").textContent = checkpoint.Means_of_Verification || "";

        $("checkpointPanel")?.classList.toggle("is-answered", hasSavedResponse(checkpoint));
        $("checkpointPanel")?.classList.toggle("is-unanswered", !hasSavedResponse(checkpoint));
        $("checkpointPanel")?.classList.toggle("is-view-only", state.readOnly);

        renderResponseControl(checkpoint, saved);
        document.querySelectorAll("#responseControl input, #responseControl select, #responseControl textarea").forEach(function (input) {
            input.disabled = state.readOnly;
        });
        renderProgress(position);

        $("btnPreviousCheckpoint").disabled = Boolean(position.is_first);
        $("btnSaveCheckpoint").hidden = state.readOnly;
        $("btnNextCheckpoint").textContent = state.readOnly && position.is_last ? "Close View" : (position.is_last ? "Finish Scope" : "Next");

        show($("checklistState"), false);
        show($("checkpointPanel"), true);
    }

    async function saveCurrentResponse() {
        if (state.assessmentLocked) {
            notify("error", "This assessment is completed and responses cannot be changed.");
            return false;
        }
        if (!state.current || !state.selected.checkpointId) {
            notify("warning", "No checkpoint loaded.");
            return false;
        }

        const payload = currentResponsePayload();

        if (!payload.ok) {
            notify("warning", payload.message || "Please enter response.");
            return false;
        }

        const request = {
            assessment_id: state.assessment.assessment_id,
            dept_id: state.selected.deptId,
            checkpoint_id: state.selected.checkpointId,
            response_value: payload.value,
            response_json: payload.json,
            remarks: "",
            evidence_url: ""
        };

        let response;
        let queued = false;
        try {
            response = await apiPost(API.saveResponse, request);
        } catch (error) {
            if (!isNetworkFailure(error) || !SQ.offlineResponseQueue) throw error;
            await SQ.offlineResponseQueue.enqueue(queueUserId(), request);
            response = { data: { offline_queued: true } };
            queued = true;
            await updateOfflineStatus();
        }

        state.answered.add(state.selected.checkpointId);
        if (state.scopeCheckpoints[state.currentIndex]) {
            state.scopeCheckpoints[state.currentIndex].saved_response = {
                response_type: response?.data?.response_type || responseDefinition(state.current.checkpoint).type,
                response_value: response?.data?.response_value ?? payload.value,
                response_json: response?.data?.response_json || payload.json,
                score: response?.data?.score ?? null,
                max_score: response?.data?.max_score ?? 0,
                score_status: response?.data?.score_status || "SCORED"
            };
        }
        notify(queued ? "warning" : "success", queued ? "Response saved on this device. It will sync automatically." : (response.message || "Response saved."));
        renderProgress(state.current.position || {});
        return response;
    }

    async function handleSave() {
        if (state.readOnly) return;
        try {
            await saveCurrentResponse();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to save response.");
        }
    }

    async function handleNext() {
        try {
            if (state.readOnly) {
                if (state.currentIndex >= state.scopeCheckpoints.length - 1) {
                    state.readOnly = false;
                    renderCompletedScopeMessage();
                } else {
                    renderCheckpointAt(state.currentIndex + 1);
                }
                return;
            }
            const saved = await saveCurrentResponse();

            if (!saved) {
                return;
            }

            const lifecycle = saved?.data?.lifecycle || {};
            if (lifecycle.department_completed || lifecycle.assessment_completed) {
                renderLifecycleCompleted(lifecycle);
                notify("success", lifecycle.assessment_completed ? "Assessment completed successfully." : "Department completed successfully.");
                return;
            }

            if (state.currentIndex >= state.scopeCheckpoints.length - 1) {
                renderCompletedScopeMessage();
                notify("success", "All checkpoints in this scope are completed.");
                return;
            }

            renderCheckpointAt(state.currentIndex + 1);

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load next checkpoint.");
        }
    }

    async function handlePrevious() {
        if (state.currentIndex <= 0) {
            return;
        }

        renderCheckpointAt(state.currentIndex - 1);
    }

    function setChecklistView(view) {
        state.checklistView = view === "concern" ? "concern" : "detailed";
        const concernView = state.checklistView === "concern";

        $("checklistScopeCard")?.classList.toggle("is-aoc-mode", concernView);
        document.querySelectorAll("[data-checklist-view]").forEach(function (button) {
            const active = button.dataset.checklistView === state.checklistView;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-selected", String(active));
        });
        show($("concernChecklistPanel"), concernView);
        show($("checkpointPanel"), !concernView && Boolean(state.current));
        show($("checklistState"), !concernView);

        if (concernView) {
            if (state.selected.deptId) {
                loadConcernChecklist();
            } else {
                renderConcernMessage("Select a department to view its Areas of Concern.");
            }
        }
    }

    function renderConcernMessage(message) {
        const target = $("concernChecklistContent");
        if (target) target.innerHTML = `<div class="sq-empty-state">${escapeHtml(message)}</div>`;
        if ($("concernTabs")) $("concernTabs").innerHTML = "";
    }

    async function loadConcernChecklist() {
        if (!state.selected.deptId || !state.assessment) return;

        const target = $("concernChecklistContent");
        if (target) target.innerHTML = '<div class="sq-empty-state">Loading areas of concern...</div>';

        try {
            await startDepartment();
            const concerns = state.concerns.length ? state.concerns : (await apiGet(API.concerns, {
                framework: state.assessment.framework_code || "saqshi-nqas",
                dept_id: state.selected.deptId
            }))?.data?.concerns || [];
            state.concerns = concerns;

            state.concernChecklist = await Promise.all(concerns.map(async function (concern) {
                const subtypeResponse = await apiGet(API.subtypes, {
                    framework: state.assessment.framework_code || "saqshi-nqas",
                    dept_id: state.selected.deptId,
                    concern_id: concern.concern_id
                });
                const subtypes = subtypeResponse?.data?.subtypes || [];
                const groups = await Promise.all(subtypes.map(async function (subtype) {
                    const response = await apiGet(API.checkpoints, {
                        assessment_id: state.assessment.assessment_id,
                        framework: state.assessment.framework_code || "saqshi-nqas",
                        dept_id: state.selected.deptId,
                        concern_id: concern.concern_id,
                        subtype_id: subtype.c_subtype_id
                    });
                    return { subtype: subtype, checkpoints: response?.data?.checkpoints || [] };
                }));
                return { concern: concern, groups: groups };
            }));

            state.activeConcernId = 0;
            renderConcernTabs();
        } catch (error) {
            console.error(error);
            renderConcernMessage(error.message || "Unable to load Areas of Concern.");
            notify("error", error.message || "Unable to load Areas of Concern.");
        }
    }

    function concernProgress(item) {
        const checkpoints = item.groups.flatMap(function (group) { return group.checkpoints; });
        const answered = checkpoints.filter(function (checkpoint) {
            return hasSavedResponse(checkpoint);
        }).length;
        return { total: checkpoints.length, answered: answered };
    }

    function renderConcernTabs() {
        const tabs = $("concernTabs");
        if (!tabs) return;
        if (!state.concernChecklist.length) {
            renderConcernMessage("No Areas of Concern found for this department.");
            return;
        }
        tabs.innerHTML = state.concernChecklist.map(function (item) {
            const concern = item.concern || {};
            const progress = concernProgress(item);
            const active = Number(concern.concern_id) === Number(state.activeConcernId);
            const completed = progress.total > 0 && progress.answered === progress.total;
            return `<button type="button" class="sq-concern-tab${active ? " is-active" : ""}${completed ? " is-complete" : ""}" data-concern-tab="${escapeHtml(concern.concern_id)}">
                <span>${escapeHtml(concern.concern_name || concern.concern_des || "Area of Concern")}</span>
                <small>${progress.answered}/${progress.total}${progress.total && progress.answered === progress.total ? " Complete" : ""}</small>
            </button>`;
        }).join("");
        if (state.activeConcernId) {
            renderActiveConcern();
        } else if ($("concernChecklistContent")) {
            $("concernChecklistContent").innerHTML = `<div class="sq-empty-state">${escapeHtml(`Select a ${domainLabel("area_of_concern", "Domain")} tab to load its checklist.`)}</div>`;
        }
    }

    function renderConcernResponse(checkpoint) {
        const definition = responseDefinition(checkpoint);
        const saved = checkpoint.saved_response || {};
        const id = checkpointId(checkpoint);
        const value = saved.response_value ?? "";
        if (definition.type === "radio" || definition.type === "yes_no") {
            return `<div class="sq-aoc-response">${responseOptions(definition).map(function (option) {
                const optionValue = String(option.value ?? "");
                return `<label class="sq-score-option"><input type="radio" name="aoc_${id}" value="${escapeHtml(optionValue)}"${String(value) === optionValue ? " checked" : ""}><span>${escapeHtml(option.label || optionValue)}</span></label>`;
            }).join("")}</div>`;
        }
        if (definition.type === "dropdown") {
            return `<select class="sq-form-control" data-aoc-value><option value="">Select response</option>${responseOptions(definition).map(function (option) {
                const optionValue = String(option.value ?? "");
                return `<option value="${escapeHtml(optionValue)}"${String(value) === optionValue ? " selected" : ""}>${escapeHtml(option.label || optionValue)}</option>`;
            }).join("")}</select>`;
        }
        const json = savedJson(saved);
        if (definition.type === "form") {
            const values = json.fields || json || {};
            return `<div class="sq-response-inline-grid">${(definition.fields || []).map(function (field) {
                const fieldValue = values[field.key] ?? "";
                return `<div class="sq-form-group"><label>${escapeHtml(field.label || field.key)}</label><input class="sq-form-control" type="${field.type === "number" ? "number" : "text"}" value="${escapeHtml(fieldValue)}" data-aoc-field="${escapeHtml(field.key)}"></div>`;
            }).join("")}</div>`;
        }
        const tag = definition.multiline ? "textarea" : "input";
        const inputType = definition.type === "number" ? "number" : "text";
        const content = value ?? json.value ?? "";
        return tag === "textarea"
            ? `<textarea class="sq-form-control" data-aoc-value>${escapeHtml(content)}</textarea>`
            : `<input class="sq-form-control" type="${inputType}" value="${escapeHtml(content)}" data-aoc-value>`;
    }

    function renderActiveConcern() {
        const item = state.concernChecklist.find(function (row) { return Number(row.concern.concern_id) === Number(state.activeConcernId); });
        const target = $("concernChecklistContent");
        if (!target || !item) return;
        const progress = concernProgress(item);
        target.innerHTML = `<div class="sq-aoc-heading"><strong>${escapeHtml(item.concern.concern_name || item.concern.concern_des || "Area of Concern")}</strong><span>${state.concernReadOnly ? "View only · " : ""}${progress.answered} of ${progress.total} answered</span></div>${item.groups.map(function (group) {
            const subtype = group.subtype || {};
            return `<section class="sq-aoc-subtype"><h4>${escapeHtml([subtype.Reference_No, subtype.area_of_con_subtypedeatils].filter(Boolean).join(" - ") || "Checklist")}</h4>${group.checkpoints.map(function (checkpoint, index) {
                const id = checkpointId(checkpoint);
                const answerState = hasSavedResponse(checkpoint) ? " is-answered" : " is-unanswered";
                const measurableElement = String(checkpoint.Measurable_Element || "").trim();
                const measurableMarkup = measurableElement ? `<div class="sq-measurable-element"><strong>Measurable Element:</strong> ${escapeHtml(measurableElement)}</div>` : "";
                return `<article class="sq-aoc-checkpoint${answerState}${state.concernReadOnly ? " is-view-only" : ""}" data-aoc-checkpoint="${id}"><div class="sq-checkpoint-reference">${escapeHtml(checkpoint.csqa_reference_id || id || "-")}</div>${measurableMarkup}<div class="sq-checkpoint-text"><strong>Checkpoint:</strong> ${escapeHtml(checkpoint.Checkpoint || measurableElement || "-")}</div>${checkpoint.Means_of_Verification ? `<div class="sq-checkpoint-verification">${escapeHtml(checkpoint.Means_of_Verification)}</div>` : ""}<div class="sq-aoc-control">${renderConcernResponse(checkpoint)}</div></article>`;
            }).join("")}</section>`;
        }).join("")}`;
        document.querySelectorAll("#concernChecklistContent input, #concernChecklistContent select, #concernChecklistContent textarea").forEach(function (input) {
            input.disabled = state.concernReadOnly;
        });
        $("btnSaveConcernDraft").hidden = state.concernReadOnly;
        $("btnSubmitConcern").hidden = state.concernReadOnly;
    }

    function concernPayload(card, checkpoint) {
        const definition = responseDefinition(checkpoint);
        if (definition.type === "radio" || definition.type === "yes_no") {
            const input = card.querySelector("input:checked");
            return input ? { value: input.value, json: { value: input.value } } : null;
        }
        if (definition.type === "form") {
            const fields = {};
            card.querySelectorAll("[data-aoc-field]").forEach(function (input) { fields[input.dataset.aocField] = input.value; });
            const value = Object.values(fields).find(function (fieldValue) { return String(fieldValue || "").trim() !== ""; }) || "";
            return value || definition.mandatory === false ? { value: value, json: { fields: fields } } : null;
        }
        const input = card.querySelector("[data-aoc-value]");
        const value = String(input?.value || "").trim();
        return value || definition.mandatory === false ? { value: value, json: { value: value } } : null;
    }

    async function saveActiveConcern(submit) {
        if (state.assessmentLocked) {
            notify("error", "This assessment is completed and responses cannot be changed.");
            return;
        }
        const item = state.concernChecklist.find(function (row) { return Number(row.concern.concern_id) === Number(state.activeConcernId); });
        if (!item) return;
        const entries = item.groups.flatMap(function (group) { return group.checkpoints; }).map(function (checkpoint) {
            return { checkpoint: checkpoint, card: document.querySelector(`[data-aoc-checkpoint="${checkpointId(checkpoint)}"]`) };
        });
        const incomplete = entries.filter(function (entry) { return !concernPayload(entry.card, entry.checkpoint) && responseDefinition(entry.checkpoint).mandatory !== false; });
        if (submit && incomplete.length) {
            notify("warning", `Please answer all mandatory checkpoints before submitting. ${incomplete.length} remaining.`);
            incomplete[0].card?.scrollIntoView({ behavior: "smooth", block: "center" });
            return;
        }
        try {
            await startDepartment();
            const changedEntries = [];
            entries.forEach(function (entry) {
                const payload = concernPayload(entry.card, entry.checkpoint);
                if (!payload) return;
                const saved = entry.checkpoint.saved_response;
                if (saved && String(saved.response_value ?? "") === String(payload.value)) return;
                changedEntries.push({
                    entry: entry,
                    request: { assessment_id: state.assessment.assessment_id, dept_id: state.selected.deptId, checkpoint_id: checkpointId(entry.checkpoint), response_value: payload.value, response_json: payload.json, remarks: "", evidence_url: "" }
                });
            });
            let savedCount = 0;
            let queuedCount = 0;
            if (changedEntries.length) {
                try {
                    const response = await apiPost(API.saveResponsesBulk, {
                        assessment_id: state.assessment.assessment_id,
                        dept_id: state.selected.deptId,
                        responses: changedEntries.map(function (item) { return item.request; })
                    });
                    const savedResponses = response?.data?.responses || [];
                    changedEntries.forEach(function (item) {
                        const saved = savedResponses.find(function (row) { return Number(row.checkpoint_id) === Number(item.request.checkpoint_id); }) || {};
                        item.entry.checkpoint.saved_response = { response_value: saved.response_value ?? item.request.response_value, response_json: saved.response_json || item.request.response_json };
                    });
                    savedCount = changedEntries.length;
                } catch (error) {
                    if (!isNetworkFailure(error) || !SQ.offlineResponseQueue) throw error;
                    for (const item of changedEntries) await SQ.offlineResponseQueue.enqueue(queueUserId(), item.request);
                    queuedCount = changedEntries.length;
                }
            }
            if (submit) {
                const next = state.concernChecklist.find(function (candidate) {
                    const progress = concernProgress(candidate);
                    return Number(candidate.concern?.concern_id) !== Number(state.activeConcernId)
                        && progress.total > 0 && progress.answered < progress.total;
                });
                if (next) {
                    state.activeConcernId = Number(next.concern?.concern_id || 0);
                    state.concernReadOnly = false;
                }
            }
            renderConcernTabs();
            await updateOfflineStatus();
            notify(queuedCount ? "warning" : "success", queuedCount
                ? `${queuedCount} response${queuedCount === 1 ? "" : "s"} saved on this device and pending synchronization.`
                : (submit ? `All responses for this ${domainLabel("area_of_concern", "Area of Concern")} were submitted successfully.` : (savedCount ? "Draft saved." : "No changes to save.")));
        } catch (error) {
            console.error(error);
            notify("error", error.message || `Unable to save ${domainLabel("area_of_concern", "Area of Concern")}.`);
        }
    }

    function bindEvents() {
        document.querySelectorAll("[data-checklist-view]").forEach(function (button) {
            button.addEventListener("click", function () { setChecklistView(button.dataset.checklistView); });
        });
        $("deptSelect")?.addEventListener("change", loadConcerns);
        $("concernSelect")?.addEventListener("change", loadSubtypes);
        $("subtypeSelect")?.addEventListener("change", loadMethods);
        $("methodSelect")?.addEventListener("change", function () {
            state.selected.method = $("methodSelect").value || "";
            state.scopeCheckpoints = [];
            state.currentIndex = 0;
            show($("checkpointPanel"), false);
            show($("checklistState"), true);
            setStateMessage("Load checkpoint to begin.");
        });
        $("btnLoadCheckpoint")?.addEventListener("click", function () {
            state.scopeCheckpoints = [];
            loadCheckpoint();
        });
        $("checklistState")?.addEventListener("click", function (event) {
            const editButton = event.target.closest("[data-sq-edit-completed]");
            const reloadButton = event.target.closest("[data-sq-reload-scope]");
            const dashboardButton = event.target.closest("[data-sq-back-dashboard]");

            if (dashboardButton) {
                if (SQ.router) SQ.router.navigate("assessor/dashboard");
                return;
            }

            if (editButton) {
                state.readOnly = false;
                if (state.scopeCheckpoints.length) {
                    renderCheckpointAt(0);
                    return;
                }

                loadScopeCheckpoints();
            }

            const viewButton = event.target.closest("[data-sq-view-completed]");
            if (viewButton) {
                state.readOnly = true;
                if (state.scopeCheckpoints.length) {
                    renderCheckpointAt(0);
                } else {
                    loadScopeCheckpoints();
                }
            }

            if (reloadButton) {
                state.scopeCheckpoints = [];
                loadScopeCheckpoints();
            }
        });
        $("btnSaveCheckpoint")?.addEventListener("click", handleSave);
        $("btnNextCheckpoint")?.addEventListener("click", handleNext);
        $("btnPreviousCheckpoint")?.addEventListener("click", handlePrevious);
        $("concernTabs")?.addEventListener("click", function (event) {
            const tab = event.target.closest("[data-concern-tab]");
            if (!tab) return;
            state.activeConcernId = Number(tab.dataset.concernTab || 0);
            const item = state.concernChecklist.find(function (row) { return Number(row.concern.concern_id) === state.activeConcernId; });
            const progress = item ? concernProgress(item) : { total: 0, answered: 0 };
            state.concernReadOnly = progress.total > 0 && progress.answered === progress.total
                ? !window.confirm("This Domain is complete. Select OK to Edit / Update responses, or Cancel to View responses only.")
                : false;
            renderConcernTabs();
        });
        $("btnSaveConcernDraft")?.addEventListener("click", function () { saveActiveConcern(false); });
        $("btnSubmitConcern")?.addEventListener("click", function () { saveActiveConcern(true); });
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
        bindEvents();
        window.addEventListener("online", syncOfflineResponses);
        window.addEventListener("offline", updateOfflineStatus);
        setStateMessage("Loading checklist page...");

        try {
            const hasAssessment = await loadAssessment();

            if (hasAssessment) {
                await loadDepartments();
                setStateMessage(`Select ${domainLabel("department", "department").toLowerCase()} to begin checklist.`);
                await syncOfflineResponses();
                await updateOfflineStatus();
            }

        } catch (error) {
            console.error(error);
            setStateMessage(error.message || "Unable to load checklist page.");
            notify("error", error.message || "Unable to load checklist page.");
        } finally {
            state.isLoading = false;
        }
    }

    SQ.assessmentChecklist = {
        init,
        state
    };

})(window, document);
