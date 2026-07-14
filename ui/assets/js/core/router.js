/*!
 * ==========================================================
 * SQ Router Service v2.5
 * ----------------------------------------------------------
 * Project  : SaQshi Open Source
 * Module   : Frontend Navigation / Routing Service
 * File     : router.js
 * License  : GPL-3.0
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const CONFIG = {
        basePath: "/ui",
        defaultRoute: "dashboard",
        loginRoute: "login",
        loginPage: "/ui/login.html",
        layoutPath: "/ui/layouts",
        pagesPath: "/ui/pages",
        rootSelector: "#sq-root",
        contentSelector: "#sq-page-content",
        pageTitleSelector: "#sq-page-title",
        pageSubtitleSelector: "#sq-page-subtitle",
        pageActionsSelector: "#sq-page-actions",
        debug: true
    };

    const state = {
        currentRoute: null,
        currentLayout: null,
        currentManifest: null,
        loadedCss: {},
        loadedJs: {},
        isLoading: false
    };

    function routeName(route = "") {
        let value = String(route || CONFIG.defaultRoute)
            .replace(window.location.origin, "")
            .replace(/^\/ui\/dashboard\.html\?route=/, "")
            .replace(/^\/ui\//, "")
            .replace(/^\/+/, "")
            .replace(/\.html$/, "")
            .split("?")[0];

        if (value === "dashboard") {
            return "dashboard";
        }

        if (value === "login") {
            return "login";
        }

        return value || CONFIG.defaultRoute;
    }

    function routeBasePath(route) {
        const name = routeName(route);

        if (name === "dashboard") {
            return `${CONFIG.pagesPath}/dashboard/dashboard`;
        }

        if (name === "login") {
            return `${CONFIG.pagesPath}/login/login`;
        }

        return `${CONFIG.pagesPath}/${name}`;
    }

    function routeUrl(route, params = {}) {
        const name = routeName(route);

        if (name === CONFIG.defaultRoute) {
            return `${CONFIG.basePath}/dashboard.html`;
        }

        const url = new URL(`${CONFIG.basePath}/dashboard.html`, window.location.origin);
        url.searchParams.set("route", name);

        Object.keys(params || {}).forEach(function (key) {
            if (
                params[key] !== null &&
                params[key] !== undefined &&
                params[key] !== ""
            ) {
                url.searchParams.set(key, params[key]);
            }
        });

        return url.pathname + url.search;
    }

    function manifestUrl(route) {
        return `${routeBasePath(route)}.json`;
    }

    function pageHtmlUrl(route) {
        return `${routeBasePath(route)}.html`;
    }

    function layoutUrl(layout) {
        return `${CONFIG.layoutPath}/${layout}.html`;
    }

    function debugLog() {
        if (CONFIG.debug && console && console.log) {
            console.log.apply(console, arguments);
        }
    }

    async function fetchJson(url) {
        const res = await fetch(url, {
            cache: "no-store",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });

        if (!res.ok) {
            throw new Error("Unable to load JSON: " + url);
        }

        return res.json();
    }

    async function fetchHtml(url) {
        const res = await fetch(url, {
            cache: "no-store",
            credentials: "same-origin",
            headers: {
                "Accept": "text/html"
            }
        });

        if (!res.ok) {
            throw new Error("Unable to load HTML: " + url);
        }

        return res.text();
    }

    async function loadCss(url) {
        if (!url || state.loadedCss[url]) {
            return;
        }

        if (document.querySelector(`link[href="${url}"]`)) {
            state.loadedCss[url] = true;
            return;
        }

        await new Promise(function (resolve, reject) {
            const link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = url;
            link.setAttribute("data-sq-page-css", url);

            link.onload = function () {
                state.loadedCss[url] = true;
                resolve();
            };

            link.onerror = function () {
                reject(new Error("Unable to load CSS: " + url));
            };

            document.head.appendChild(link);
        });
    }

    async function loadJs(url) {
        if (!url || state.loadedJs[url]) {
            return;
        }

        if (document.querySelector(`script[src="${url}"]`)) {
            state.loadedJs[url] = true;
            return;
        }

        await new Promise(function (resolve, reject) {
            const script = document.createElement("script");
            script.src = url;
            script.setAttribute("data-sq-page-js", url);

            script.onload = function () {
                state.loadedJs[url] = true;
                resolve();
            };

            script.onerror = function () {
                reject(new Error("Unable to load JS: " + url));
            };

            document.body.appendChild(script);
        });
    }

    async function loadLayout(layoutName) {
        const root = document.querySelector(CONFIG.rootSelector);

        if (!root) {
            throw new Error("Root container not found: " + CONFIG.rootSelector);
        }

        if (state.currentLayout === layoutName && root.innerHTML.trim() !== "") {
            return;
        }

        root.innerHTML = await fetchHtml(layoutUrl(layoutName));
        state.currentLayout = layoutName;

        if (SQ.componentLoader && typeof SQ.componentLoader.load === "function") {
            await SQ.componentLoader.load(root);
        }
    }

    async function checkAuth(manifest) {
        const required =
            manifest.authentication &&
            manifest.authentication.required === true;

        if (!required) {
            return;
        }

        if (SQ.auth && typeof SQ.auth.requireAuth === "function") {
            await SQ.auth.requireAuth();
        }
    }

    function setPageMeta(manifest) {
        document.title = `${manifest.title || manifest.name || "SaQshi"} | SaQshi`;

        const title = document.querySelector(CONFIG.pageTitleSelector);
        const subtitle = document.querySelector(CONFIG.pageSubtitleSelector);

        if (title) {
            title.textContent =
                manifest.pageHeader?.title ||
                manifest.title ||
                "";
        }

        if (subtitle) {
            subtitle.textContent =
                manifest.pageHeader?.subtitle ||
                manifest.description ||
                "";
        }
    }

    function renderActions(manifest) {
        const target = document.querySelector(CONFIG.pageActionsSelector);

        if (!target) {
            return;
        }

        target.innerHTML = "";

        (manifest.quickActions || []).forEach(function (action) {
            const a = document.createElement("a");
            a.href = action.url || "#";
            a.className = action.className || "sq-btn sq-btn-primary";
            a.setAttribute("data-sq-route", action.route || action.url || "#");

            a.innerHTML = `
                ${action.icon ? `<i class="bi ${action.icon}"></i>` : ""}
                ${escapeHtml(action.title || "Action")}
            `;

            target.appendChild(a);
        });
    }

    function renderBreadcrumb(manifest) {
        if (!SQ.breadcrumb || typeof SQ.breadcrumb.render !== "function") {
            return;
        }

        const items = (manifest.breadcrumb || []).map(function (item) {
            return {
                label: item.title || item.label,
                url: item.url,
                icon: item.icon
            };
        });

        SQ.breadcrumb.render(items);
    }

    function forceHideLoader() {
        if (SQ.loader && typeof SQ.loader.hide === "function") {
            SQ.loader.hide();
        }

        document.querySelectorAll("#sq-page-loader, .sq-loader").forEach(function (el) {
            el.classList.remove("active", "success", "error");
            el.style.display = "none";
            el.setAttribute("aria-hidden", "true");
        });

        document.body.style.overflow = "";
    }

    async function loadPage(route, options = {}) {
        if (state.isLoading) {
            return;
        }

        const name = routeName(route);
        state.isLoading = true;

        try {
            const useLoader =
                options.loader === true &&
                name !== CONFIG.loginRoute;

            forceHideLoader();

            if (useLoader && SQ.loader && typeof SQ.loader.show === "function") {
                SQ.loader.show("Loading page...");
            }

            debugLog("[SQ Router] Route:", name);
            debugLog("[SQ Router] Manifest:", manifestUrl(name));
            debugLog("[SQ Router] HTML:", pageHtmlUrl(name));

            const manifest = await fetchJson(manifestUrl(name));

            debugLog("[SQ Router] CSS assets:", manifest.assets?.css || []);
            debugLog("[SQ Router] JS assets:", manifest.assets?.js || []);

            await checkAuth(manifest);
            await loadLayout(manifest.layout || "dashboard");

            for (const css of manifest.assets?.css || []) {
                await loadCss(css);
            }

            const content = document.querySelector(CONFIG.contentSelector);

            if (!content) {
                throw new Error(
                    "Page content container not found: " +
                    CONFIG.contentSelector
                );
            }

            content.innerHTML = await fetchHtml(pageHtmlUrl(name));

            setPageMeta(manifest);
            renderActions(manifest);
            renderBreadcrumb(manifest);

            if (SQ.componentLoader && typeof SQ.componentLoader.load === "function") {
                await SQ.componentLoader.load(content);
            }

            for (const js of manifest.assets?.js || []) {
                await loadJs(js);
            }

            state.currentRoute = name;
            state.currentManifest = manifest;

            if (options.history !== false) {
                history.pushState(
                    {
                        route: name
                    },
                    "",
                    routeUrl(name, options.params || {})
                );
            }

            setActiveMenu();

            document.dispatchEvent(new CustomEvent("sq:page-loaded", {
                detail: {
                    route: name,
                    manifest: manifest
                }
            }));

            const moduleName =
                manifest.moduleName ||
                manifest.module ||
                name.replace(/[/-](\w)/g, function (_, c) {
                    return c.toUpperCase();
                });

            const module = SQ[moduleName] || SQ[name];

            if (module && typeof module.init === "function") {
                try {
                    await module.init();
                } catch (initError) {
                    console.error("[SQ Page Init Error]", initError);

                    const message =
                        initError?.message ||
                        "Unable to load page data right now. Please refresh or try again after some time.";

                    const notice = document.createElement("div");
                    notice.className = "sq-alert sq-alert-warning sq-mb-3";
                    notice.innerHTML = `
                        <div class="sq-alert-content">
                            <div class="sq-alert-title">Page data could not be loaded</div>
                            <div class="sq-alert-text">${escapeHtml(message)}</div>
                        </div>
                    `;

                    const pageContent = document.querySelector(CONFIG.contentSelector);

                    if (pageContent) {
                        pageContent.prepend(notice);
                    }

                    if (SQ.notification && typeof SQ.notification.warning === "function") {
                        SQ.notification.warning("Page opened, but some data could not be loaded.");
                    }
                }
            }

            document.dispatchEvent(new CustomEvent("sq:page-ready", {
                detail: {
                    route: name,
                    manifest: manifest
                }
            }));

        } catch (error) {
            console.error("[SQ Router Error]", error);

            const root = document.querySelector(CONFIG.rootSelector) || document.body;

            root.innerHTML = `
                <div class="sq-alert sq-alert-danger sq-m-5">
                    <div class="sq-alert-content">
                        <div class="sq-alert-title">Page Load Failed</div>
                        <div class="sq-alert-text">${escapeHtml(error.message || error)}</div>
                    </div>
                </div>
            `;
        } finally {
            state.isLoading = false;
            forceHideLoader();
        }
    }

    function navigate(route, params = {}, options = {}) {
        return loadPage(
            routeName(route),
            Object.assign({}, options, {
                params: params || {}
            })
        );
    }

    function go(path, params = {}, options = {}) {
        if (options.spa === true) {
            return navigate(path, params, options);
        }

        const url = buildUrl(path, params);

        if (options.replace) {
            window.location.replace(url);
        } else {
            window.location.href = url;
        }
    }

    function buildUrl(path, params = {}) {
        const url = new URL(
            path.startsWith("/") ? path : CONFIG.basePath + "/" + path,
            window.location.origin
        );

        Object.keys(params).forEach(function (key) {
            if (
                params[key] !== null &&
                params[key] !== undefined &&
                params[key] !== ""
            ) {
                url.searchParams.set(key, params[key]);
            }
        });

        return url.toString();
    }

    function setQuery(params = {}, options = {}) {
        const url = new URL(window.location.href);

        Object.keys(params).forEach(function (key) {
            if (
                params[key] === null ||
                params[key] === undefined ||
                params[key] === ""
            ) {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, params[key]);
            }
        });

        if (options.replace !== false) {
            window.history.replaceState({}, "", url.toString());
        } else {
            window.history.pushState({}, "", url.toString());
        }
    }

    function query(name, fallback = null) {
        return new URLSearchParams(window.location.search).get(name) || fallback;
    }

    function queries() {
        const obj = {};

        new URLSearchParams(window.location.search).forEach(function (value, key) {
            obj[key] = value;
        });

        return obj;
    }

    function removeQuery(keys = []) {
        const url = new URL(window.location.href);

        keys.forEach(function (key) {
            url.searchParams.delete(key);
        });

        window.history.replaceState({}, "", url.toString());
    }

    function setActiveMenu(selector = "[data-sq-nav]") {
        document.querySelectorAll(selector).forEach(function (link) {
            const href = link.getAttribute("href") || "";
            const route = link.getAttribute("data-sq-route") || href;

            const active =
                route.includes(state.currentRoute || "") ||
                href.includes(state.currentRoute || "");

            link.classList.toggle("is-active", active);

            if (active) {
                link.setAttribute("aria-current", "page");
            } else {
                link.removeAttribute("aria-current");
            }
        });
    }

    function reload() {
        if (state.currentRoute) {
            return loadPage(state.currentRoute, {
                history: false
            });
        }

        window.location.reload();
    }

    function back() {
        window.history.back();
    }

    function safeBack(fallback = CONFIG.defaultRoute) {
        if (window.history.length > 1) {
            back();
        } else {
            navigate(fallback);
        }
    }

    function bindLinks() {
        document.addEventListener("click", function (event) {
            const el = event.target.closest("[data-sq-route]");

            if (!el) {
                return;
            }

            event.preventDefault();

            const route =
                el.getAttribute("data-sq-route") ||
                el.getAttribute("href");

            navigate(route);
        });
    }

    function currentRouteFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const route = params.get("route");

        if (route) {
            return routeName(route);
        }

        return routeName(window.location.pathname);
    }

    function init() {
        bindLinks();

        window.addEventListener("popstate", function (event) {
            const route =
                event.state?.route ||
                state.currentRoute ||
                CONFIG.defaultRoute;

            loadPage(route, {
                history: false
            });
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    SQ.router = {
        config(settings = {}) {
            Object.assign(CONFIG, settings);
        },

        navigate,
        loadPage,
        go,
        reload,
        back,
        safeBack,

        query,
        queries,
        setQuery,
        removeQuery,

        setActiveMenu,
        buildUrl,
        routeName,
        manifestUrl,
        pageHtmlUrl,
        routeUrl,
        currentRouteFromUrl,
        init,
        state
    };

    document.addEventListener("DOMContentLoaded", init);

})(window, document);
