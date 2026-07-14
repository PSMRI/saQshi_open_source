/*!
 * ==========================================================
 * SaQshi Open Source
 * Gap Closure
 * closure.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        activeAssessment: "/assessment/v1/active_assessment.php",
        actionPlan: "/assessment/v1/action_plan.php",
        close: "/assessment/v1/action_plan_closure.php"
    };

    const state = {
        assessment: null,
        facility: null,
        plans: [],
        filtered: [],
        departments: [],
        concerns: [],
        subtypes: [],
        currentIndex: 0,
        editMode: false,
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
            headers: { "Accept": "application/json" }
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

    function getUrlAssessmentId() {
        const params = new URLSearchParams(window.location.search);
        return Number(params.get("assessment_id") || sessionStorage.getItem("sq_active_assessment_id") || 0);
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

    function renderContext() {
        const assessment = state.assessment || {};
        const facility = state.facility || {};

        setText("closureAssessmentName", assessment.assessment_name || "-");
        setText("closureAssessmentStatus", statusText(assessment.status));
        setText("closureAssessmentFramework", assessment.framework_code || "-");
        setText("closureFacilityName", facility.fac_name || facility.facility_name || "-");
    }

    function renderSummary() {
        const plans = state.plans;
        const open = plans.filter(function (plan) {
            return String(plan.action_plan?.status || "OPEN").toUpperCase() === "OPEN";
        }).length;
        const inProgress = plans.filter(function (plan) {
            return String(plan.action_plan?.status || "").toUpperCase() === "IN_PROGRESS";
        }).length;
        const completed = plans.filter(function (plan) {
            return String(plan.action_plan?.status || "").toUpperCase() === "COMPLETED";
        }).length;

        setText("closureTotalPlans", plans.length);
        setText("closureOpenPlans", open);
        setText("closureInProgressPlans", inProgress);
        setText("closureCompletedPlans", completed);
    }

    function syncDepartments() {
        const map = {};

        state.plans.forEach(function (plan) {
            const dept = plan.department || {};
            const id = Number(plan.dept_id || dept.dept_id || 0);

            if (id > 0 && !map[id]) {
                map[id] = {
                    dept_id: id,
                    dept_name: dept.dept_name || ("Department " + id)
                };
            }
        });

        state.departments = Object.keys(map).map(function (id) {
            return map[id];
        }).sort(function (a, b) {
            return String(a.dept_name || "").localeCompare(String(b.dept_name || ""));
        });
    }

    function syncConcerns() {
        const deptId = $("closureDepartmentFilter")?.value || "";
        const map = {};

        state.plans.forEach(function (plan) {
            const concern = plan.concern || {};
            const id = Number(concern.concern_id || 0);

            if ((!deptId || Number(plan.dept_id) === Number(deptId)) && id > 0 && !map[id]) {
                map[id] = {
                    concern_id: id,
                    concern_name: concern.concern_name || concern.concern_des || ("Area " + id)
                };
            }
        });

        state.concerns = Object.keys(map).map(function (id) {
            return map[id];
        }).sort(function (a, b) {
            return String(a.concern_name || "").localeCompare(String(b.concern_name || ""));
        });
    }

    function syncSubtypes() {
        const deptId = $("closureDepartmentFilter")?.value || "";
        const concernId = $("closureConcernFilter")?.value || "";
        const map = {};

        state.plans.forEach(function (plan) {
            const subtype = plan.subtype || {};
            const planConcern = plan.concern || {};
            const id = Number(subtype.c_subtype_id || 0);

            if (
                (!deptId || Number(plan.dept_id) === Number(deptId)) &&
                (!concernId || Number(planConcern.concern_id) === Number(concernId)) &&
                id > 0 &&
                !map[id]
            ) {
                map[id] = {
                    c_subtype_id: id,
                    label: [subtype.Reference_No, subtype.area_of_con_subtypedeatils]
                        .filter(Boolean)
                        .join(" - ") || ("Subtype " + id)
                };
            }
        });

        state.subtypes = Object.keys(map).map(function (id) {
            return map[id];
        }).sort(function (a, b) {
            return String(a.label || "").localeCompare(String(b.label || ""));
        });
    }

    function renderSelect(id, rows, valueKey, labelKey, emptyLabel, currentValue) {
        const select = $(id);

        if (!select) {
            return;
        }

        select.innerHTML = `<option value="">${escapeHtml(emptyLabel)}</option>`;

        rows.forEach(function (row) {
            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${escapeHtml(row[valueKey])}">${escapeHtml(row[labelKey])}</option>`
            );
        });

        if (currentValue && rows.some(function (row) { return Number(row[valueKey]) === Number(currentValue); })) {
            select.value = currentValue;
        }
    }

    function filteredPlans() {
        const deptId = $("closureDepartmentFilter")?.value || "";
        const concernId = $("closureConcernFilter")?.value || "";
        const subtypeId = $("closureSubtypeFilter")?.value || "";
        const status = $("closureStatusFilter")?.value || "";

        return state.plans.filter(function (plan) {
            const concern = plan.concern || {};
            const subtype = plan.subtype || {};
            const planStatus = String(plan.action_plan?.status || "OPEN").toUpperCase();

            return (!deptId || Number(plan.dept_id) === Number(deptId)) &&
                (!concernId || Number(concern.concern_id) === Number(concernId)) &&
                (!subtypeId || Number(subtype.c_subtype_id) === Number(subtypeId)) &&
                (!status || planStatus === status);
        });
    }

    function updateFiltered(resetIndex = true) {
        state.filtered = filteredPlans();

        if (resetIndex) {
            state.currentIndex = 0;
            state.editMode = false;
        }

        if (state.currentIndex >= state.filtered.length) {
            state.currentIndex = Math.max(0, state.filtered.length - 1);
        }
    }

    function isCurrentScopeCompleted() {
        return state.filtered.length > 0 && state.filtered.every(function (plan) {
            return String(plan.action_plan?.status || "").toUpperCase() === "COMPLETED";
        });
    }

    function closureInputId(plan, suffix) {
        return "cl_" + Number(plan.dept_id || 0) + "_" + Number(plan.checkpoint_id || 0) + "_" + suffix;
    }

    function extractUploadUrl(response) {
        return response?.data?.url ||
            response?.data?.file_url ||
            response?.data?.path ||
            response?.url ||
            response?.file_url ||
            "";
    }

    function renderProgress() {
        const total = state.filtered.length;
        const current = total ? state.currentIndex + 1 : 0;
        const percent = total ? Math.round((current / total) * 100) : 0;
        const dept = $("closureDepartmentFilter")?.selectedOptions?.[0]?.textContent || "All Departments";
        const concern = $("closureConcernFilter")?.selectedOptions?.[0]?.textContent || "All Areas";
        const subtype = $("closureSubtypeFilter")?.selectedOptions?.[0]?.textContent || "All Subtypes";

        setText("closureCurrentPosition", current + " / " + total);
        setText("closureCurrentScopeText", [dept, concern, subtype].join(" | "));

        const bar = $("closureProgressBar");
        if (bar) {
            bar.style.width = percent + "%";
        }
    }

    function renderContainer(resetIndex = false) {
        const target = $("closureContainer");

        if (!target) {
            return;
        }

        updateFiltered(resetIndex);
        renderProgress();

        if (!state.filtered.length) {
            target.innerHTML = `<div class="sq-closure-empty">No closure records found for selected filters.</div>`;
            return;
        }

        if (!state.editMode && isCurrentScopeCompleted()) {
            target.innerHTML = `
                <div class="sq-closure-completed">
                    <div>
                        <strong>You have completed the gap closure.</strong>
                        <p>${state.filtered.length} checkpoint closure${state.filtered.length === 1 ? "" : "s"} completed for this scope.</p>
                    </div>
                    <button type="button" class="sq-btn sq-btn-primary" id="btnEditCompletedClosure">
                        Edit / Update
                    </button>
                </div>
            `;
            return;
        }

        const plan = state.filtered[state.currentIndex];
        const action = plan.action_plan || {};
        const department = plan.department || {};
        const concern = plan.concern || {};
        const subtype = plan.subtype || {};
        const checkpoint = plan.checkpoint || {};
        const response = plan.response || {};
        const status = String(action.status || "OPEN").toUpperCase();

        target.innerHTML = `
            <article class="sq-closure-item">
                <div class="sq-closure-item-head">
                    <div class="sq-closure-title">
                        <strong>${escapeHtml(checkpoint.Checkpoint || checkpoint.Measurable_Element || ("Checkpoint " + plan.checkpoint_id))}</strong>
                        <span class="sq-closure-subtext">
                            ${escapeHtml(department.dept_name || ("Dept " + plan.dept_id))}
                            ${concern.concern_name || concern.concern_des ? " | " + escapeHtml(concern.concern_name || concern.concern_des) : ""}
                            ${subtype.Reference_No ? " | " + escapeHtml(subtype.Reference_No) : ""}
                        </span>
                    </div>
                    <div class="sq-closure-chips">
                        <span class="sq-closure-chip ${status === "COMPLETED" ? "is-completed" : (status === "IN_PROGRESS" ? "is-progress" : "is-open")}">${escapeHtml(status)}</span>
                    </div>
                </div>

                <div class="sq-closure-item-body">
                    <div class="sq-closure-section">
                        <div class="sq-closure-section-title">Action Plan</div>
                        <div class="sq-closure-box">${escapeHtml(action.user_action_plan || action.system_action_plan || checkpoint.system_action_plan || "No action plan available.")}</div>

                        <div class="sq-closure-section-title">Closure Update</div>
                        <div class="sq-closure-grid">
                            <select id="${closureInputId(plan, "closed")}" class="sq-form-control">
                                <option value="0" ${status !== "COMPLETED" ? "selected" : ""}>Not Closed</option>
                                <option value="1" ${status === "COMPLETED" ? "selected" : ""}>Gap Closed</option>
                            </select>
                            <select id="${closureInputId(plan, "score")}" class="sq-form-control">
                                <option value="">Revised Score</option>
                                <option value="0" ${Number(action.revised_score) === 0 ? "selected" : ""}>0</option>
                                <option value="1" ${Number(action.revised_score) === 1 ? "selected" : ""}>1</option>
                                <option value="2" ${Number(action.revised_score) === 2 ? "selected" : ""}>2</option>
                            </select>
                            <input id="${closureInputId(plan, "evidence")}" type="hidden" value="${escapeHtml(action.closure_evidence_url || "")}">
                        </div>

                        <div class="sq-closure-upload">
                            <div class="sq-closure-upload-status" id="${closureInputId(plan, "evidenceStatus")}">
                                ${action.closure_evidence_url
                                    ? `<a href="${escapeHtml(action.closure_evidence_url)}" target="_blank" rel="noopener">View uploaded evidence</a>`
                                    : `<span>No evidence uploaded</span>`}
                            </div>
                            <div class="sq-closure-upload-actions">
                                <label class="sq-btn sq-btn-light">
                                    Upload File
                                    <input
                                        type="file"
                                        class="sq-closure-file-input"
                                        data-sq-closure-upload="${Number(plan.dept_id)}:${Number(plan.checkpoint_id)}"
                                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                                </label>
                                <label class="sq-btn sq-btn-light">
                                    Camera
                                    <input
                                        type="file"
                                        class="sq-closure-file-input"
                                        data-sq-closure-upload="${Number(plan.dept_id)}:${Number(plan.checkpoint_id)}"
                                        accept="image/*"
                                        capture="environment">
                                </label>
                                <button
                                    type="button"
                                    class="sq-btn sq-btn-light sq-closure-delete-evidence"
                                    data-sq-closure-delete="${Number(plan.dept_id)}:${Number(plan.checkpoint_id)}"
                                    ${action.closure_evidence_url ? "" : "hidden"}>
                                    Delete
                                </button>
                            </div>
                        </div>

                        <textarea
                            id="${closureInputId(plan, "remarks")}"
                            class="sq-form-control sq-closure-textarea"
                            placeholder="Enter closure remarks">${escapeHtml(action.closure_remarks || "")}</textarea>

                        <div class="sq-closure-subtext">
                            Original score ${Number(response.score || 0).toFixed(2)} / 2
                            ${action.closed_on ? " | Closed on: " + escapeHtml(action.closed_on) : ""}
                        </div>
                    </div>

                    <aside class="sq-closure-section">
                        <div class="sq-closure-section-title">Suggested Action Plan</div>
                        <div class="sq-closure-box">${escapeHtml(action.system_action_plan || checkpoint.system_action_plan || "No suggested action plan available.")}</div>

                        <div class="sq-closure-section-title">Original Remarks</div>
                        <div class="sq-closure-box">${escapeHtml(response.remarks || "-")}</div>
                    </aside>
                </div>

                <div class="sq-closure-nav">
                    <button type="button" class="sq-btn sq-btn-light" id="btnPreviousClosure" ${state.currentIndex <= 0 ? "disabled" : ""}>
                        Back
                    </button>
                    <div class="sq-closure-nav-actions">
                        <button type="button" class="sq-btn sq-btn-light" data-sq-save-closure="${Number(plan.dept_id)}:${Number(plan.checkpoint_id)}">
                            ${status === "COMPLETED" ? "Update" : "Save"}
                        </button>
                        <button type="button" class="sq-btn sq-btn-primary" data-sq-save-next-closure="${Number(plan.dept_id)}:${Number(plan.checkpoint_id)}" ${state.currentIndex >= state.filtered.length - 1 ? "disabled" : ""}>
                            Save & Next
                        </button>
                    </div>
                    <button type="button" class="sq-btn sq-btn-light" id="btnNextClosure" ${state.currentIndex >= state.filtered.length - 1 ? "disabled" : ""}>
                        Next
                    </button>
                </div>
            </article>
        `;
    }

    async function loadAssessment() {
        const assessmentId = getUrlAssessmentId();

        if (assessmentId > 0) {
            state.assessment = { assessment_id: assessmentId };
            return;
        }

        const response = await apiGet(API.activeAssessment);
        const assessment = response?.data?.assessment || response?.assessment || null;

        if (!assessment || !assessment.assessment_id) {
            throw new Error("No active assessment found. Create or select an assessment first.");
        }

        state.assessment = assessment;
    }

    async function loadClosurePlans(options = {}) {
        const assessmentId = Number(state.assessment?.assessment_id || getUrlAssessmentId() || 0);
        const keepCurrent = options.keepCurrent === true;
        const currentPlan = state.filtered[state.currentIndex] || null;
        const currentKey = currentPlan ? Number(currentPlan.dept_id) + ":" + Number(currentPlan.checkpoint_id) : "";

        if (!assessmentId) {
            throw new Error("assessment_id is required.");
        }

        const response = await apiGet(API.actionPlan, {
            assessment_id: assessmentId
        });

        if (response.status === "error" || response.success === false) {
            throw new Error(response.message || "Unable to load closure records.");
        }

        const data = response.data || {};
        state.assessment = data.assessment || state.assessment || {};
        state.facility = data.facility || {};
        state.plans = (data.action_plans || []).filter(function (plan) {
            return Boolean(plan.action_plan && plan.action_plan.has_saved_plan);
        });

        syncDepartments();
        renderSelect("closureDepartmentFilter", state.departments, "dept_id", "dept_name", "All Departments", $("closureDepartmentFilter")?.value || "");
        syncConcerns();
        renderSelect("closureConcernFilter", state.concerns, "concern_id", "concern_name", "All Areas", $("closureConcernFilter")?.value || "");
        syncSubtypes();
        renderSelect("closureSubtypeFilter", state.subtypes, "c_subtype_id", "label", "All Subtypes", $("closureSubtypeFilter")?.value || "");
        renderContext();
        renderSummary();

        updateFiltered(!keepCurrent);

        if (keepCurrent && currentKey) {
            const index = state.filtered.findIndex(function (plan) {
                return Number(plan.dept_id) + ":" + Number(plan.checkpoint_id) === currentKey;
            });

            if (index >= 0) {
                state.currentIndex = index;
            }
        }

        renderContainer(false);
    }

    function findPlan(deptId, checkpointId) {
        return state.plans.find(function (plan) {
            return Number(plan.dept_id) === Number(deptId) &&
                Number(plan.checkpoint_id) === Number(checkpointId);
        });
    }

    async function saveClosure(deptId, checkpointId, button, moveNext) {
        const plan = findPlan(deptId, checkpointId);

        if (!plan) {
            return;
        }

        const closed = ($(closureInputId(plan, "closed"))?.value || "0") === "1";
        const revisedScore = $(closureInputId(plan, "score"))?.value || "";
        const payload = {
            assessment_id: Number(state.assessment?.assessment_id || plan.assessment_id || 0),
            dept_id: Number(deptId),
            checkpoint_id: Number(checkpointId),
            is_gap_closed: closed,
            revised_score: closed && revisedScore !== "" ? Number(revisedScore) : null,
            closure_remarks: $(closureInputId(plan, "remarks"))?.value.trim() || "",
            closure_evidence_url: $(closureInputId(plan, "evidence"))?.value.trim() || ""
        };

        if (closed && revisedScore === "") {
            notify("warning", "Please select revised score when gap is closed.");
            return;
        }

        const originalText = button?.textContent;

        if (button) {
            button.disabled = true;
            button.textContent = "Saving...";
        }

        try {
            const response = await apiPost(API.close, payload);

            if (response.status === "error" || response.success === false) {
                throw new Error(response.message || "Unable to update gap closure.");
            }

            notify("success", response.message || "Gap closure updated.");
            await loadClosurePlans({ keepCurrent: true });

            if (moveNext && state.currentIndex < state.filtered.length - 1) {
                state.currentIndex += 1;
                renderContainer(false);
            }

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to update gap closure.");

            if (button) {
                button.disabled = false;
                button.textContent = originalText || "Save";
            }
        }
    }

    async function uploadClosureEvidence(deptId, checkpointId, input) {
        const plan = findPlan(deptId, checkpointId);
        const file = input?.files?.[0] || null;

        if (!plan || !file) {
            return;
        }

        const status = $(closureInputId(plan, "evidenceStatus"));
        const hidden = $(closureInputId(plan, "evidence"));
        const deleteButton = document.querySelector(`[data-sq-closure-delete="${Number(deptId)}:${Number(checkpointId)}"]`);

        if (!SQ.upload || typeof SQ.upload.upload !== "function") {
            notify("error", "Upload service is not available.");
            return;
        }

        if (status) {
            status.innerHTML = "<span>Uploading evidence...</span>";
        }

        input.disabled = true;

        try {
            const response = await SQ.upload.upload(file, {
                category: "closure",
                assessment_id: Number(state.assessment?.assessment_id || plan.assessment_id || 0),
                dept_id: Number(deptId),
                checkpoint_id: Number(checkpointId)
            }, {
                loader: true
            });

            const url = extractUploadUrl(response);

            if (!url) {
                throw new Error("Upload completed but file URL was not received.");
            }

            if (hidden) {
                hidden.value = url;
            }

            if (status) {
                status.innerHTML = `<a href="${escapeHtml(url)}" target="_blank" rel="noopener">View uploaded evidence</a>`;
            }

            if (deleteButton) {
                deleteButton.hidden = false;
            }

            notify("success", "Evidence uploaded successfully.");

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to upload evidence.");

            if (status && !hidden?.value) {
                status.innerHTML = "<span>No evidence uploaded</span>";
            }
        } finally {
            input.disabled = false;
            input.value = "";
        }
    }

    async function deleteClosureEvidence(deptId, checkpointId, button) {
        const plan = findPlan(deptId, checkpointId);

        if (!plan) {
            return;
        }

        const hidden = $(closureInputId(plan, "evidence"));
        const status = $(closureInputId(plan, "evidenceStatus"));
        const url = hidden?.value.trim() || "";

        if (!url) {
            if (button) {
                button.hidden = true;
            }
            return;
        }

        const originalText = button?.textContent;

        if (button) {
            button.disabled = true;
            button.textContent = "Deleting...";
        }

        try {
            if (SQ.upload && typeof SQ.upload.delete === "function") {
                await SQ.upload.delete(url);
            } else {
                await apiPost("/files/v1/delete.php", { url });
            }

            hidden.value = "";

            if (status) {
                status.innerHTML = "<span>No evidence uploaded</span>";
            }

            if (button) {
                button.hidden = true;
            }

            notify("success", "Evidence removed.");

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to delete evidence.");

        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText || "Delete";
            }
        }
    }

    function bindEvents() {
        $("closureDepartmentFilter")?.addEventListener("change", function () {
            if ($("closureConcernFilter")) {
                $("closureConcernFilter").value = "";
            }
            if ($("closureSubtypeFilter")) {
                $("closureSubtypeFilter").value = "";
            }
            syncConcerns();
            renderSelect("closureConcernFilter", state.concerns, "concern_id", "concern_name", "All Areas", "");
            syncSubtypes();
            renderSelect("closureSubtypeFilter", state.subtypes, "c_subtype_id", "label", "All Subtypes", "");
            renderContainer(true);
        });

        $("closureConcernFilter")?.addEventListener("change", function () {
            if ($("closureSubtypeFilter")) {
                $("closureSubtypeFilter").value = "";
            }
            syncSubtypes();
            renderSelect("closureSubtypeFilter", state.subtypes, "c_subtype_id", "label", "All Subtypes", "");
            renderContainer(true);
        });

        $("closureSubtypeFilter")?.addEventListener("change", function () {
            renderContainer(true);
        });

        $("closureStatusFilter")?.addEventListener("change", function () {
            renderContainer(true);
        });

        $("btnRefreshClosure")?.addEventListener("click", function () {
            loadClosurePlans();
        });

        document.addEventListener("click", function (event) {
            const save = event.target.closest("[data-sq-save-closure]");
            const saveNext = event.target.closest("[data-sq-save-next-closure]");

            if (save) {
                const parts = String(save.dataset.sqSaveClosure || "").split(":");
                saveClosure(parts[0], parts[1], save, false);
            }

            if (saveNext) {
                const parts = String(saveNext.dataset.sqSaveNextClosure || "").split(":");
                saveClosure(parts[0], parts[1], saveNext, true);
            }

            if (event.target.closest("#btnPreviousClosure")) {
                state.currentIndex = Math.max(0, state.currentIndex - 1);
                renderContainer(false);
            }

            if (event.target.closest("#btnNextClosure")) {
                state.currentIndex = Math.min(state.filtered.length - 1, state.currentIndex + 1);
                renderContainer(false);
            }

            if (event.target.closest("#btnEditCompletedClosure")) {
                state.editMode = true;
                state.currentIndex = 0;
                renderContainer(false);
            }

            const deleteEvidence = event.target.closest("[data-sq-closure-delete]");

            if (deleteEvidence) {
                const parts = String(deleteEvidence.dataset.sqClosureDelete || "").split(":");
                deleteClosureEvidence(parts[0], parts[1], deleteEvidence);
            }
        });

        document.addEventListener("change", function (event) {
            const input = event.target.closest("[data-sq-closure-upload]");

            if (!input) {
                return;
            }

            const parts = String(input.dataset.sqClosureUpload || "").split(":");
            uploadClosureEvidence(parts[0], parts[1], input);
        });
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
            await loadClosurePlans();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to initialize gap closure.");

            const target = $("closureContainer");
            if (target) {
                target.innerHTML = `<div class="sq-closure-empty">${escapeHtml(error.message || "Unable to initialize gap closure.")}</div>`;
            }
        } finally {
            state.isLoading = false;
        }
    }

    SQ.cqiClosure = {
        init,
        state
    };

})(window, document);
