/*!
 * ==========================================================
 * SaQshi Open Source
 * State User Administration
 * users.js
 * Version 1.2.0 | Updated 2026-07-13
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    const state = { pager: null, rows: [], canEditProfiles: false, canCreateUsers: false, canResetPasswords: false, editingUserId: 0, scopeOptions: null };

    function downloadCsv(filename, headers, rows) {
        const quote = value => `"${String(value ?? "").replace(/"/g, '""')}"`;
        const csv = [headers, ...rows].map(row => row.map(quote).join(",")).join("\r\n");
        const url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
        const link = document.createElement("a");
        link.href = url; link.download = filename; link.click();
        URL.revokeObjectURL(url);
    }

    function domainLabel(key, fallback) {
        return SQ.deployment && typeof SQ.deployment.label === "function"
            ? SQ.deployment.label(key, fallback)
            : fallback;
    }

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function queryParams() {
        return state.pager.params({
            search: document.getElementById("stateUsersSearch")?.value || ""
        });
    }

    function statusBadge(isActive) {
        return Number(isActive) === 1
            ? `<span class="sq-state-badge sq-state-latest">Active</span>`
            : `<span class="sq-state-badge sq-state-danger">Inactive</span>`;
    }

    function actionButton(row) {
        const active = Number(row.is_active) === 1;
        const stateAdmin = Number(row.role_id_fk) === 9 || String(row.role_name || "").toLowerCase() === "state admin";
        return `
            ${state.canResetPasswords ? `<button class="sq-btn sq-btn-light" type="button" data-user-password-reset="${esc(row.u_id)}">Reset Password</button>` : ""}
            ${state.canEditProfiles ? `<button class="sq-btn sq-btn-light" type="button" data-user-edit="${esc(row.u_id)}">Edit Profile</button>` : ""}
            ${active && stateAdmin ? "" : `<button class="sq-btn ${active ? "sq-btn-light" : "sq-btn-primary"}" type="button"
                data-user-status="${esc(row.u_id)}"
                data-next-status="${active ? "0" : "1"}">
                ${active ? "Deactivate" : "Activate"}
            </button>`}
        `;
    }

    function renderRows(rows) {
        document.getElementById("stateUserRows").innerHTML = rows.length
            ? `<table class="sq-state-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>${esc(domainLabel("facility", "Facility"))}</th>
                        <th>District</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>${rows.map(row => `
                    <tr>
                        <td><strong>${esc(row.full_name || row.u_name)}</strong><br><small>${esc(row.u_name)} · ID ${esc(row.u_id)}</small></td>
                        <td>${esc(row.role_name || row.role_id_fk)}</td>
                        <td>${esc(row.fac_name || "-")}</td>
                        <td>${esc(row.district || "-")}</td>
                        <td>${statusBadge(row.is_active)}</td>
                        <td>${actionButton(row)}</td>
                    </tr>
                `).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No users available.</div>`;
    }

    async function load() {
        const response = await SQ.api.get("/state/v1/users.php", queryParams(), {
            loader: false,
            showError: false
        });
        renderRows(response.data?.rows || []);
        state.pager.set(response.data?.pagination || {}).render("stateUsersPager", "Showing");
        state.rows = response.data?.rows || [];
    }

    async function updateStatus(userId, nextStatus) {
        const activate = Number(nextStatus) === 1;
        const ok = window.confirm(`${activate ? "Activate" : "Deactivate"} this user?`);
        if (!ok) return;

        try {
            await SQ.api.post("/state/v1/user_status.php", {
                u_id: Number(userId),
                is_active: activate ? 1 : 0
            }, {
                loader: true,
                showError: false
            });

            if (SQ.notification) {
                SQ.notification.success(`User ${activate ? "activated" : "deactivated"}.`);
            }
            await load();
        } catch (error) {
            if (SQ.notification) {
                SQ.notification.error(error.message || "Unable to update user status.");
            }
        }
    }

    function ensureProfileEditor() {
        if (document.getElementById("stateUserProfileModal")) return;
        document.querySelector(".sq-state-page")?.insertAdjacentHTML("beforeend", `
            <div class="sq-state-modal" id="stateUserProfileModal" hidden>
                <div class="sq-state-modal-panel sq-user-profile-panel" role="dialog" aria-modal="true" aria-labelledby="stateUserProfileTitle">
                    <div class="sq-card-header"><div><h3 id="stateUserProfileTitle">Edit User Profile</h3><p>Update the actual identity and contact information.</p></div><button class="sq-btn sq-btn-light" type="button" data-user-edit-close>Close</button></div>
                    <form class="sq-card-body sq-state-cert-form" id="stateUserProfileForm">
                        <div><label class="sq-form-label" for="editUserFirstName">First Name *</label><input class="sq-form-control" id="editUserFirstName" required maxlength="100"></div>
                        <div><label class="sq-form-label" for="editUserMiddleName">Middle Name</label><input class="sq-form-control" id="editUserMiddleName" maxlength="100"></div>
                        <div><label class="sq-form-label" for="editUserLastName">Last Name</label><input class="sq-form-control" id="editUserLastName" maxlength="100"></div>
                        <div><label class="sq-form-label" for="editUserUsername">Username *</label><input class="sq-form-control" id="editUserUsername" required minlength="3" maxlength="100"></div>
                        <div><label class="sq-form-label" for="editUserEmail">Email</label><input class="sq-form-control" id="editUserEmail" type="email" maxlength="190"></div>
                        <div><label class="sq-form-label" for="editUserMobile">Mobile Number</label><input class="sq-form-control" id="editUserMobile" maxlength="20"></div>
                        <div class="sq-state-cert-form-wide"><label class="sq-form-label" for="editUserPassword">New Password</label><input class="sq-form-control" id="editUserPassword" type="password" autocomplete="new-password"><small>Leave blank to retain the existing password.</small></div>
                        <div class="sq-state-cert-form-wide sq-state-modal-actions"><button class="sq-btn sq-btn-primary" type="submit">Save Profile</button><button class="sq-btn sq-btn-light" type="button" data-user-edit-close>Cancel</button></div>
                    </form>
                </div>
            </div>`);
        document.getElementById("stateUserProfileModal")?.addEventListener("click", event => {
            if (event.target === event.currentTarget || event.target.closest("[data-user-edit-close]")) event.currentTarget.hidden = true;
        });
        document.getElementById("stateUserProfileForm")?.addEventListener("submit", saveUserProfile);
    }

    function ensurePasswordResetDialog() {
        if (document.getElementById("statePasswordResetModal")) return;
        document.querySelector(".sq-state-page")?.insertAdjacentHTML("beforeend", `
            <div class="sq-state-modal" id="statePasswordResetModal" hidden>
                <div class="sq-state-modal-panel sq-user-profile-panel" role="dialog" aria-modal="true" aria-labelledby="statePasswordResetTitle">
                    <div class="sq-card-header"><div><h3 id="statePasswordResetTitle">Reset User Password</h3><p id="statePasswordResetHint"></p></div><button class="sq-btn sq-btn-light" type="button" data-password-reset-close>Close</button></div>
                    <form class="sq-card-body sq-state-cert-form" id="statePasswordResetForm"><div class="sq-state-cert-form-wide"><label class="sq-form-label" for="stateResetPassword">New Temporary Password *</label><input class="sq-form-control" id="stateResetPassword" type="password" required autocomplete="new-password"><small>8+ characters with upper-case, lower-case, number and special character.</small></div><div class="sq-state-cert-form-wide"><label class="sq-form-label" for="stateResetPasswordConfirm">Confirm Password *</label><input class="sq-form-control" id="stateResetPasswordConfirm" type="password" required autocomplete="new-password"></div><div class="sq-state-cert-form-wide sq-state-modal-actions"><button class="sq-btn sq-btn-primary" type="submit">Reset Password</button><button class="sq-btn sq-btn-light" type="button" data-password-reset-close>Cancel</button></div></form>
                </div>
            </div>`);
        document.getElementById("statePasswordResetModal")?.addEventListener("click", event => { if (event.target === event.currentTarget || event.target.closest("[data-password-reset-close]")) event.currentTarget.hidden = true; });
        document.getElementById("statePasswordResetForm")?.addEventListener("submit", submitPasswordReset);
    }

    function openPasswordReset(button) {
        const userId = Number(button.getAttribute("data-user-password-reset") || 0);
        const row = state.rows.find(item => Number(item.u_id) === userId);
        if (!row) return;
        ensurePasswordResetDialog();
        state.editingUserId = userId;
        document.getElementById("statePasswordResetHint").textContent = `Reset password for ${row.full_name || row.u_name} (${row.u_name}).`;
        document.getElementById("stateResetPassword").value = "";
        document.getElementById("stateResetPasswordConfirm").value = "";
        document.getElementById("statePasswordResetModal").hidden = false;
    }

    async function submitPasswordReset(event) {
        event.preventDefault();
        const password = document.getElementById("stateResetPassword").value;
        if (password !== document.getElementById("stateResetPasswordConfirm").value) {
            SQ.notification?.error("Password confirmation does not match.");
            return;
        }
        try {
            await SQ.api.post("/state/v1/user_password_reset.php", { u_id: state.editingUserId, password: password }, { loader: true, showError: false });
            document.getElementById("statePasswordResetModal").hidden = true;
            SQ.notification?.success("Password reset. The user must change it at next login.");
        } catch (error) { SQ.notification?.error(error.message || "Unable to reset password."); }
    }

    async function ensureCreateUserForm() {
        if (document.getElementById("stateUserCreateModal")) return;
        const response = await SQ.api.get("/state/v1/user_scope_options.php", {}, { loader: true, showError: false });
        state.scopeOptions = response.data || {};
        document.querySelector(".sq-state-page")?.insertAdjacentHTML("beforeend", `
            <div class="sq-state-modal" id="stateUserCreateModal" hidden>
                <div class="sq-state-modal-panel sq-user-profile-panel" role="dialog" aria-modal="true" aria-labelledby="stateUserCreateTitle">
                    <div class="sq-card-header"><div><h3 id="stateUserCreateTitle">Create User</h3><p>Create a user with the correct monitoring scope.</p></div><button class="sq-btn sq-btn-light" type="button" data-user-create-close>Close</button></div>
                    <form class="sq-card-body sq-state-cert-form" id="stateUserCreateForm">
                        <div><label class="sq-form-label" for="createUserRole">Role *</label><select class="sq-form-control" id="createUserRole" required><option value="1">Facility User</option><option value="8">Block User</option><option value="4">District User</option><option value="5">Division User</option></select></div>
                        <div id="createUserIdentity"><div><label class="sq-form-label" for="createUserFirstName">First Name *</label><input class="sq-form-control" id="createUserFirstName" required maxlength="100"></div><div><label class="sq-form-label" for="createUserLastName">Last Name</label><input class="sq-form-control" id="createUserLastName" maxlength="100"></div><div><label class="sq-form-label" for="createUserUsername">Username *</label><input class="sq-form-control" id="createUserUsername" required minlength="3" maxlength="100"></div><div><label class="sq-form-label" for="createUserPassword">Temporary Password *</label><input class="sq-form-control" id="createUserPassword" type="password" required autocomplete="new-password"><small>8+ characters with upper-case, lower-case, number and special character.</small></div><div><label class="sq-form-label" for="createUserEmail">Email</label><input class="sq-form-control" id="createUserEmail" type="email" maxlength="190"></div><div><label class="sq-form-label" for="createUserMobile">Mobile</label><input class="sq-form-control" id="createUserMobile" maxlength="20"></div></div>
                        <div class="sq-state-cert-form-wide" id="createFacilityScope"><p>For a Facility User, the NIN is automatically used as the user ID and initial password. The user completes their personal details after first login.</p><label class="sq-form-label" for="createUserFacilityDistrict">District *</label><select class="sq-form-control" id="createUserFacilityDistrict"></select><label class="sq-form-label" for="createUserFacilityBlock">Block *</label><select class="sq-form-control" id="createUserFacilityBlock" disabled></select><label class="sq-form-label" for="createUserFacilityNin">Facility *</label><select class="sq-form-control" id="createUserFacilityNin" disabled></select></div>
                        <div class="sq-state-cert-form-wide" id="createHierarchyScope" hidden><label class="sq-form-label" for="createUserScope">Assigned Scope *</label><select class="sq-form-control" id="createUserScope"></select></div>
                        <div class="sq-state-cert-form-wide sq-state-modal-actions"><button class="sq-btn sq-btn-primary" type="submit">Create User</button><button class="sq-btn sq-btn-light" type="button" data-user-create-close>Cancel</button></div>
                    </form>
                </div>
            </div>`);
        document.getElementById("stateUserCreateModal")?.addEventListener("click", event => { if (event.target === event.currentTarget || event.target.closest("[data-user-create-close]")) event.currentTarget.hidden = true; });
        document.getElementById("createUserRole")?.addEventListener("change", renderCreateScope);
        document.getElementById("createUserFacilityDistrict")?.addEventListener("change", renderFacilityBlocks);
        document.getElementById("createUserFacilityBlock")?.addEventListener("change", renderFacilities);
        document.getElementById("stateUserCreateForm")?.addEventListener("submit", createUser);
    }

    function renderCreateScope() {
        const roleId = Number(document.getElementById("createUserRole")?.value || 0);
        const facility = document.getElementById("createFacilityScope");
        const hierarchy = document.getElementById("createHierarchyScope");
        const select = document.getElementById("createUserScope");
        const identity = document.getElementById("createUserIdentity");
        const sets = roleId === 5 ? (state.scopeOptions?.divisions || []) : roleId === 4 ? (state.scopeOptions?.districts || []) : roleId === 8 ? (state.scopeOptions?.blocks || []) : [];
        if (facility) facility.hidden = roleId !== 1;
        if (hierarchy) hierarchy.hidden = ![4, 5, 8].includes(roleId);
        if (identity) identity.hidden = roleId === 1;
        ["createUserFirstName", "createUserUsername", "createUserPassword"].forEach(id => {
            const input = document.getElementById(id);
            if (!input) return;
            input.disabled = roleId === 1;
            input.required = roleId !== 1;
        });
        if (roleId === 1) renderFacilityDistricts();
        if (!select) return;
        const label = roleId === 5 ? "division" : roleId === 4 ? "district" : "block";
        select.innerHTML = `<option value="">Select ${label}</option>` + sets.map(row => {
            const id = roleId === 5 ? row.division_id : roleId === 4 ? row.dist_id : row.block_id;
            const name = roleId === 5 ? row.division_name : roleId === 4 ? row.district_name : row.block_name;
            return `<option value="${esc(id)}">${esc(name)} (${esc(id)})</option>`;
        }).join("");
    }

    function optionRows(rows, empty, idKey, nameKey) {
        return `<option value="">${empty}</option>` + rows.map(row => `<option value="${esc(row[idKey])}">${esc(row[nameKey])}</option>`).join("");
    }

    function renderFacilityDistricts() {
        const select = document.getElementById("createUserFacilityDistrict");
        if (!select) return;
        select.innerHTML = optionRows(state.scopeOptions?.districts || [], "Select district", "dist_id", "district_name");
        document.getElementById("createUserFacilityBlock").innerHTML = '<option value="">Select block</option>';
        document.getElementById("createUserFacilityBlock").disabled = true;
        document.getElementById("createUserFacilityNin").innerHTML = '<option value="">Select facility</option>';
        document.getElementById("createUserFacilityNin").disabled = true;
    }

    function renderFacilityBlocks() {
        const districtId = Number(document.getElementById("createUserFacilityDistrict")?.value || 0);
        const select = document.getElementById("createUserFacilityBlock");
        const rows = (state.scopeOptions?.blocks || []).filter(row => Number(row.dist_id) === districtId);
        select.innerHTML = optionRows(rows, "Select block", "block_id", "block_name");
        select.disabled = !districtId;
        document.getElementById("createUserFacilityNin").innerHTML = '<option value="">Select facility</option>';
        document.getElementById("createUserFacilityNin").disabled = true;
    }

    function renderFacilities() {
        const blockId = Number(document.getElementById("createUserFacilityBlock")?.value || 0);
        const select = document.getElementById("createUserFacilityNin");
        const rows = (state.scopeOptions?.facilities || []).filter(row => Number(row.block_id) === blockId);
        select.innerHTML = `<option value="">Select facility</option>` + rows.map(row => `<option value="${esc(row.NIN_no)}">${esc(row.fac_name)} — NIN ${esc(row.NIN_no)}</option>`).join("");
        select.disabled = !blockId;
    }

    async function openCreateUser() {
        try {
            await ensureCreateUserForm();
            renderCreateScope();
            document.getElementById("stateUserCreateModal").hidden = false;
        } catch (error) { SQ.notification?.error(error.message || "Unable to load user creation form."); }
    }

    async function createUser(event) {
        event.preventDefault();
        const roleId = Number(document.getElementById("createUserRole").value);
        const payload = {
            role_id: roleId,
            first_name: document.getElementById("createUserFirstName").value.trim(),
            last_name: document.getElementById("createUserLastName").value.trim(),
            username: document.getElementById("createUserUsername").value.trim(),
            password: document.getElementById("createUserPassword").value,
            email: document.getElementById("createUserEmail").value.trim(),
            mobile: document.getElementById("createUserMobile").value.trim(),
            facility_nin: document.getElementById("createUserFacilityNin").value.trim(),
            scope_id: Number(document.getElementById("createUserScope").value || 0)
        };
        try {
            const response = await SQ.api.post("/state/v1/user_create.php", payload, { loader: true, showError: false });
            document.getElementById("stateUserCreateModal").hidden = true;
            event.target.reset(); renderCreateScope();
            SQ.notification?.success(response.message || `User ${response.data?.username || ""} created. Password change is required at first login.`);
            await load();
        } catch (error) { SQ.notification?.error(error.message || "Unable to create user."); }
    }

    function editUser(button) {
        const userId = Number(button.getAttribute("data-user-edit"));
        const row = state.rows.find(item => Number(item.u_id) === userId);
        if (!row) return;
        ensureProfileEditor();
        state.editingUserId = userId;
        document.getElementById("editUserFirstName").value = row.f_name || "";
        document.getElementById("editUserMiddleName").value = row.m_name || "";
        document.getElementById("editUserLastName").value = row.l_name || "";
        document.getElementById("editUserUsername").value = row.u_name || "";
        document.getElementById("editUserEmail").value = row.mail_id || "";
        document.getElementById("editUserMobile").value = row.mob_no || "";
        document.getElementById("editUserPassword").value = "";
        document.getElementById("stateUserProfileModal").hidden = false;
    }

    async function saveUserProfile(event) {
        event.preventDefault();
        const payload = {
            u_id: state.editingUserId,
            f_name: document.getElementById("editUserFirstName").value.trim(),
            m_name: document.getElementById("editUserMiddleName").value.trim(),
            l_name: document.getElementById("editUserLastName").value.trim(),
            u_name: document.getElementById("editUserUsername").value.trim(),
            mail_id: document.getElementById("editUserEmail").value.trim(),
            mob_no: document.getElementById("editUserMobile").value.trim(),
            password: document.getElementById("editUserPassword").value
        };
        try {
            await SQ.api.post("/state/v1/user_save.php", payload, { loader: true, showError: false });
            document.getElementById("stateUserProfileModal").hidden = true;
            SQ.notification?.success(payload.password ? "Profile updated. New password must be changed at next login." : "User profile updated.");
            await load();
        } catch (error) {
            SQ.notification?.error(error.message || "Unable to update user profile.");
        }
    }

    function bindSearch() {
        let timer = null;
        document.getElementById("stateUsersSearch")?.addEventListener("input", function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                state.pager.reset();
                load();
            }, 300);
        });
    }

    function bindActions() {
        document.getElementById("stateUserRows")?.addEventListener("click", function (event) {
            const resetButton = event.target.closest("[data-user-password-reset]");
            if (resetButton) { openPasswordReset(resetButton); return; }
            const editButton = event.target.closest("[data-user-edit]");
            if (editButton) { editUser(editButton); return; }
            const button = event.target.closest("[data-user-status]");
            if (!button) return;
            updateStatus(button.getAttribute("data-user-status"), button.getAttribute("data-next-status"));
        });
    }

    async function init() {
        if (SQ.deployment && typeof SQ.deployment.load === "function") {
            await SQ.deployment.load();
            SQ.deployment.applyLabels(document);
        }
        const currentUser = SQ.auth && typeof SQ.auth.getUser === "function" ? SQ.auth.getUser() : null;
        state.canEditProfiles = Number(currentUser?.role_id) === 11;
        state.canCreateUsers = [9, 11].includes(Number(currentUser?.role_id));
        state.canResetPasswords = Number(currentUser?.role_id) === 9;
        state.pager = SQ.pagination.create({ page: 1, perPage: 50, onChange: load });
        bindActions();
        bindSearch();
        document.getElementById("stateUsersRefresh")?.addEventListener("click", function () {
            state.pager.reset();
            load();
        });
        const createButton = document.getElementById("stateUserCreate");
        if (createButton && state.canCreateUsers) {
            createButton.hidden = false;
            createButton.addEventListener("click", openCreateUser);
        }
        document.getElementById("stateUsersTemplate")?.addEventListener("click", function () {
            downloadCsv("user-import-template.csv", ["username", "role_name", "school_facility_udise_nin", "is_active", "temporary_password"], [["new.admin", "Administrator", "", "1", ""], ["new.mentor", "Mentor", "", "1", ""]]);
        });
        document.getElementById("stateFacilityReference")?.addEventListener("click", async function () {
            try {
                const response = await SQ.api.get("/state/v1/facility_reference.php", {}, { loader: true, showError: false });
                const unit = domainLabel("facility", "Facility");
                downloadCsv(`${unit.toLowerCase().replace(/\s+/g, "-")}-reference.csv`, [`${unit.toLowerCase().replace(/\s+/g, "_")}_name`, "udise_nin_code", "district", "block"], (response.data?.rows || []).map(row => [row.fac_name, row.NIN_no, row.Dist_Name, row.Block_Name]));
            } catch (error) { SQ.notification?.error(error.message || "Unable to download School/Facility list."); }
        });
        const referenceButton = document.getElementById("stateFacilityReference");
        if (referenceButton) referenceButton.textContent = `${domainLabel("facility", "Facility")} List`;
        document.getElementById("stateUsersExport")?.addEventListener("click", function () {
            downloadCsv("users-export.csv", ["user_id", "username", "role", "school_facility", "district", "status"], (state.rows || []).map(row => [row.u_id, row.u_name, row.role_name || row.role_id_fk, row.fac_name, row.district, Number(row.is_active) === 1 ? "Active" : "Inactive"]));
        });
        await load();
    }

    SQ.stateUsers = { init };
})(window, document);
