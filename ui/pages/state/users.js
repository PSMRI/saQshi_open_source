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
    const state = { pager: null, rows: [], canEditProfiles: false, editingUserId: 0 };

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
        return `
            ${state.canEditProfiles ? `<button class="sq-btn sq-btn-light" type="button" data-user-edit="${esc(row.u_id)}">Edit Profile</button>` : ""}
            <button class="sq-btn ${active ? "sq-btn-light" : "sq-btn-primary"}" type="button"
                data-user-status="${esc(row.u_id)}"
                data-next-status="${active ? "0" : "1"}">
                ${active ? "Deactivate" : "Activate"}
            </button>
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
        state.pager = SQ.pagination.create({ page: 1, perPage: 50, onChange: load });
        bindActions();
        bindSearch();
        document.getElementById("stateUsersRefresh")?.addEventListener("click", function () {
            state.pager.reset();
            load();
        });
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
