/*!
 * ==========================================================
 * SQ Create Assessment Page v1.0
 * ----------------------------------------------------------
 * Project  : SaQshi Open Source
 * Module   : Assessment
 * Page     : Create Assessment
 * File     : create.js
 * License  : GPL-3.0
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        me: "/auth/v1/me.php",
        activeAssessment: "/assessment/v1/active_assessment.php",
        createAssessment: "/assessment/v1/create_assessment.php",
        cancelAssessment: "/assessment/v1/cancel_assessment.php"
    };

    const state = {
        user: null,
        facility: null,
        activeAssessment: null,
        assessmentNameEdited: false
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

    async function apiGet(url) {
        if (SQ.api && typeof SQ.api.get === "function") {
            return SQ.api.get(url, {}, { loader: false });
        }

        const response = await fetch(url, {
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });

        return response.json();
    }

    async function apiPost(url, payload) {
        if (SQ.api && typeof SQ.api.post === "function") {
            return SQ.api.post(url, payload, { loader: false });
        }

        const response = await fetch(url, {
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

    function notify(type, message) {
        if (SQ.notification && typeof SQ.notification[type] === "function") {
            SQ.notification[type](message);
            return;
        }

        if (SQ.toast) {
            SQ.toast(message, type);
        }
    }

    function setLoading(isLoading) {
        const btn = $("btnCreateAssessment");

        if (!btn) {
            return;
        }

        btn.disabled = isLoading;
        btn.innerHTML = isLoading
            ? `<span class="sq-btn-spinner"></span> Creating...`
            : `<i class="bi bi-plus-circle"></i> Create Assessment`;
    }

    function setCancelLoading(isLoading) {
        const btn = $("btnCancelActiveAssessment");

        if (!btn) {
            return;
        }

        btn.disabled = isLoading;
        btn.innerHTML = isLoading
            ? `<span class="sq-btn-spinner"></span> Cancelling...`
            : `Cancel Current Assessment`;
    }

    function setDefaultDates() {
        const today = new Date();
        const after30Days = new Date();

        after30Days.setDate(today.getDate() + 30);

        const start = $("start_date");
        const end = $("end_date");

        if (start && !start.value) {
            start.value = today.toISOString().slice(0, 10);
        }

        if (end && !end.value) {
            end.value = after30Days.toISOString().slice(0, 10);
        }
    }

    function frameworkLabel(value) {
        const framework = String(value || "saqshi-nqas").toLowerCase();

        if (framework.includes("musqan")) {
            return "MusQan";
        }

        if (framework.includes("laqshya")) {
            return "LaQshya";
        }

        return "NQAS";
    }

    function monthYear(value) {
        const date = value ? new Date(value) : new Date();

        if (Number.isNaN(date.getTime())) {
            return new Date().toLocaleDateString("en-IN", {
                month: "long",
                year: "numeric"
            });
        }

        return date.toLocaleDateString("en-IN", {
            month: "long",
            year: "numeric"
        });
    }

    function generateAssessmentName() {
        const user = state.user || {};
        const facility = state.facility || user.facility || {};
        const facilityName =
            facility.fac_name ||
            facility.facility_name ||
            user.facility_name ||
            "Facility";

        const framework = frameworkLabel($("framework_code")?.value);
        const period = monthYear($("start_date")?.value);

        return `${facilityName} - ${framework} - ${period}`;
    }

    function autoFillAssessmentName(force) {
        const input = $("assessment_name");

        if (!input) {
            return;
        }

        if (!force && state.assessmentNameEdited) {
            return;
        }

        input.value = generateAssessmentName();
    }

    function renderFacility() {
        const target = $("facilityInfoCard");

        if (!target) {
            return;
        }

        const user = state.user || {};
        const facility = state.facility || user.facility || {};

        target.innerHTML = `
            <div class="sq-info-item">
                <span class="sq-info-label">User</span>
                <span class="sq-info-value">${escapeHtml(user.name || user.u_name || "User")}</span>
            </div>

            <div class="sq-info-item">
                <span class="sq-info-label">Role</span>
                <span class="sq-info-value">${escapeHtml(user.role || user.role_name || "Role")}</span>
            </div>

            <div class="sq-info-item">
                <span class="sq-info-label">Facility</span>
                <span class="sq-info-value">${escapeHtml(facility.fac_name || facility.facility_name || user.facility_name || "-")}</span>
            </div>

            <div class="sq-info-item">
                <span class="sq-info-label">Facility ID</span>
                <span class="sq-info-value">${escapeHtml(facility.fac_id || user.fac_id_fk || "-")}</span>
            </div>
        `;
    }

    function renderActiveAssessment() {
        const target = $("activeAssessmentStatus");

        if (!target) {
            return;
        }

        const assessment = state.activeAssessment;

        if (!assessment || !assessment.assessment_id) {
            target.innerHTML = `
                <div class="sq-alert sq-alert-success">
                    <div class="sq-alert-content">
                        <div class="sq-alert-title">
                            Assessment can be created
                        </div>
                        <div class="sq-alert-text">
                            No ACTIVE assessment found for this facility.
                        </div>
                    </div>
                </div>
            `;

            const btn = $("btnCreateAssessment");
            if (btn) {
                btn.disabled = false;
            }

            return;
        }

        target.innerHTML = `
            <div class="sq-alert sq-alert-warning">
                <div class="sq-alert-content">
                    <div class="sq-alert-title">
                        ACTIVE assessment already exists
                    </div>
                    <div class="sq-alert-text">
                        You cannot create another assessment until the current assessment is completed or cancelled. If required, cancel this assessment and then create a new one.
                    </div>
                </div>
            </div>

            <div class="sq-info-list sq-mt-3">
                <div class="sq-info-item">
                    <span class="sq-info-label">Assessment</span>
                    <span class="sq-info-value">${escapeHtml(assessment.assessment_name || "-")}</span>
                </div>

                <div class="sq-info-item">
                    <span class="sq-info-label">Framework</span>
                    <span class="sq-info-value">${escapeHtml(assessment.framework_code || "-")}</span>
                </div>

                <div class="sq-info-item">
                    <span class="sq-info-label">Status</span>
                    <span class="sq-info-value sq-status-active">${escapeHtml(assessment.status || "ACTIVE")}</span>
                </div>

                <div class="sq-info-item">
                    <span class="sq-info-label">Start Date</span>
                    <span class="sq-info-value">${escapeHtml(assessment.start_date || "-")}</span>
                </div>

                <div class="sq-info-item">
                    <span class="sq-info-label">End Date</span>
                    <span class="sq-info-value">${escapeHtml(assessment.end_date || "-")}</span>
                </div>
            </div>

            <div class="sq-danger-zone sq-mt-4">
                <div>
                    <div class="sq-danger-title">Cancel current assessment</div>
                    <div class="sq-danger-text">This will close the active assessment for this facility and allow a new assessment to be created.</div>
                </div>
                <button type="button" class="sq-btn sq-btn-danger" id="btnCancelActiveAssessment">
                    Cancel Current Assessment
                </button>
            </div>
        `;

        const btn = $("btnCreateAssessment");
        if (btn) {
            btn.disabled = true;
        }
    }

    async function loadUser() {
        try {
            const response = await apiGet(API.me);

            state.user =
                response.data?.user ||
                response.user ||
                response.data ||
                null;

            state.facility =
                response.data?.facility ||
                state.user?.facility ||
                null;

        } catch (error) {
            console.error(error);
        }

        renderFacility();
        autoFillAssessmentName();
    }

    async function loadActiveAssessment() {
        try {
            const response = await apiGet(API.activeAssessment);

            state.activeAssessment =
                response.data?.assessment ||
                response.assessment ||
                response.data ||
                null;

        } catch (error) {
            state.activeAssessment = null;
        }

        renderActiveAssessment();
    }

    function validateForm(form) {
        const data = new FormData(form);

        const assessmentName = String(data.get("assessment_name") || generateAssessmentName()).trim();
        const frameworkCode = String(data.get("framework_code") || "").trim();
        const startDate = String(data.get("start_date") || "").trim();
        const endDate = String(data.get("end_date") || "").trim();

        if (!assessmentName) {
            notify("warning", "Assessment name could not be generated.");
            return false;
        }

        if (!frameworkCode) {
            notify("warning", "Please select framework.");
            return false;
        }

        if (!startDate || !endDate) {
            notify("warning", "Please select start date and end date.");
            return false;
        }

        if (new Date(endDate) < new Date(startDate)) {
            notify("warning", "End date cannot be before start date.");
            return false;
        }

        return true;
    }

    async function handleSubmit(event) {
        event.preventDefault();

        const form = $("assessmentCreateForm");

        if (!form) {
            return;
        }

        if (state.activeAssessment && state.activeAssessment.assessment_id) {
            notify("warning", "An ACTIVE assessment already exists.");
            return;
        }

        if (!validateForm(form)) {
            return;
        }

        autoFillAssessmentName();

        const data = new FormData(form);

        const payload = {
            assessment_name: String(data.get("assessment_name") || generateAssessmentName()).trim(),
            framework_code: String(data.get("framework_code") || "saqshi-nqas").trim(),
            start_date: String(data.get("start_date") || "").trim(),
            end_date: String(data.get("end_date") || "").trim(),
            remarks: String(data.get("remarks") || "").trim()
        };

        try {
            setLoading(true);

            if (SQ.loader && typeof SQ.loader.show === "function") {
                SQ.loader.show("Creating assessment...");
            }

            const response = await apiPost(API.createAssessment, payload);

            if (response.status === "error" || response.success === false) {
                throw new Error(response.message || "Unable to create assessment");
            }

            if (response.data?.created === false) {
                state.activeAssessment = response.data.assessment || null;
                renderActiveAssessment();
                notify("warning", response.message || "Active assessment already exists.");
                return;
            }

            notify("success", response.message || "Assessment created successfully.");

            const assessment =
                response.data?.assessment ||
                response.assessment ||
                response.data ||
                null;

            if (assessment && assessment.assessment_id) {
                sessionStorage.setItem("sq_active_assessment_id", assessment.assessment_id);
            }

           setTimeout(function () {
    if (SQ.router && typeof SQ.router.navigate === "function") {
        SQ.router.navigate("assessment/departments");
    } else {
        window.location.href = "/ui/dashboard.html";
    }
}, 700);

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to create assessment.");
        } finally {
            setLoading(false);

            if (SQ.loader && typeof SQ.loader.hide === "function") {
                SQ.loader.hide();
            }
        }
    }

    async function handleCancelActiveAssessment() {
        const assessment = state.activeAssessment;

        if (!assessment || !assessment.assessment_id) {
            notify("warning", "No active assessment found.");
            return;
        }

        const confirmed = window.confirm(
            "Cancel current active assessment? After cancellation you can create a new assessment."
        );

        if (!confirmed) {
            return;
        }

        try {
            setCancelLoading(true);

            if (SQ.loader && typeof SQ.loader.show === "function") {
                SQ.loader.show("Cancelling assessment...");
            }

            const response = await apiPost(API.cancelAssessment, {
                assessment_id: assessment.assessment_id
            });

            if (response.status === "error" || response.success === false) {
                throw new Error(response.message || "Unable to cancel assessment");
            }

            sessionStorage.removeItem("sq_active_assessment_id");
            state.activeAssessment = null;
            notify("success", response.message || "Assessment cancelled successfully.");
            await loadActiveAssessment();

        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to cancel assessment.");
        } finally {
            setCancelLoading(false);

            if (SQ.loader && typeof SQ.loader.hide === "function") {
                SQ.loader.hide();
            }
        }
    }

    function bindEvents() {
        const form = $("assessmentCreateForm");

        if (form) {
            form.addEventListener("submit", handleSubmit);
        }

        const cancel = $("btnCancelCreateAssessment");

        if (cancel) {
            cancel.addEventListener("click", function () {
                window.location.href = "/ui/dashboard.html";
            });
        }

        const activeStatus = $("activeAssessmentStatus");

        if (activeStatus) {
            activeStatus.addEventListener("click", function (event) {
                const cancelActive = event.target.closest("#btnCancelActiveAssessment");

                if (cancelActive) {
                    handleCancelActiveAssessment();
                }
            });
        }

        const assessmentName = $("assessment_name");

        if (assessmentName) {
            assessmentName.addEventListener("input", function () {
                state.assessmentNameEdited = String(assessmentName.value || "").trim() !== "";
            });
        }

        const framework = $("framework_code");

        if (framework) {
            framework.addEventListener("change", function () {
                autoFillAssessmentName();
            });
        }

        const startDate = $("start_date");

        if (startDate) {
            startDate.addEventListener("change", function () {
                autoFillAssessmentName();
            });
        }
    }

    async function init() {
        setDefaultDates();
        autoFillAssessmentName(true);
        bindEvents();

        try {
            await loadUser();
            await loadActiveAssessment();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load assessment page.");
        }
    }

   SQ.assessmentCreate = {
    init,
    state
};
})(window, document);
