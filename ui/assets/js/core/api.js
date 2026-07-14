/*!
 * ==========================================================
 * SQ-UI API Client v2.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Central API Client
 * Standard  : ES6 + Fetch API
 * License   : GPL-3.0
 * ==========================================================
 */

(function (window) {
    "use strict";

    window.SQ = window.SQ || {};

    const SQ = window.SQ;

    const API = {
        baseUrl: "/api",
        csrfEndpoint: "/auth/v1/csrf.php",
        timeout: 30000,
        csrfKey: "sq_csrf_token",
        debug: false
    };

    function normalizeEndpoint(endpoint) {
        let value = String(endpoint || "");

        if (value.startsWith("http")) {
            return value;
        }

        value = value.replace(/^\/+/, "");

        if (value.startsWith("api/")) {
            value = value.substring(4);
        }

        return "/" + value;
    }

    function buildUrl(endpoint, params = {}) {
        const cleanEndpoint = normalizeEndpoint(endpoint);

        let url = cleanEndpoint.startsWith("http")
            ? cleanEndpoint
            : API.baseUrl + cleanEndpoint;

        const query = new URLSearchParams();

        Object.keys(params || {}).forEach(function (key) {
            if (
                params[key] !== null &&
                params[key] !== undefined &&
                params[key] !== ""
            ) {
                query.append(key, params[key]);
            }
        });

        const queryString = query.toString();

        if (queryString) {
            url += (url.includes("?") ? "&" : "?") + queryString;
        }

        return url;
    }

    function getCsrfToken() {
        return localStorage.getItem(API.csrfKey) || "";
    }

    function setCsrfToken(token) {
        if (token) {
            localStorage.setItem(API.csrfKey, token);
        }
    }

    function clearCsrfToken() {
        localStorage.removeItem(API.csrfKey);
    }

    function clearSession() {
        localStorage.removeItem(API.csrfKey);
        localStorage.removeItem("sq_user");
        localStorage.removeItem("sq_active_assessment");
    }

    function requestHeaders(extraHeaders = {}, isFormData = false) {
        const headers = {};

        if (!isFormData) {
            headers["Content-Type"] = "application/json";
        }

        headers["Accept"] = "application/json";

        const csrf = getCsrfToken();

      if (csrf) {
    headers["X-CSRF-TOKEN"] = String(csrf).trim();
}

        return Object.assign(headers, extraHeaders);
    }

    function isWriteMethod(method) {
        return ["POST", "PUT", "PATCH", "DELETE"].includes(String(method).toUpperCase());
    }

    function extractCsrfToken(result) {
        return (
            result?.csrf_token ||
            result?.data?.csrf_token ||
            result?.token ||
            result?.data?.token ||
            ""
        );
    }

    async function parseResponse(response) {
        const contentType = response.headers.get("content-type") || "";

        if (contentType.includes("application/json")) {
            return await response.json();
        }

        const text = await response.text();

        return {
            status: response.ok ? "success" : "error",
            message: response.ok
                ? (text || response.statusText)
                : "Something went wrong while processing your request. Please try again.",
            data: null,
            errors: response.ok ? null : {
                http_status: response.status
            }
        };
    }

    async function ensureCsrfToken(forceRefresh = false) {
        if (!forceRefresh && getCsrfToken()) {
            return getCsrfToken();
        }

        if (forceRefresh) {
            clearCsrfToken();
        }

        const response = await fetch(buildUrl(API.csrfEndpoint), {
            method: "GET",
            credentials: "include",
            headers: {
                "Accept": "application/json"
            }
        });

        const result = await parseResponse(response);

        if (!response.ok) {
            throw result;
        }

        const token = extractCsrfToken(result);

        if (!token) {
            throw {
                status: "error",
                message: "CSRF token not received from server.",
                data: result,
                errors: null
            };
        }

        setCsrfToken(token);

        return token;
    }

    function isInvalidCsrfError(error) {
        const message = String(error?.message || "").toLowerCase();

        return (
            message.includes("csrf") &&
            (
                message.includes("invalid") ||
                message.includes("expired") ||
                message.includes("missing")
            )
        );
    }

    function redactSensitive(value) {
        if (!value || typeof value !== "object" || value instanceof FormData) {
            return value;
        }

        const copy = Array.isArray(value) ? value.slice() : Object.assign({}, value);

        ["password", "passwd", "pwd", "confirm_password", "old_password", "new_password", "captcha"].forEach(function (key) {
            if (Object.prototype.hasOwnProperty.call(copy, key)) {
                copy[key] = "***REDACTED***";
            }
        });

        return copy;
    }

    async function doFetch(method, endpoint, data, options, retrying = false) {
        const controller = new AbortController();
        const timeout = options.timeout || API.timeout;

        const timer = setTimeout(function () {
            controller.abort();
        }, timeout);

        const isFormData = data instanceof FormData;

        try {
            if (isWriteMethod(method)) {
                await ensureCsrfToken(retrying);
            }

            const fetchOptions = {
                method: method,
                credentials: "include",
                headers: requestHeaders(options.headers || {}, isFormData),
                signal: controller.signal
            };

            if (data && method !== "GET") {
                fetchOptions.body = isFormData ? data : JSON.stringify(data);
            }

            const url = method === "GET"
                ? buildUrl(endpoint, data || {})
                : buildUrl(endpoint, options.params || {});

            if (API.debug) {
                console.log("[SQ API]", method, url, redactSensitive(data) || "");
            }

            const response = await fetch(url, fetchOptions);

            const result = await parseResponse(response);

            const newToken = extractCsrfToken(result);

            if (newToken) {
                setCsrfToken(newToken);
            }

            if (response.status === 401) {
                clearSession();

                if (SQ.toast) {
                    SQ.toast("Session expired. Please login again.", "danger");
                }

                if (options.redirectOnUnauthorized !== false) {
                    setTimeout(function () {
                        window.location.href = "/ui/login.html";
                    }, 800);
                }

                throw result;
            }

            if (!response.ok || result?.status === "error") {
                throw result;
            }

            return result;

        } catch (error) {
            if (
                !retrying &&
                isWriteMethod(method) &&
                isInvalidCsrfError(error)
            ) {
                clearCsrfToken();
                return doFetch(method, endpoint, data, options, true);
            }

            if (error.name === "AbortError") {
                throw {
                    status: "error",
                    message: "Request timeout. Please try again.",
                    data: null,
                    errors: null
                };
            }

            throw error;

        } finally {
            clearTimeout(timer);
        }
    }

    async function request(method, endpoint, data = null, options = {}) {
        const shouldShowLoader =
            options.loader === true ||
            (
                options.loader !== false &&
                String(method).toUpperCase() !== "GET"
            );

        try {
            if (
                shouldShowLoader &&
                SQ.loader &&
                typeof SQ.loader.show === "function"
            ) {
                SQ.loader.show(options.loaderText || "Please wait...");
            }

            return await doFetch(method, endpoint, data, options, false);

        } catch (error) {
            if (SQ.toast && options.showError !== false) {
                SQ.toast(error.message || "Something went wrong", "danger");
            }

            throw error;

        } finally {
            if (
                shouldShowLoader &&
                SQ.loader &&
                typeof SQ.loader.hide === "function"
            ) {
                SQ.loader.hide();
            }
        }
    }

    SQ.api = {
        config: function (settings = {}) {
            Object.assign(API, settings);
        },

        get: function (endpoint, params = {}, options = {}) {
            return request("GET", endpoint, params, options);
        },

        post: function (endpoint, data = {}, options = {}) {
            return request("POST", endpoint, data, options);
        },

        put: function (endpoint, data = {}, options = {}) {
            return request("PUT", endpoint, data, options);
        },

        patch: function (endpoint, data = {}, options = {}) {
            return request("PATCH", endpoint, data, options);
        },

        delete: function (endpoint, data = {}, options = {}) {
            return request("DELETE", endpoint, data, options);
        },

        upload: function (endpoint, formData, options = {}) {
            if (!(formData instanceof FormData)) {
                throw new Error("Upload requires FormData");
            }

            return request("POST", endpoint, formData, options);
        },

        download: async function (endpoint, params = {}, filename = "download") {
            const url = buildUrl(endpoint, params);

            const response = await fetch(url, {
                method: "GET",
                credentials: "include",
                headers: requestHeaders({}, false)
            });

            if (!response.ok) {
                throw new Error("Download failed");
            }

            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);

            const link = document.createElement("a");
            link.href = objectUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();

            URL.revokeObjectURL(objectUrl);
        },

        csrf: function (forceRefresh = false) {
            return ensureCsrfToken(forceRefresh);
        },

        setCsrfToken,
        getCsrfToken,
        clearCsrfToken,
        clearSession,
        buildUrl
    };

})(window);
