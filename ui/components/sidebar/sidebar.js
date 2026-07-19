/*!
 * ==========================================================
 * SQ Sidebar Component v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Sidebar
 * File      : sidebar.js
 * License   : GPL-3.0
 * ==========================================================
 *
 * FEATURES
 * ----------------------------------------------------------
 * ✔ Collapse / Expand
 * ✔ Mobile Drawer
 * ✔ Overlay
 * ✔ Active Menu
 * ✔ Accordion Menu (Future)
 * ✔ Remember State
 * ✔ Keyboard Accessible
 * ✔ WCAG 2.2 AA
 * ==========================================================
 */

(function (window, document) {

    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    const STORAGE_KEY = "sq_sidebar_state";

    let collapsed = false;
    let deploymentConfig = null;
    let navigationEventsBound = false;

    /* ======================================================
       Elements
    ====================================================== */

    function sidebar() {
        return document.querySelector(".sq-sidebar");
    }

    function overlay() {
        return document.querySelector(".sq-sidebar-overlay");
    }

    /* ======================================================
       Save State
    ====================================================== */

    function save() {

        if (!SQ.storage) {
            return;
        }

        SQ.storage.set(STORAGE_KEY, collapsed);

    }

    function load() {

        if (!SQ.storage) {
            return false;
        }

        return SQ.storage.get(STORAGE_KEY, false);

    }

    /* ======================================================
       Desktop Collapse
    ====================================================== */

    function collapse() {

        document.body.classList.add("sq-sidebar-collapsed");

        collapsed = true;

        save();

    }

    function expand() {

        document.body.classList.remove("sq-sidebar-collapsed");

        collapsed = false;

        save();

    }

    function toggle() {

        if (collapsed) {

            expand();

        } else {

            collapse();

        }

    }

    /* ======================================================
       Mobile
    ====================================================== */

    function open() {

        document.body.classList.add("sq-sidebar-open");

    }

    function close() {

        document.body.classList.remove("sq-sidebar-open");

    }

    function toggleMobile() {

        document.body.classList.toggle("sq-sidebar-open");

    }

    /* ======================================================
       Active Navigation
    ====================================================== */

    function activeMenu() {

        const current = window.location.pathname;
        const currentHash = window.location.hash || "";
        const links = Array.from(document.querySelectorAll(".sq-sidebar-link"));
        const hasHashSpecificMatch = links.some(function (link) {
            const href = link.getAttribute("href");

            if (!href) {
                return false;
            }

            const url = new URL(href, window.location.origin);
            return url.pathname === current && url.hash && url.hash === currentHash;
        });

        links.forEach(function (link) {

                link.classList.remove("is-active");

                const href = link.getAttribute("href");

                if (!href) {
                    return;
                }

                const url = new URL(href, window.location.origin);

                const isActive = url.pathname === current &&
                    ((hasHashSpecificMatch && url.hash === currentHash) ||
                    (!hasHashSpecificMatch && !url.hash));

                if (isActive) {

                    link.classList.add("is-active");

                    const section = link.closest(".sq-sidebar-section");

                    if (section) {

                        section.classList.add("is-open");

                    }

                }

            });

    }

    function ensureAssessorInfoLink() {

        if (document.querySelector('[data-sq-route="assessment/assessor-info"]')) {
            return;
        }

        const departmentsLink = document.querySelector('[data-sq-route="assessment/departments"]');

        if (!departmentsLink) {
            return;
        }

        const link = document.createElement("a");
        link.href = "#";
        link.className = "sq-sidebar-link";
        link.setAttribute("data-sq-route", "assessment/assessor-info");
        link.setAttribute("data-sq-nav", "");
        link.innerHTML = `
            <i class="bi bi-person-lines-fill"></i>
            <span>Assessor Info</span>
        `;

        departmentsLink.insertAdjacentElement("afterend", link);

    }

    function applyRoleVisibility() {

        const user =
            SQ.auth &&
            typeof SQ.auth.getUser === "function"
                ? SQ.auth.getUser()
                : null;

        const roleId = Number(user && user.role_id);
        const isMonitoringRole = [4, 5, 8, 9].indexOf(roleId) !== -1;
        const isAssessorRole =
            roleId === 10 ||
            /assessor/i.test(String(user && user.role_name || ""));
        const monitoringLabel =
            roleId === 5 ? "Regional Monitoring" :
            roleId === 4 ? "District Monitoring" :
            roleId === 8 ? "Block Monitoring" :
            "State Monitoring";
        const dashboardLabel =
            roleId === 5 ? "Regional Dashboard" :
            roleId === 4 ? "District Dashboard" :
            roleId === 8 ? "Block Dashboard" :
            "State Dashboard";

        document
            .querySelectorAll("[data-state-only]")
            .forEach(function (item) {
                const hiddenByRole = !isMonitoringRole;
                item.dataset.roleHidden = hiddenByRole ? "1" : "0";
                item.hidden = hiddenByRole;
            });

        document
            .querySelectorAll("[data-facility-only]")
            .forEach(function (item) {
                const hiddenByRole = isMonitoringRole || isAssessorRole;
                item.dataset.roleHidden = hiddenByRole ? "1" : "0";
                item.hidden = hiddenByRole;
            });

        document
            .querySelectorAll("[data-assessor-only]")
            .forEach(function (item) {
                const hiddenByRole = !isAssessorRole;
                item.dataset.roleHidden = hiddenByRole ? "1" : "0";
                item.hidden = hiddenByRole;
            });

        document
            .querySelectorAll("[data-monitoring-title]")
            .forEach(function (item) {
                item.textContent = monitoringLabel;
            });

        document
            .querySelectorAll("[data-monitoring-dashboard-label]")
            .forEach(function (item) {
                item.textContent = dashboardLabel;
            });

    }

    async function loadDeploymentConfig(force = false) {
        if (deploymentConfig && !force) {
            return deploymentConfig;
        }

        if (SQ.deployment && typeof SQ.deployment.load === "function") {
            deploymentConfig = await SQ.deployment.load(force);
            return deploymentConfig;
        }

        if (!SQ.api || typeof SQ.api.get !== "function") {
            return null;
        }

        try {
            const response = await SQ.api.get("/config/v1/deployment.php", {}, {
                loader: false,
                showError: false,
                redirectOnUnauthorized: false
            });
            deploymentConfig = response.data || null;
            window.SQ.deployment = deploymentConfig;
            return deploymentConfig;
        } catch (error) {
            return null;
        }
    }

    function moduleEnabled(config, key) {
        if (!key) {
            return true;
        }

        const modules = config && config.modules && config.modules.modules;
        return modules && modules[key] ? modules[key].enabled !== false : true;
    }

    function applyModuleVisibility(config) {
        document.querySelectorAll("[data-module-key]").forEach(function (item) {
            const hiddenByRole = item.dataset.roleHidden === "1";
            const hiddenByModule = !moduleEnabled(config, item.getAttribute("data-module-key"));
            item.hidden = hiddenByRole || hiddenByModule;
        });
    }

    async function refresh() {
        applyRoleVisibility();
        const config = await loadDeploymentConfig(true);
        applyModuleVisibility(config);
        if (SQ.deployment && typeof SQ.deployment.applyLabels === "function") {
            SQ.deployment.applyLabels(document);
        }
        activeMenu();
    }

    /* ======================================================
       Overlay
    ====================================================== */

    function bindOverlay() {

        const ov = overlay();

        if (!ov) {
            return;
        }

        ov.addEventListener("click", close);

    }

    /* ======================================================
       ESC Key
    ====================================================== */

    function bindEscape() {

        document.addEventListener("keydown", function (e) {

            if (e.key === "Escape") {

                close();

            }

        });

    }

    /* ======================================================
       Responsive
    ====================================================== */

    function responsive() {

        if (window.innerWidth < 992) {

            expand();

        }

    }

    /* ======================================================
       Section Accordion
    ====================================================== */

    function bindAccordion() {

        document
            .querySelectorAll("[data-sidebar-group]")
            .forEach(function (group) {

                group.addEventListener("click", function () {

                    group.parentElement.classList.toggle("is-open");

                });

            });

    }

    function bindNavigationEvents() {

        if (navigationEventsBound) {
            return;
        }

        window.addEventListener("hashchange", activeMenu);
        navigationEventsBound = true;

    }

    /* ======================================================
       Search Filter (Future Ready)
    ====================================================== */

    function filter(keyword) {

        keyword = keyword.toLowerCase();

        document
            .querySelectorAll(".sq-sidebar-link")
            .forEach(function (item) {

                const text = item.textContent.toLowerCase();

                if (text.indexOf(keyword) > -1) {

                    item.style.display = "";

                } else {

                    item.style.display = "none";

                }

            });

    }

    /* ======================================================
       Public API
    ====================================================== */

    async function init() {

        collapsed = load();

        if (collapsed) {

            collapse();

        }

        bindOverlay();

        bindEscape();

        bindAccordion();

        bindNavigationEvents();

        ensureAssessorInfoLink();

        applyRoleVisibility();

        const config = await loadDeploymentConfig();
        applyModuleVisibility(config);
        if (SQ.deployment && typeof SQ.deployment.applyLabels === "function") {
            SQ.deployment.applyLabels(document);
        }

        activeMenu();

        responsive();

        window.addEventListener("resize", responsive);

    }

    SQ.sidebar = {

        init,

        collapse,

        expand,

        toggle,

        open,

        close,

        toggleMobile,

        activeMenu,
        refresh,

        filter,

        isCollapsed() {

            return collapsed;

        }

    };

    document.addEventListener("DOMContentLoaded", init);

    document.addEventListener("sq:component-loaded", function (event) {

        if (event.detail &&
            event.detail.name === "sidebar") {

            init();

        }

    });

})(window, document);
