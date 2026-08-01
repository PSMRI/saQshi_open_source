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

    function interpolate(value) {
        return String(value || "").replace(/\{([a-z_]+)\}/g, function (_, key) {
            return label(key, key);
        });
    }

    function text(key, fallback = "") {
        return interpolate(cached?.domain?.content?.[key] || fallback || key);
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

        root.querySelectorAll("[data-domain-content]").forEach(function (el) {
            const key = el.getAttribute("data-domain-content");
            el.textContent = text(key, el.textContent);
        });

        root.querySelectorAll("[data-domain-template]").forEach(function (el) {
            el.textContent = String(el.getAttribute("data-domain-template") || "").replace(/\{([a-z_]+)\}/g, function (_, key) {
                return label(key, key);
            });
        });

        root.querySelectorAll("[data-domain-placeholder]").forEach(function (el) {
            el.setAttribute("placeholder", String(el.getAttribute("data-domain-placeholder") || "").replace(/\{([a-z_]+)\}/g, function (_, key) {
                return label(key, key);
            }));
        });

        root.querySelectorAll("[data-domain-aria-label]").forEach(function (el) {
            el.setAttribute("aria-label", String(el.getAttribute("data-domain-aria-label") || "").replace(/\{([a-z_]+)\}/g, function (_, key) {
                return label(key, key);
            }));
        });

        root.querySelectorAll("[data-domain-title]").forEach(function (el) {
            el.setAttribute("title", String(el.getAttribute("data-domain-title") || "").replace(/\{([a-z_]+)\}/g, function (_, key) {
                return label(key, key);
            }));
        });

        root.querySelectorAll("[data-domain-template]").forEach(function (el) {
            el.textContent = String(el.getAttribute("data-domain-template") || "").replace(/\{([a-z_]+)\}/g, function (_, key) {
                return label(key, key);
            });
        });

        root.querySelectorAll("[data-domain-placeholder]").forEach(function (el) {
            el.setAttribute("placeholder", String(el.getAttribute("data-domain-placeholder") || "").replace(/\{([a-z_]+)\}/g, function (_, key) {
                return label(key, key);
            }));
        });

        root.querySelectorAll("[data-domain-aria-label]").forEach(function (el) {
            el.setAttribute("aria-label", String(el.getAttribute("data-domain-aria-label") || "").replace(/\{([a-z_]+)\}/g, function (_, key) {
                return label(key, key);
            }));
        });
    }

    SQ.deployment = {
        load,
        label,
        text,
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
