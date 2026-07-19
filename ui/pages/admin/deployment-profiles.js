/*!
 * ==========================================================
 * SaQshi Open Source
 * Deployment Profiles
 * deployment-profiles.js
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */
(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function moduleList(profile) {
        return Object.keys(profile.modules || {}).map(function (key) {
            const enabled = profile.modules[key] === true;
            return `<span class="${enabled ? "" : "is-off"}">${esc(key)}: ${enabled ? "on" : "off"}</span>`;
        }).join("");
    }

    function render(config) {
        const domain = config?.domain || {};
        const modules = config?.modules || {};
        const profiles = config?.profiles || [];

        setText("deploymentActiveProfile", domain.profile_name || modules.active_profile || domain.profile_code || "-");
        setText("deploymentDomain", domain.domain || modules.domain || "-");
        setText("deploymentFramework", domain.default_framework || modules.default_framework || "-");

        document.getElementById("deploymentProfileRows").innerHTML = profiles.length
            ? profiles.map(profile => `
                <article class="sq-deployment-profile">
                    <div>
                        <h4>${esc(profile.profile_name || profile.profile_code)}</h4>
                        <small>${esc(profile.recommended_for || "")}</small>
                    </div>
                    <div class="sq-deployment-module-list">${moduleList(profile)}</div>
                    <button type="button" class="sq-btn sq-btn-primary" data-apply-profile="${esc(profile.profile_code)}">Apply Profile</button>
                </article>
            `).join("")
            : `<div class="sq-empty-state">No deployment profiles found.</div>`;
    }

    async function load(force = false) {
        const config = SQ.deployment && typeof SQ.deployment.load === "function"
            ? await SQ.deployment.load(force)
            : (await SQ.api.get("/config/v1/deployment.php", {}, { loader: false })).data;
        render(config || {});
    }

    async function applyProfile(profileCode) {
        if (!profileCode) {
            return;
        }

        if (!window.confirm("Apply this deployment profile? Current module and label configuration will be updated.")) {
            return;
        }

        try {
            const response = await SQ.api.post("/config/v1/profile_apply.php", {
                profile_code: profileCode
            }, { loader: true, showError: false });

            if (SQ.notification) SQ.notification.success(response.message || "Deployment profile applied.");
            await load(true);
            if (SQ.sidebar && typeof SQ.sidebar.refresh === "function") {
                await SQ.sidebar.refresh();
            }
        } catch (error) {
            if (SQ.notification) SQ.notification.error(error.message || "Unable to apply deployment profile.");
        }
    }

    function bind() {
        document.getElementById("deploymentProfileRefresh")?.addEventListener("click", function () {
            load(true);
        });
        document.getElementById("deploymentProfileRows")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-apply-profile]");
            if (button) applyProfile(button.getAttribute("data-apply-profile"));
        });
    }

    async function init() {
        bind();
        await load(true);
    }

    SQ.deploymentProfiles = { init };
})(window, document);
