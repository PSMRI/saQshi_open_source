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
    const state = { pager: null };

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
                        <th>Facility</th>
                        <th>District</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>${rows.map(row => `
                    <tr>
                        <td><strong>${esc(row.u_name)}</strong><br><small>ID ${esc(row.u_id)}</small></td>
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
            const button = event.target.closest("[data-user-status]");
            if (!button) return;
            updateStatus(button.getAttribute("data-user-status"), button.getAttribute("data-next-status"));
        });
    }

    async function init() {
        state.pager = SQ.pagination.create({ page: 1, perPage: 50, onChange: load });
        bindActions();
        bindSearch();
        document.getElementById("stateUsersRefresh")?.addEventListener("click", function () {
            state.pager.reset();
            load();
        });
        await load();
    }

    SQ.stateUsers = { init };
})(window, document);
