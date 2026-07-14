/*!
 * ==========================================================
 * SaQshi Open Source
 * Action Plan
 * action-plan.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        activeAssessment: "/assessment/v1/active_assessment.php",
        actionPlan: "/assessment/v1/action_plan.php",
        save: "/assessment/v1/action_plan_save.php"
    };

    const state = {
        assessment: null,
        facility: null,
        summary: {},
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

    function gapTypeText(value) {
        return value === "NON_COMPLIANT" ? "Non Compliance" : "Partial Compliance";
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

        setText("totalPlans", Number(summary.total_action_plans || 0));
        setText("nonCompliantPlans", Number(summary.non_compliant || 0));
        setText("partialPlans", Number(summary.partially_compliant || 0));
        setText("achievablePlans", Number(summary.achievable || 0));
        setText("nonAchievablePlans", Number(summary.non_achievable || 0));
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

        state.departments = Object.keys(map)
            .map(function (id) {
                return map[id];
            })
            .sort(function (a, b) {
                return String(a.dept_name || "").localeCompare(String(b.dept_name || ""));
            });
    }

    function syncConcerns() {
        const deptId = $("departmentFilter")?.value || "";
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

        state.concerns = Object.keys(map)
            .map(function (id) {
                return map[id];
            })
            .sort(function (a, b) {
                return String(a.concern_name || "").localeCompare(String(b.concern_name || ""));
            });
    }

    function syncSubtypes() {
        const deptId = $("departmentFilter")?.value || "";
        const concernId = $("concernFilter")?.value || "";
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

        state.subtypes = Object.keys(map)
            .map(function (id) {
                return map[id];
            })
            .sort(function (a, b) {
                return String(a.label || "").localeCompare(String(b.label || ""));
            });
    }

    function renderDepartmentFilter() {
        const select = $("departmentFilter");

        if (!select) {
            return;
        }

        const current = select.value;
        select.innerHTML = '<option value="">All Departments</option>';

        state.departments.forEach(function (dept) {
            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${escapeHtml(dept.dept_id)}">${escapeHtml(dept.dept_name)}</option>`
            );
        });

        if (current) {
            select.value = current;
        }
    }

    function renderConcernFilter() {
        const select = $("concernFilter");

        if (!select) {
            return;
        }

        const current = select.value;
        select.innerHTML = '<option value="">All Areas</option>';

        state.concerns.forEach(function (concern) {
            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${escapeHtml(concern.concern_id)}">${escapeHtml(concern.concern_name)}</option>`
            );
        });

        if (current && state.concerns.some(function (row) { return Number(row.concern_id) === Number(current); })) {
            select.value = current;
        }
    }

    function renderSubtypeFilter() {
        const select = $("subtypeFilter");

        if (!select) {
            return;
        }

        const current = select.value;
        select.innerHTML = '<option value="">All Subtypes</option>';

        state.subtypes.forEach(function (subtype) {
            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${escapeHtml(subtype.c_subtype_id)}">${escapeHtml(subtype.label)}</option>`
            );
        });

        if (current && state.subtypes.some(function (row) { return Number(row.c_subtype_id) === Number(current); })) {
            select.value = current;
        }
    }

    function filteredPlans() {
        const deptId = $("departmentFilter")?.value || "";
        const concernId = $("concernFilter")?.value || "";
        const subtypeId = $("subtypeFilter")?.value || "";
        const type = $("typeFilter")?.value || "";

        return state.plans.filter(function (plan) {
            const concern = plan.concern || {};
            const subtype = plan.subtype || {};

            return (!deptId || Number(plan.dept_id) === Number(deptId)) &&
                (!concernId || Number(concern.concern_id) === Number(concernId)) &&
                (!subtypeId || Number(subtype.c_subtype_id) === Number(subtypeId)) &&
                (!type || plan.gap_type === type);
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
            return Boolean(plan.action_plan && plan.action_plan.has_saved_plan);
        });
    }

    function planInputId(plan, suffix) {
        return "ap_" + Number(plan.dept_id || 0) + "_" + Number(plan.checkpoint_id || 0) + "_" + suffix;
    }

    function renderLibrary(plan) {
        const rows = plan.action_plan?.facility_suggestions || plan.facility_suggestions || [];

        if (!rows.length) {
            return `<div class="sq-ap-empty">No facility suggestions saved for this checkpoint yet.</div>`;
        }

        return rows.map(function (item) {
            return `
                <div class="sq-ap-library-item">
                    <div class="sq-ap-library-meta">
                        <span>${escapeHtml(item.fac_name || "Facility")}</span>
                        <span>${escapeHtml(item.created_on || "")}</span>
                    </div>
                    ${escapeHtml(item.user_action_plan || item.action_plan || "")}
                </div>
            `;
        }).join("");
    }

    function renderProgress() {
        const total = state.filtered.length;
        const current = total ? state.currentIndex + 1 : 0;
        const percent = total ? Math.round((current / total) * 100) : 0;
        const dept = $("departmentFilter")?.selectedOptions?.[0]?.textContent || "All Departments";
        const concern = $("concernFilter")?.selectedOptions?.[0]?.textContent || "All Areas";
        const subtype = $("subtypeFilter")?.selectedOptions?.[0]?.textContent || "All Subtypes";

        setText("currentPosition", current + " / " + total);
        setText("currentScopeText", [dept, concern, subtype].join(" | "));

        const bar = $("actionPlanProgressBar");

        if (bar) {
            bar.style.width = percent + "%";
        }
    }

    function renderPlans(resetIndex = false) {
        const target = $("actionPlanList");

        if (!target) {
            return;
        }

        updateFiltered(resetIndex);
        renderProgress();

        if (!state.filtered.length) {
            target.innerHTML = `<div class="sq-ap-empty">No action plans found for selected filters.</div>`;
            return;
        }

        if (!state.editMode && isCurrentScopeCompleted()) {
            target.innerHTML = `
                <div class="sq-ap-completed">
                    <div>
                        <strong>You have completed the action plan.</strong>
                        <p>${state.filtered.length} checkpoint action plan${state.filtered.length === 1 ? "" : "s"} saved for this scope.</p>
                    </div>
                    <button type="button" class="sq-btn sq-btn-primary" id="btnEditCompletedActionPlans">
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
            const typeClass = plan.gap_type === "NON_COMPLIANT" ? "is-nc" : "is-pc";
            const systemPlan = action.system_action_plan || checkpoint.system_action_plan || "";
            const userPlan = action.user_action_plan || "";

        target.innerHTML = `
                <article class="sq-ap-item" data-sq-plan-card>
                    <div class="sq-ap-item-head">
                        <div class="sq-ap-title">
                            <strong>${escapeHtml(checkpoint.Checkpoint || checkpoint.Measurable_Element || ("Checkpoint " + plan.checkpoint_id))}</strong>
                            <span class="sq-ap-subtext">
                                ${escapeHtml(department.dept_name || ("Dept " + plan.dept_id))}
                                ${concern.concern_name || concern.concern_des ? " | " + escapeHtml(concern.concern_name || concern.concern_des) : ""}
                                ${subtype.Reference_No ? " | " + escapeHtml(subtype.Reference_No) : ""}
                            </span>
                        </div>
                        <div class="sq-ap-chips">
                            <span class="sq-ap-chip ${typeClass}">${escapeHtml(gapTypeText(plan.gap_type))}</span>
                            <span class="sq-ap-chip ${action.has_saved_plan ? "is-saved" : "is-pc"}">${action.has_saved_plan ? "Saved" : "Draft"}</span>
                        </div>
                    </div>

                    <div class="sq-ap-item-body">
                        <div class="sq-ap-section">
                            <div class="sq-ap-section-title">Pre Suggested Action Plan</div>
                            <div class="sq-ap-suggested">${escapeHtml(systemPlan || "No suggested action plan available.")}</div>

                            <div class="sq-ap-section-title">Your Action Plan</div>
                            <textarea
                                id="${planInputId(plan, "plan")}"
                                class="sq-form-control sq-ap-textarea"
                                placeholder="Enter or paste facility action plan">${escapeHtml(userPlan)}</textarea>

                            <div class="sq-ap-form-grid">
                                <input id="${planInputId(plan, "person")}" class="sq-form-control" placeholder="Responsible person" value="${escapeHtml(action.responsible_person || "")}">
                                <select id="${planInputId(plan, "achieve")}" class="sq-form-control">
                                    <option value="ACHIEVABLE" ${action.achievability !== "NON_ACHIEVABLE" ? "selected" : ""}>Achievable</option>
                                    <option value="NON_ACHIEVABLE" ${action.achievability === "NON_ACHIEVABLE" ? "selected" : ""}>Non Achievable</option>
                                </select>
                                <select id="${planInputId(plan, "priority")}" class="sq-form-control">
                                    <option value="LOW" ${action.priority === "LOW" ? "selected" : ""}>Low</option>
                                    <option value="MEDIUM" ${!action.priority || action.priority === "MEDIUM" ? "selected" : ""}>Medium</option>
                                    <option value="HIGH" ${action.priority === "HIGH" ? "selected" : ""}>High</option>
                                </select>
                                <input id="${planInputId(plan, "date")}" type="date" class="sq-form-control" value="${escapeHtml(action.target_date || "")}">
                            </div>

                            <div class="sq-ap-actions">
                                <div class="sq-ap-copy-tools">
                                    <button type="button" class="sq-btn sq-btn-light" data-sq-copy-system="${Number(plan.dept_id)}:${Number(plan.checkpoint_id)}">
                                        Copy Suggested
                                    </button>
                                </div>
                            </div>

                            <div class="sq-ap-subtext">
                                Score ${Number(response.score || 0).toFixed(2)} / 2
                                ${response.remarks ? " | Remarks: " + escapeHtml(response.remarks) : ""}
                            </div>
                        </div>

                        <aside class="sq-ap-section">
                            <div class="sq-ap-section-title">Facility Suggestions For Same Checkpoint</div>
                            <div class="sq-ap-library">${renderLibrary(plan)}</div>
                        </aside>
                    </div>

                    <div class="sq-ap-nav">
                        <button type="button" class="sq-btn sq-btn-light" id="btnPreviousActionPlan" ${state.currentIndex <= 0 ? "disabled" : ""}>
                            Back
                        </button>
                        <div class="sq-ap-nav-save">
                            <button type="button" class="sq-btn sq-btn-light" data-sq-save-plan="${Number(plan.dept_id)}:${Number(plan.checkpoint_id)}">
                                ${action.has_saved_plan ? "Update" : "Save"}
                            </button>
                            <button type="button" class="sq-btn sq-btn-primary" data-sq-save-next="${Number(plan.dept_id)}:${Number(plan.checkpoint_id)}" ${state.currentIndex >= state.filtered.length - 1 ? "disabled" : ""}>
                                Save & Next
                            </button>
                        </div>
                        <button type="button" class="sq-btn sq-btn-light" id="btnNextActionPlan" ${state.currentIndex >= state.filtered.length - 1 ? "disabled" : ""}>
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

    async function loadActionPlans(options = {}) {
        const assessmentId = Number(state.assessment?.assessment_id || getUrlAssessmentId() || 0);
        const keepCurrent = options.keepCurrent === true;
        const currentPlan = state.filtered[state.currentIndex] || null;
        const currentKey = currentPlan
            ? Number(currentPlan.dept_id) + ":" + Number(currentPlan.checkpoint_id)
            : "";

        if (!assessmentId) {
            throw new Error("assessment_id is required.");
        }

        const response = await apiGet(API.actionPlan, {
            assessment_id: assessmentId
        });

        if (response.status === "error" || response.success === false) {
            throw new Error(response.message || "Unable to load action plans.");
        }

        const data = response.data || {};
        state.assessment = data.assessment || state.assessment || {};
        state.facility = data.facility || {};
        state.summary = data.summary || {};
        state.plans = data.action_plans || [];

        syncDepartments();
        renderDepartmentFilter();
        syncConcerns();
        renderConcernFilter();
        syncSubtypes();
        renderSubtypeFilter();
        renderContext();
        renderSummary();
        updateFiltered(!keepCurrent);

        if (keepCurrent && currentKey) {
            const nextIndex = state.filtered.findIndex(function (plan) {
                return Number(plan.dept_id) + ":" + Number(plan.checkpoint_id) === currentKey;
            });

            if (nextIndex >= 0) {
                state.currentIndex = nextIndex;
            }
        }

        renderPlans(false);
    }

    function findPlan(deptId, checkpointId) {
        return state.plans.find(function (plan) {
            return Number(plan.dept_id) === Number(deptId) &&
                Number(plan.checkpoint_id) === Number(checkpointId);
        });
    }

    function copySystemPlan(deptId, checkpointId) {
        const plan = findPlan(deptId, checkpointId);
        const target = $(planInputId({ dept_id: deptId, checkpoint_id: checkpointId }, "plan"));
        const systemPlan = plan?.action_plan?.system_action_plan || plan?.checkpoint?.system_action_plan || "";

        if (target) {
            target.value = systemPlan;
            target.focus();
        }
    }

    async function savePlan(deptId, checkpointId, button, moveNext = false) {
        const plan = findPlan(deptId, checkpointId);

        if (!plan) {
            return;
        }

        const payload = {
            assessment_id: Number(plan.assessment_id || state.assessment?.assessment_id || 0),
            dept_id: Number(deptId),
            checkpoint_id: Number(checkpointId),
            system_action_plan: plan.action_plan?.system_action_plan || plan.checkpoint?.system_action_plan || "",
            user_action_plan: $(planInputId(plan, "plan"))?.value.trim() || "",
            responsible_person: $(planInputId(plan, "person"))?.value.trim() || "",
            achievability: $(planInputId(plan, "achieve"))?.value || "ACHIEVABLE",
            priority: $(planInputId(plan, "priority"))?.value || "MEDIUM",
            target_date: $(planInputId(plan, "date"))?.value || "",
            status: "OPEN"
        };

        if (!payload.user_action_plan) {
            notify("warning", "Please enter an action plan.");
            return;
        }

        if (payload.achievability === "ACHIEVABLE" && (!payload.responsible_person || !payload.target_date)) {
            notify("warning", "Responsible person and target date are required.");
            return;
        }

        const originalText = button?.textContent;

        if (button) {
            button.disabled = true;
            button.textContent = "Saving...";
        }

        try {
            const response = await apiPost(API.save, payload);

            if (response.status === "error" || response.success === false) {
                throw new Error(response.message || "Unable to save action plan.");
            }

            notify("success", response.message || "Action plan saved.");
            await loadActionPlans({ keepCurrent: true });

            if (moveNext && state.currentIndex < state.filtered.length - 1) {
                state.currentIndex += 1;
                renderPlans(false);
            }

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to save action plan.");

            if (button) {
                button.disabled = false;
                button.textContent = originalText || "Save Action Plan";
            }
        }
    }

    function bindEvents() {
        $("departmentFilter")?.addEventListener("change", function () {
            if ($("concernFilter")) {
                $("concernFilter").value = "";
            }
            if ($("subtypeFilter")) {
                $("subtypeFilter").value = "";
            }
            syncConcerns();
            renderConcernFilter();
            syncSubtypes();
            renderSubtypeFilter();
            renderPlans(true);
        });

        $("concernFilter")?.addEventListener("change", function () {
            if ($("subtypeFilter")) {
                $("subtypeFilter").value = "";
            }
            syncSubtypes();
            renderSubtypeFilter();
            renderPlans(true);
        });

        $("subtypeFilter")?.addEventListener("change", function () {
            renderPlans(true);
        });

        $("typeFilter")?.addEventListener("change", function () {
            renderPlans(true);
        });

        $("btnRefreshActionPlans")?.addEventListener("click", loadActionPlans);

        document.addEventListener("click", function (event) {
            const copy = event.target.closest("[data-sq-copy-system]");
            const save = event.target.closest("[data-sq-save-plan]");
            const saveNext = event.target.closest("[data-sq-save-next]");

            if (copy) {
                const parts = String(copy.dataset.sqCopySystem || "").split(":");
                copySystemPlan(parts[0], parts[1]);
            }

            if (save) {
                const parts = String(save.dataset.sqSavePlan || "").split(":");
                savePlan(parts[0], parts[1], save);
            }

            if (saveNext) {
                const parts = String(saveNext.dataset.sqSaveNext || "").split(":");
                savePlan(parts[0], parts[1], saveNext, true);
            }

            if (event.target.closest("#btnPreviousActionPlan")) {
                state.currentIndex = Math.max(0, state.currentIndex - 1);
                renderPlans(false);
            }

            if (event.target.closest("#btnNextActionPlan")) {
                state.currentIndex = Math.min(state.filtered.length - 1, state.currentIndex + 1);
                renderPlans(false);
            }

            if (event.target.closest("#btnEditCompletedActionPlans")) {
                state.editMode = true;
                state.currentIndex = 0;
                renderPlans(false);
            }
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
            await loadActionPlans();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to initialize action plan.");

            const target = $("actionPlanList");
            if (target) {
                target.innerHTML = `<div class="sq-ap-empty">${escapeHtml(error.message || "Unable to initialize action plan.")}</div>`;
            }
        } finally {
            state.isLoading = false;
        }
    }

    SQ.actionPlan = {
        init,
        state
    };

})(window, document);
