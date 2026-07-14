/*!
 * ==========================================================
 * SaQshi App Bootstrap v1.0
 * ----------------------------------------------------------
 * Project : SaQshi Open Source
 * File    : app.js
 * Purpose : Main frontend bootstrap file
 * License : MIT
 * ==========================================================
 *
 * This file initializes:
 * - SQ-UI core
 * - API base URL
 * - Theme
 * - Auth rendering
 * - Sidebar state
 * - Active menu
 * - Chat assistant
 * - Global error handling
 *
 * Load this file after:
 * 1. sq-ui.js
 * 2. storage.js
 * 3. api.js
 * 4. auth.js
 * 5. validator.js
 * 6. upload.js
 * 7. router.js
 * 8. chat.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    const APP = {
        name: "SaQshi",
        version: "1.0.0",
        apiBaseUrl: "/api",
        loginPage: "/ui/login.html",
        dashboardPage: "/ui/dashboard.html",
        defaultTheme: "light",
        debug: true
    };

    function configureApi() {
        if (!SQ.api) {
            return;
        }

        SQ.api.config({
            baseUrl: APP.apiBaseUrl,
            timeout: 30000,
            debug: APP.debug
        });
    }

    function configureAuth() {
        if (!SQ.auth) {
            return;
        }

        SQ.auth.config({
            loginPage: APP.loginPage,
            dashboardPage: APP.dashboardPage
        });
    }

    function initTheme() {
        if (!SQ.storage) {
            return;
        }

        const savedTheme = SQ.storage.get("theme", APP.defaultTheme);

        document.documentElement.setAttribute("data-theme", savedTheme);

        document.querySelectorAll("[data-sq-theme-toggle]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                const currentTheme =
                    document.documentElement.getAttribute("data-theme") || APP.defaultTheme;

                const nextTheme = currentTheme === "dark" ? "light" : "dark";

                document.documentElement.setAttribute("data-theme", nextTheme);
                SQ.storage.set("theme", nextTheme);

                if (SQ.toast) {
                    SQ.toast("Theme changed to " + nextTheme, "info");
                }
            });
        });
    }

    function initSidebarState() {
        if (!SQ.storage) {
            return;
        }

        const collapsed = SQ.storage.get("sidebar_collapsed", false);

        if (collapsed) {
            document.body.classList.add("sq-sidebar-collapsed");
        }

        document.querySelectorAll("[data-sq-sidebar-collapse]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                document.body.classList.toggle("sq-sidebar-collapsed");

                SQ.storage.set(
                    "sidebar_collapsed",
                    document.body.classList.contains("sq-sidebar-collapsed")
                );
            });
        });
    }

    function initActiveMenu() {
        if (SQ.router) {
            SQ.router.setActiveMenu("[data-sq-nav]");
        }
    }

    function initUserRender() {
        if (SQ.auth) {
            SQ.auth.renderUser();
        }
    }

    function initChat() {
        /*
         * Chat assistant is loaded as ui/components/chat-assistant.
         * Do not initialize the legacy SQ.chat floating widget here,
         * otherwise two floating help buttons appear on every page.
         */
    }

    function protectPage() {
        const isPublicPage =
            document.body.hasAttribute("data-public-page") ||
            window.location.pathname.endsWith("/login.html") ||
            window.location.pathname.endsWith("/index.html");

        if (isPublicPage) {
            return;
        }

        if (SQ.auth) {
            SQ.auth.requireAuth();
        }
    }

    function bindGlobalActions() {
        document.querySelectorAll("[data-sq-refresh]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                window.location.reload();
            });
        });

        document.querySelectorAll("[data-sq-print]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                window.print();
            });
        });

        document.querySelectorAll("[data-sq-copy]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                const value = btn.getAttribute("data-sq-copy");

                if (SQ.copy) {
                    SQ.copy(value);
                }
            });
        });
    }

    function bindGlobalErrors() {
        window.addEventListener("error", function (event) {
            if (APP.debug) {
                console.error("[SaQshi Error]", event.error || event.message);
            }

            if (SQ.toast) {
                SQ.toast("Unexpected error occurred", "danger");
            }
        });

        window.addEventListener("unhandledrejection", function (event) {
            if (APP.debug) {
                console.error("[SaQshi Promise Error]", event.reason);
            }

            if (SQ.toast) {
                const message =
                    event.reason?.message ||
                    "Something went wrong";

                SQ.toast(message, "danger");
            }
        });
    }

    function init() {
        configureApi();
        configureAuth();
        initTheme();
        initSidebarState();
        initActiveMenu();
        initUserRender();
        initChat();
        bindGlobalActions();
        bindGlobalErrors();
        protectPage();

        document.body.classList.add("sq-app-ready");

        if (APP.debug) {
            console.log(APP.name + " UI initialized", APP.version);
        }
    }

    SQ.app = {
        config: function (settings = {}) {
            Object.assign(APP, settings);
        },

        init: init,

        info: function () {
            return {
                name: APP.name,
                version: APP.version,
                apiBaseUrl: APP.apiBaseUrl
            };
        }
    };

    document.addEventListener("DOMContentLoaded", init);

})(window, document);
