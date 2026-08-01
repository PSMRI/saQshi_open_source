/*!
 * ==========================================================
 * SaQshi Open Source
 * State Assessor Management
 * assessors.js
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */
(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    const state = { pager: null, selectedAssessor: null, facilityTimer: null, selectedFacilities: new Map(), searchFacilityRows: new Map() };

    function domainLabel(key, fallback) {
        return SQ.deployment && typeof SQ.deployment.label === "function"
            ? SQ.deployment.label(key, fallback)
            : fallback;
    }

    function esc(value) {
        return String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function val(id) {
        return document.getElementById(id)?.value || "";
    }

    function setVal(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value ?? "";
    }

    function assessorSearchValue() {
        return val("assessorSearch") || val("assessorTableSearch");
    }

    function badge(value) {
        return Number(value) === 1 || String(value).toUpperCase() === "ACTIVE"
            ? `<span class="sq-assessor-status is-active">Active</span>`
            : `<span class="sq-assessor-status is-inactive">Inactive</span>`;
    }

    function renderSelectedFacilities() {
        const target = document.getElementById("selectedFacilityRows");
        if (!target) return;
        const rows = Array.from(state.selectedFacilities.values());
        target.innerHTML = rows.length ? `
            <h4>Selected ${esc(domainLabel("facilities", "Facilities"))} (${rows.length})</h4>
            <table class="sq-state-table"><thead><tr><th>${esc(domainLabel("facility", "Facility"))}</th><th>${esc(domainLabel("facility_code", "NIN"))}</th><th>Remove</th></tr></thead>
            <tbody>${rows.map(row => `<tr><td>${esc(row.fac_name)}</td><td>${esc(row.NIN_no || "-")}</td><td><label><input type="checkbox" data-selected-facility-select value="${esc(row.fac_id)}" checked> Selected</label></td></tr>`).join("")}</tbody></table>` : "";
    }

    function formPayload() {
        return {
            assessor_id: val("assessorId"),
            assessor_code: val("assessorCode"),
            assessor_name: val("assessorName"),
            user_id: val("assessorUserId"),
            designation: val("assessorDesignation"),
            mobile_no: val("assessorMobile"),
            mail_id: val("assessorEmail"),
            is_active: Number(val("assessorStatus") || 1)
        };
    }

    function resetForm() {
        ["assessorId", "assessorCode", "assessorName", "assessorUserId", "assessorDesignation", "assessorMobile", "assessorEmail"].forEach(id => setVal(id, ""));
        setVal("assessorStatus", "1");
    }

    function selectAssessor(row) {
        state.selectedAssessor = row;
        document.getElementById("mappingAssessorName").textContent = `${row.assessor_name || row.assessor_code} (${row.assessor_code})`;
        loadMappings();
    }

    function editAssessor(row) {
        setVal("assessorId", row.assessor_id);
        setVal("assessorCode", row.assessor_code);
        setVal("assessorName", row.assessor_name);
        setVal("assessorUserId", row.user_id || "");
        setVal("assessorDesignation", row.designation || "");
        setVal("assessorMobile", row.mobile_no || "");
        setVal("assessorEmail", row.mail_id || "");
        setVal("assessorStatus", row.is_active);
        selectAssessor(row);
    }

    function renderAssessors(rows) {
        document.getElementById("assessorRows").innerHTML = rows.length ? `
            <table class="sq-state-table">
                <thead><tr><th>Assessor</th><th>Login User</th><th>Contact</th><th>Mapped Facilities</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>${rows.map(row => `
                    <tr>
                        <td><strong>${esc(row.assessor_name)}</strong><br><small>${esc(row.assessor_code)} ${row.designation ? "| " + esc(row.designation) : ""}</small></td>
                        <td>${esc(row.u_name || "-")}<br><small>${row.user_id ? "User ID " + esc(row.user_id) : "Not linked"}</small></td>
                        <td>${esc(row.mobile_no || "-")}<br><small>${esc(row.mail_id || "")}</small></td>
                        <td>${esc(row.mapped_facilities || 0)}</td>
                        <td>${badge(row.is_active)}</td>
                        <td>
                            <button class="sq-btn sq-btn-primary" type="button" data-assessor-select='${esc(JSON.stringify(row))}'>Select</button>
                            <button class="sq-btn sq-btn-light" type="button" data-assessor-edit='${esc(JSON.stringify(row))}'>Edit</button>
                        </td>
                    </tr>`).join("")}</tbody>
            </table>` : `<div class="sq-state-empty">${assessorSearchValue().trim()
                ? `No ${esc(domainLabel("assessor", "Assessor").toLowerCase())} is mapped to the searched ${esc(domainLabel("facility", "facility").toLowerCase())} or matches this search.`
                : `No ${esc(domainLabel("assessor", "Assessor").toLowerCase())} available.`}</div>`;
    }

    async function loadAssessors() {
        const response = await SQ.api.get("/assessor/v1/list.php", state.pager.params({ search: assessorSearchValue() }), { loader: false, showError: false });
        renderAssessors(response.data?.rows || []);
        state.pager.set(response.data?.pagination || {}).render("assessorPager", "Showing");
    }

    async function saveAssessor(event) {
        event.preventDefault();
        try {
            const response = await SQ.api.post("/assessor/v1/save.php", formPayload(), { loader: true, showError: false });
            if (SQ.notification) SQ.notification.success("Assessor saved.");
            resetForm();
            await loadAssessors();
            selectAssessor(response.data || {});
            if (response.data?.login_user_created) {
                if (SQ.notification?.success) {
                    SQ.notification.success("Login user created. Temporary password delivery was sent to configured channels.");
                }
                if (response.data?.temporary_password) {
                    window.alert(
                        `${domainLabel("assessor", "Assessor")} login created.\n\n`
                        + `Username: ${response.data.assessor_code}\n`
                        + `Temporary password: ${response.data.temporary_password}\n\n`
                        + "Copy it now. The user must change this password on first login."
                    );
                }
            }
        } catch (error) {
            if (SQ.notification) SQ.notification.error(error.message || "Unable to save assessor.");
        }
    }

    async function searchFacilities() {
        const keyword = val("facilitySearch");
        if (keyword.trim().length < 2) {
            document.getElementById("facilitySearchRows").innerHTML = "";
            return;
        }

        const response = await SQ.api.get("/assessor/v1/facility_search.php", { search: keyword }, { loader: false, showError: false });
        const rows = response.data?.rows || [];
        state.searchFacilityRows = new Map(rows.map(row => [Number(row.fac_id), row]));
        document.getElementById("facilitySearchRows").innerHTML = rows.length ? `
            <div class="sq-assessor-bulk-actions">
                <span>${state.selectedFacilities.size} selected. Search for more, then assign them together.</span>
                <button class="sq-btn sq-btn-primary" type="button" data-map-selected>Assign selected (${state.selectedFacilities.size})</button>
            </div>${rows.map(row => `
            <div class="sq-assessor-mini-row">
                <label><input type="checkbox" data-map-facility-select value="${esc(row.fac_id)}" aria-label="Select ${esc(row.fac_name)}" ${state.selectedFacilities.has(Number(row.fac_id)) ? "checked" : ""}> Select</label>
                <div><strong>${esc(row.fac_name)}</strong><small>${esc(domainLabel("facility_code", "NIN"))} ${esc(row.NIN_no || "-")} | ${esc(row.Dist_Name || "-")} / ${esc(row.Block_Name || "-")}</small></div>
                <button class="sq-btn sq-btn-primary" type="button" data-map-facility="${esc(row.fac_id)}">Assign</button>
            </div>`).join("")}` : `<div class="sq-state-empty">No ${esc(domainLabel("facility", "facility").toLowerCase())} found.</div>`;
    }

    async function loadMappings() {
        const target = document.getElementById("mappingRows");
        if (!state.selectedAssessor?.assessor_id) {
            target.innerHTML = `<div class="sq-state-empty">Select an assessor to view mapped facilities.</div>`;
            return;
        }

        const response = await SQ.api.get("/assessor/v1/mapping_list.php", { assessor_id: state.selectedAssessor.assessor_id }, { loader: false, showError: false });
        const rows = response.data?.rows || [];
        target.innerHTML = rows.length ? rows.map(row => `
            <div class="sq-assessor-mini-row">
                <div><strong>${esc(row.fac_name || domainLabel("facility", "Facility") + " " + row.fac_id)}</strong><small>${esc(domainLabel("facility_code", "NIN"))} ${esc(row.fac_nin || "-")} | ${esc(row.Dist_Name || "-")} / ${esc(row.Block_Name || "-")}${row.assigned_from ? ` | From ${esc(row.assigned_from)}` : ""}${row.assigned_to ? ` to ${esc(row.assigned_to)}` : ""}</small></div>
                ${badge(row.assignment_status)}
                ${String(row.assignment_status).toUpperCase() === "ACTIVE" ? `<button class="sq-btn sq-btn-light" type="button" data-end-mapping="${esc(row.fac_id)}">End Assignment</button>` : ""}
            </div>`).join("") : `<div class="sq-state-empty">No ${esc(domainLabel("facilities", "facilities").toLowerCase())} mapped yet.</div>`;
    }

    async function endMapping(facId) {
        if (!state.selectedAssessor?.assessor_id) return;
        const unit = domainLabel("facility", "Facility");
        if (!window.confirm(`End this ${unit} assignment today? It can then be assigned to another ${domainLabel("assessor", "Assessor")}.`)) return;

        try {
            await SQ.api.post("/assessor/v1/mapping_save.php", {
                assessor_id: state.selectedAssessor.assessor_id,
                fac_id: Number(facId),
                assignment_status: "INACTIVE",
                assigned_to: new Date().toISOString().slice(0, 10)
            }, { loader: true, showError: false });
            if (SQ.notification) SQ.notification.success(`${unit} assignment ended. You can now assign it to another ${domainLabel("assessor", "Assessor")}.`);
            await loadMappings();
            await loadAssessors();
        } catch (error) {
            if (SQ.notification) SQ.notification.error(error.message || "Unable to end assignment.");
        }
    }

    async function mapFacility(facId) {
        if (!state.selectedAssessor?.assessor_id) {
            if (SQ.notification) SQ.notification.warning("Select an assessor first.");
            return;
        }

        await SQ.api.post("/assessor/v1/mapping_save.php", {
            assessor_id: state.selectedAssessor.assessor_id,
            fac_id: Number(facId),
            assignment_status: "ACTIVE"
        }, { loader: true, showError: false });
        if (SQ.notification) SQ.notification.success(`${domainLabel("facility", "Facility")} assigned.`);
        await loadMappings();
    }

    async function mapSelectedFacilities() {
        if (!state.selectedAssessor?.assessor_id) {
            if (SQ.notification) SQ.notification.warning("Select an assessor first.");
            return;
        }
        const facilityIds = Array.from(state.selectedFacilities.keys());
        if (!facilityIds.length) {
            if (SQ.notification) SQ.notification.warning(`Select at least one ${domainLabel("facility", "facility").toLowerCase()}.`);
            return;
        }
        const response = await SQ.api.post("/assessor/v1/mapping_save.php", {
            assessor_id: state.selectedAssessor.assessor_id,
            fac_ids: facilityIds,
            assignment_status: "ACTIVE"
        }, { loader: true, showError: false });
        if (SQ.notification) SQ.notification.success(`${response.data?.mapped_count || facilityIds.length} ${domainLabel("facilities", "Facilities").toLowerCase()} assigned.`);
        state.selectedFacilities.clear();
        document.getElementById("facilitySearchRows").innerHTML = "";
        renderSelectedFacilities();
        await loadMappings();
    }

    function bind() {
        document.getElementById("assessorForm")?.addEventListener("submit", saveAssessor);
        document.getElementById("assessorReset")?.addEventListener("click", resetForm);
        document.getElementById("assessorRefresh")?.addEventListener("click", function () {
            state.pager.reset();
            loadAssessors();
        });
        ["assessorSearch", "assessorTableSearch"].forEach(function (id) {
            document.getElementById(id)?.addEventListener("input", function (event) {
                const search = event.target.value;
                ["assessorSearch", "assessorTableSearch"].forEach(function (otherId) {
                    if (otherId !== id) setVal(otherId, search);
                });
                clearTimeout(state.searchTimer);
                state.searchTimer = setTimeout(function () {
                    state.pager.reset();
                    loadAssessors();
                }, 300);
            });
        });
        document.getElementById("facilitySearch")?.addEventListener("input", function () {
            clearTimeout(state.facilityTimer);
            state.facilityTimer = setTimeout(searchFacilities, 300);
        });
        document.getElementById("assessorRows")?.addEventListener("click", function (event) {
            const select = event.target.closest("[data-assessor-select]");
            const edit = event.target.closest("[data-assessor-edit]");
            if (select) selectAssessor(JSON.parse(select.getAttribute("data-assessor-select")));
            if (edit) editAssessor(JSON.parse(edit.getAttribute("data-assessor-edit")));
        });
        document.getElementById("facilitySearchRows")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-map-facility]");
            const bulkButton = event.target.closest("[data-map-selected]");
            if (button) mapFacility(button.getAttribute("data-map-facility"));
            if (bulkButton) mapSelectedFacilities();
        });
        document.getElementById("mappingRows")?.addEventListener("click", function (event) {
            const endButton = event.target.closest("[data-end-mapping]");
            if (endButton) endMapping(endButton.getAttribute("data-end-mapping"));
        });
        document.getElementById("facilitySearchRows")?.addEventListener("change", function (event) {
            const checkbox = event.target.closest("[data-map-facility-select]");
            if (!checkbox) return;
            const facilityId = Number(checkbox.value);
            if (!Number.isFinite(facilityId)) return;
            if (checkbox.checked) {
                const facility = state.searchFacilityRows.get(facilityId);
                if (facility) state.selectedFacilities.set(facilityId, facility);
            } else state.selectedFacilities.delete(facilityId);
            renderSelectedFacilities();
            searchFacilities();
        });
        document.getElementById("selectedFacilityRows")?.addEventListener("change", function (event) {
            const checkbox = event.target.closest("[data-selected-facility-select]");
            if (!checkbox) return;
            state.selectedFacilities.delete(Number(checkbox.value));
            renderSelectedFacilities();
            searchFacilities();
        });
    }

    async function init() {
        if (SQ.deployment && typeof SQ.deployment.load === "function") {
            await SQ.deployment.load();
            SQ.deployment.applyLabels(document);
        }
        state.pager = SQ.pagination.create({ page: 1, perPage: 25, onChange: loadAssessors });
        bind();
        await loadAssessors();
        await loadMappings();
    }

    SQ.stateAssessors = { init };
})(window, document);
