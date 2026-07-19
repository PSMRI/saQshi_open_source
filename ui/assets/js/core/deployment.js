/*!
 * ==========================================================
 * SaQshi Deployment Config Client
 * deployment.js
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */
(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    let cached = null;

    async function load(force = false) {
        if (cached && !force) {
            return cached;
        }

        if (!SQ.api || typeof SQ.api.get !== "function") {
            return null;
        }

        const response = await SQ.api.get("/config/v1/deployment.php", {}, {
            loader: false,
            showError: false,
            redirectOnUnauthorized: false
        });
        cached = response.data || null;
        SQ.deploymentConfig = cached;
        applyLabels(document);
        return cached;
    }

    function label(key, fallback = "") {
        return cached?.domain?.labels?.[key] || fallback || key;
    }

    function moduleEnabled(key) {
        const module = cached?.modules?.modules?.[key];
        return module ? module.enabled !== false : true;
    }

    function applyLabels(root = document) {
        if (!cached) {
            return;
        }

        root.querySelectorAll("[data-domain-label]").forEach(function (el) {
            const key = el.getAttribute("data-domain-label");
            el.textContent = label(key, el.textContent);
        });
    }

    SQ.deployment = {
        load,
        label,
        moduleEnabled,
        applyLabels,
        get current() {
            return cached;
        }
    };

    document.addEventListener("sq:page-ready", function () {
        if (cached) {
            applyLabels(document);
        }
    });
})(window, document);
