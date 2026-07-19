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
        saveResponse: "/assessment/v1/save-response.php"
    };

    const state = {
        assessment: null,
        departments: [],
        current: null,
        scopeCheckpoints: [],
        currentIndex: 0,
        departmentStarted: false,
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
        const concern = $("concernSelect")?.selectedOptions?.[0]?.textContent || "this area of concern";

        if (!target) {
            return;
        }

        target.innerHTML = `
            <div class="sq-card-body">
                <div class="sq-completed-state">
                    <div>
                        <div class="sq-completed-title">You have completed all checkpoints for this area of concern.</div>
                        <p>${escapeHtml(concern)} has ${total} completed checkpoint${total === 1 ? "" : "s"}.</p>
                    </div>
                    <div class="sq-completed-actions">
                        <button type="button" class="sq-btn sq-btn-primary" data-sq-edit-completed>
                            Edit / Update Responses
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

        const deptResponse = await apiGet(API.departments, {
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

        state.departments = (deptResponse?.data?.departments || [])
            .map(function (dept) {
                const deptId = Number(dept.dept_id || dept.fac_dept_id || 0);
                return Object.assign({}, dept, { dept_id: deptId });
            })
            .filter(function (dept) {
                return Boolean(activeMap[Number(dept.dept_id)]);
            });

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
        resetBelow("department");

        if (!state.selected.deptId) {
            setStateMessage("Select department to load area of concern.");
            return;
        }

        const response = await apiGet(API.concerns, {
            framework: state.assessment.framework_code || "saqshi-nqas",
            dept_id: state.selected.deptId
        });

        const concerns = response?.data?.concerns || [];
        const select = $("concernSelect");

        setOptions(
            select,
            concerns.length ? "Select area of concern" : "No concern found",
            concerns,
            function (row) { return row.concern_id; },
            function (row) { return row.concern_name || row.concern_des || "Concern"; }
        );

        select.disabled = concerns.length === 0;
        setStateMessage("Select area of concern.");
    }

    async function loadSubtypes() {
        state.selected.concernId = Number($("concernSelect").value || 0);
        state.selected.subtypeId = 0;
        state.selected.checkpointId = 0;
        state.scopeCheckpoints = [];
        state.currentIndex = 0;
        resetBelow("concern");

        if (!state.selected.concernId) {
            setStateMessage("Select area of concern to load subtypes.");
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
                        return checkpoint.saved_response &&
                            checkpoint.saved_response.response_value !== null &&
                            checkpoint.saved_response.response_value !== undefined;
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
        $("checkpointText").textContent = checkpoint.Checkpoint || checkpoint.Measurable_Element || "-";
        $("checkpointVerification").textContent = checkpoint.Means_of_Verification || "";

        renderResponseControl(checkpoint, saved);
        renderProgress(position);

        $("btnPreviousCheckpoint").disabled = Boolean(position.is_first);
        $("btnNextCheckpoint").textContent = position.is_last ? "Finish Scope" : "Next";

        show($("checklistState"), false);
        show($("checkpointPanel"), true);
    }

    async function saveCurrentResponse() {
        if (!state.current || !state.selected.checkpointId) {
            notify("warning", "No checkpoint loaded.");
            return false;
        }

        const payload = currentResponsePayload();

        if (!payload.ok) {
            notify("warning", payload.message || "Please enter response.");
            return false;
        }

        const response = await apiPost(API.saveResponse, {
            assessment_id: state.assessment.assessment_id,
            dept_id: state.selected.deptId,
            checkpoint_id: state.selected.checkpointId,
            response_value: payload.value,
            response_json: payload.json,
            remarks: "",
            evidence_url: ""
        });

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
        notify("success", response.message || "Response saved.");
        renderProgress(state.current.position || {});
        return true;
    }

    async function handleSave() {
        try {
            await saveCurrentResponse();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to save response.");
        }
    }

    async function handleNext() {
        try {
            const saved = await saveCurrentResponse();

            if (!saved) {
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

    function bindEvents() {
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

            if (editButton) {
                if (state.scopeCheckpoints.length) {
                    renderCheckpointAt(0);
                    return;
                }

                loadScopeCheckpoints();
            }

            if (reloadButton) {
                state.scopeCheckpoints = [];
                loadScopeCheckpoints();
            }
        });
        $("btnSaveCheckpoint")?.addEventListener("click", handleSave);
        $("btnNextCheckpoint")?.addEventListener("click", handleNext);
        $("btnPreviousCheckpoint")?.addEventListener("click", handlePrevious);
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        bindEvents();
        setStateMessage("Loading checklist page...");

        try {
            const hasAssessment = await loadAssessment();

            if (hasAssessment) {
                await loadDepartments();
                setStateMessage("Select department to begin checklist.");
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
