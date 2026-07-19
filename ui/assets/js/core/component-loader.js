/*!
 * ==========================================================
 * SQ Component Loader v2.0
 * ----------------------------------------------------------
 * Project  : SaQshi Open Source
 * Module   : HTML/CSS/JS Component Loader
 * File     : component-loader.js
 * License  : GPL-3.0
 * ==========================================================
 *
 * PURPOSE
 * ----------------------------------------------------------
 * Loads reusable components into pages.
 *
 * Use:
 *
 *      <div data-component="header"></div>
 *      <div data-component="sidebar"></div>
 *      <div data-component="footer"></div>
 *
 * This loader automatically loads:
 *
 *      /ui/components/header/header.html
 *      /ui/components/header/header.css
 *      /ui/components/header/header.js
 *
 *      /ui/components/sidebar/sidebar.html
 *      /ui/components/sidebar/sidebar.css
 *      /ui/components/sidebar/sidebar.js
 *
 * BENEFITS
 * ----------------------------------------------------------
 * ✔ No duplicate HTML
 * ✔ Auto loads component HTML
 * ✔ Auto loads component CSS once
 * ✔ Auto loads component JS once
 * ✔ Supports nested components
 * ✔ Supports cache
 * ✔ Supports reload
 * ✔ Supports fallback error message
 * ✔ Open-source friendly component system
 * ==========================================================
 */


(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const CONFIG = {
        basePath: "/ui/components",
        assetVersion: "20260718-a11y-focus-hover-speech",
        attribute: "data-component",
        cache: false,
        debug: true,
        errorTemplate: true,
        autoLoadAssets: true,
        autoInit: false
    };

    const htmlCache = {};
    const loadedCss = {};
    const loadedJs = {};
    const loadingComponents = {};

    function log() {
        if (CONFIG.debug && console && console.log) {
            console.log.apply(console, arguments);
        }
    }

    function componentHtmlUrl(name) {
        return `${CONFIG.basePath}/${name}/${name}.html?v=${CONFIG.assetVersion}`;
    }

    function componentCssUrl(name) {
        return `${CONFIG.basePath}/${name}/${name}.css?v=${CONFIG.assetVersion}`;
    }

    function componentJsUrl(name) {
        return `${CONFIG.basePath}/${name}/${name}.js?v=${CONFIG.assetVersion}`;
    }

    async function loadCss(name) {
        if (!CONFIG.autoLoadAssets || loadedCss[name]) {
            return true;
        }

        const url = componentCssUrl(name);

        if (document.querySelector(`link[data-sq-component-css="${name}"]`)) {
            loadedCss[name] = true;
            return true;
        }

        return new Promise(function (resolve) {
            const link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = url;
            link.setAttribute("data-sq-component-css", name);

            link.onload = function () {
                loadedCss[name] = true;
                log("[SQ Component CSS Loaded]", name);
                resolve(true);
            };

            link.onerror = function () {
                log("[SQ Component CSS Missing/Skipped]", url);
                link.remove();
                resolve(false);
            };

            document.head.appendChild(link);
        });
    }

    async function loadJs(name) {
        if (!CONFIG.autoLoadAssets || loadedJs[name]) {
            return true;
        }

        const url = componentJsUrl(name);

        if (document.querySelector(`script[data-sq-component-js="${name}"]`)) {
            loadedJs[name] = true;
            return true;
        }

        return new Promise(function (resolve) {
            const script = document.createElement("script");
            script.src = url;
            script.defer = true;
            script.setAttribute("data-sq-component-js", name);

            script.onload = function () {
                loadedJs[name] = true;
                log("[SQ Component JS Loaded]", name);
                resolve(true);
            };

            script.onerror = function () {
                log("[SQ Component JS Missing/Skipped]", url);
                script.remove();
                resolve(false);
            };

            document.body.appendChild(script);
        });
    }

    async function fetchComponent(name) {
        if (CONFIG.cache && htmlCache[name]) {
            return htmlCache[name];
        }

        const url = componentHtmlUrl(name);

        const response = await fetch(url, {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: {
                "Accept": "text/html"
            }
        });

        if (!response.ok) {
            throw new Error("Component not found: " + name + " (" + url + ")");
        }

        const html = await response.text();

        if (CONFIG.cache) {
            htmlCache[name] = html;
        }

        return html;
    }

    function renderError(element, name, error) {
        if (!CONFIG.errorTemplate) {
            return;
        }

        element.innerHTML = `
            <div class="sq-alert sq-alert-danger" role="alert">
                <div class="sq-alert-content">
                    <div class="sq-alert-title">Component Load Error</div>
                    <div class="sq-alert-text">
                        Could not load component: <strong>${escapeHtml(name)}</strong>
                    </div>
                    <div class="sq-alert-text sq-text-sm">
                        ${escapeHtml(error.message || "Unknown error")}
                    </div>
                </div>
            </div>
        `;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    async function loadComponent(element) {
        if (!element) {
            return false;
        }

        const name = element.getAttribute(CONFIG.attribute);

        if (!name || element.getAttribute("data-component-loaded") === "true") {
            return false;
        }

        if (loadingComponents[name]) {
            await loadingComponents[name];
        }

        const promise = (async function () {
            try {
                element.setAttribute("data-component-loading", "true");

                await loadCss(name);

                const html = await fetchComponent(name);

                element.innerHTML = html;

                await loadJs(name);

                element.removeAttribute("data-component-loading");
                element.setAttribute("data-component-loaded", "true");

                log("[SQ Component Loaded]", name);

                await load(element);

                dispatchLoadedEvent(element, name);

                return true;

            } catch (error) {
                element.removeAttribute("data-component-loading");
                element.setAttribute("data-component-error", "true");

                renderError(element, name, error);

                console.error("[SQ Component Error]", error);

                return false;
            }
        })();

        loadingComponents[name] = promise;

        const result = await promise;

        delete loadingComponents[name];

        return result;
    }

    async function load(root = document) {
        const selector =
            `[${CONFIG.attribute}]:not([data-component-loaded="true"])`;

        const elements = Array.from(root.querySelectorAll(selector));

        for (const element of elements) {
            await loadComponent(element);
        }

        return true;
    }

    function dispatchLoadedEvent(element, name) {
        element.dispatchEvent(new CustomEvent("sq:component-loaded", {
            bubbles: true,
            detail: {
                name: name,
                element: element
            }
        }));
    }

    function clearCache(name = null) {
        if (name) {
            delete htmlCache[name];
            return;
        }

        Object.keys(htmlCache).forEach(function (key) {
            delete htmlCache[key];
        });
    }

    async function reload(root = document) {
        const elements = Array.from(
            root.querySelectorAll(`[${CONFIG.attribute}]`)
        );

        elements.forEach(function (element) {
            element.removeAttribute("data-component-loaded");
            element.removeAttribute("data-component-error");
            element.removeAttribute("data-component-loading");
            element.innerHTML = "";
        });

        return load(root);
    }

    function init() {
        document.addEventListener("sq:component-loaded", function () {
            if (SQ.router && typeof SQ.router.setActiveMenu === "function") {
                SQ.router.setActiveMenu("[data-sq-nav]");
            }

            if (SQ.auth) {
                if (typeof SQ.auth.renderUser === "function") {
                    SQ.auth.renderUser();
                }

                if (typeof SQ.auth.bindLogoutButtons === "function") {
                    SQ.auth.bindLogoutButtons();
                }
            }
        });

        if (CONFIG.autoInit) {
            load(document);
        }
    }

    SQ.componentLoader = {
        config: function (settings = {}) {
            Object.assign(CONFIG, settings);
        },

        load,
        loadComponent,
        reload,
        clearCache,

        componentHtmlUrl,
        componentCssUrl,
        componentJsUrl
    };

    document.addEventListener("DOMContentLoaded", init);

})(window, document);
