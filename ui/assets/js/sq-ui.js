/*!
 * ==========================================================
 * SQ-UI JavaScript v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Core UI Helpers
 * Standard  : ES6 + Accessibility Ready
 * License   : MIT
 * ==========================================================
 */

(function (window, document) {
    "use strict";

   // const SQ = {};
const SQ = window.SQ || {};
    /* ======================================================
       Query Helpers
    ====================================================== */

    SQ.$ = function (selector, parent = document) {
        return parent.querySelector(selector);
    };

    SQ.$$ = function (selector, parent = document) {
        return Array.from(parent.querySelectorAll(selector));
    };

    /* ======================================================
       Class Helpers
    ====================================================== */

    SQ.addClass = function (el, className) {
        if (el) {
            el.classList.add(className);
        }
    };

    SQ.removeClass = function (el, className) {
        if (el) {
            el.classList.remove(className);
        }
    };

    SQ.toggleClass = function (el, className) {
        if (el) {
            el.classList.toggle(className);
        }
    };

    SQ.hasClass = function (el, className) {
        return el ? el.classList.contains(className) : false;
    };

    /* ======================================================
       Event Helpers
    ====================================================== */

    SQ.on = function (selector, eventName, callback, parent = document) {
        parent.addEventListener(eventName, function (event) {
            const target = event.target.closest(selector);

            if (target && parent.contains(target)) {
                callback.call(target, event, target);
            }
        });
    };

    SQ.ready = function (callback) {
        if (document.readyState !== "loading") {
            callback();
        } else {
            document.addEventListener("DOMContentLoaded", callback);
        }
    };

    /* ======================================================
       Local Storage Helpers
    ====================================================== */

    SQ.storage = {
        set: function (key, value) {
            localStorage.setItem(key, JSON.stringify(value));
        },

        get: function (key, fallback = null) {
            try {
                const value = localStorage.getItem(key);
                return value ? JSON.parse(value) : fallback;
            } catch (e) {
                return fallback;
            }
        },

        remove: function (key) {
            localStorage.removeItem(key);
        },

        clear: function () {
            localStorage.clear();
        }
    };

    /* ======================================================
       Theme
    ====================================================== */

    SQ.theme = {
        set: function (theme) {
            document.documentElement.setAttribute("data-theme", theme);
            SQ.storage.set("sq-theme", theme);
        },

        get: function () {
            return document.documentElement.getAttribute("data-theme")
                || SQ.storage.get("sq-theme", "light");
        },

        toggle: function () {
            const current = SQ.theme.get();
            SQ.theme.set(current === "dark" ? "light" : "dark");
        },

        init: function () {
            const theme = SQ.storage.get("sq-theme", "light");
            document.documentElement.setAttribute("data-theme", theme);
        }
    };

    /* ======================================================
       Alert Message
    ====================================================== */

    SQ.alert = function (message, type = "info", target = null) {
        const alert = document.createElement("div");

        alert.className = "sq-alert sq-alert-" + type;
        alert.setAttribute("role", "alert");

        alert.innerHTML = `
            <div class="sq-alert-content">
                <div class="sq-alert-text">${SQ.escape(message)}</div>
            </div>
            <button type="button" class="sq-alert-close" aria-label="Close alert">
                ×
            </button>
        `;

        alert.querySelector(".sq-alert-close").addEventListener("click", function () {
            alert.remove();
        });

        if (target) {
            const container = typeof target === "string"
                ? SQ.$(target)
                : target;

            if (container) {
                container.innerHTML = "";
                container.appendChild(alert);
            }
        }

        return alert;
    };

    /* ======================================================
       Toast
    ====================================================== */

    SQ.toast = function (message, type = "info", duration = 4000) {
        let container = SQ.$("#sq-toast-container");

        if (!container) {
            container = document.createElement("div");
            container.id = "sq-toast-container";
            container.style.position = "fixed";
            container.style.right = "1rem";
            container.style.bottom = "1rem";
            container.style.zIndex = "1080";
            container.style.display = "flex";
            container.style.flexDirection = "column";
            container.style.gap = "0.75rem";
            document.body.appendChild(container);
        }

        const toast = document.createElement("div");
        toast.className = "sq-alert sq-alert-" + type + " sq-shadow-md sq-fade-in";
        toast.setAttribute("role", "status");
        toast.innerHTML = `
            <div class="sq-alert-content">
                <div class="sq-alert-text">${SQ.escape(message)}</div>
            </div>
            <button type="button" class="sq-alert-close" aria-label="Close notification">×</button>
        `;

        toast.querySelector(".sq-alert-close").addEventListener("click", function () {
            toast.remove();
        });

        container.appendChild(toast);

        if (duration > 0) {
            setTimeout(function () {
                toast.remove();
            }, duration);
        }

        return toast;
    };

    /* ======================================================
       Loader
    ====================================================== */

    SQ.loader = {
    show: function (message = "Loading...") {
        const componentLoader = document.getElementById("sq-loader");

        if (componentLoader) {
            componentLoader.style.display = "";
            componentLoader.classList.add("active");
            componentLoader.setAttribute("aria-hidden", "false");

            const msg = document.getElementById("sq-loader-message");
            if (msg) {
                msg.textContent = message;
            }

            return;
        }

        let loader = SQ.$("#sq-page-loader");

        if (!loader) {
            loader = document.createElement("div");
            loader.id = "sq-page-loader";
            loader.style.position = "fixed";
            loader.style.inset = "0";
            loader.style.background = "rgba(15,23,42,0.45)";
            loader.style.zIndex = "99999";
            loader.style.display = "flex";
            loader.style.alignItems = "center";
            loader.style.justifyContent = "center";

            loader.innerHTML = `
                <div class="sq-card sq-p-5 sq-text-center">
                    <div class="sq-mt-3">${SQ.escape(message)}</div>
                </div>
            `;

            document.body.appendChild(loader);
        }

        loader.style.display = "flex";
    },

    hide: function () {
        const oldLoader = SQ.$("#sq-page-loader");

        if (oldLoader) {
            oldLoader.remove();
        }

        const componentLoader = SQ.$("#sq-loader");

        if (componentLoader) {
            componentLoader.classList.remove("active", "success", "error");
            componentLoader.setAttribute("aria-hidden", "true");
        }

        document.body.style.overflow = "";
    }
};

    /* ======================================================
       Modal
    ====================================================== */

    SQ.modal = {
        open: function (selector) {
            const modal = SQ.$(selector);

            if (!modal) {
                return;
            }

            modal.classList.add("is-open");
            modal.removeAttribute("hidden");
            modal.setAttribute("aria-hidden", "false");

            const focusable = modal.querySelector(
                "button, [href], input, select, textarea, [tabindex]:not([tabindex='-1'])"
            );

            if (focusable) {
                focusable.focus();
            }

            document.body.classList.add("sq-overflow-hidden");
        },

        close: function (selector) {
            const modal = SQ.$(selector);

            if (!modal) {
                return;
            }

            modal.classList.remove("is-open");
            modal.setAttribute("hidden", "hidden");
            modal.setAttribute("aria-hidden", "true");

            document.body.classList.remove("sq-overflow-hidden");
        }
    };

    /* ======================================================
       Confirm Dialog
    ====================================================== */

    SQ.confirm = function (message, onConfirm) {
        const result = window.confirm(message);

        if (result && typeof onConfirm === "function") {
            onConfirm();
        }

        return result;
    };

    /* ======================================================
       Sidebar
    ====================================================== */

    SQ.sidebar = {
        toggle: function () {
            const sidebar = SQ.$(".sq-sidebar");
            const overlay = SQ.$(".sq-sidebar-overlay");

            if (sidebar) {
                sidebar.classList.toggle("is-open");
            }

            if (overlay) {
                overlay.classList.toggle("is-open");
            }
        },

        close: function () {
            const sidebar = SQ.$(".sq-sidebar");
            const overlay = SQ.$(".sq-sidebar-overlay");

            if (sidebar) {
                sidebar.classList.remove("is-open");
            }

            if (overlay) {
                overlay.classList.remove("is-open");
            }
        }
    };

    /* ======================================================
       Dropdown
    ====================================================== */

    SQ.dropdown = {
        init: function () {
            SQ.on("[data-sq-dropdown]", "click", function (event, trigger) {
                event.preventDefault();

                const targetId = trigger.getAttribute("data-sq-dropdown");
                const menu = SQ.$(targetId);

                if (!menu) {
                    return;
                }

                SQ.$$(".sq-dropdown-menu.is-open").forEach(function (item) {
                    if (item !== menu) {
                        item.classList.remove("is-open");
                    }
                });

                menu.classList.toggle("is-open");
            });

            document.addEventListener("click", function (event) {
                if (!event.target.closest("[data-sq-dropdown], .sq-dropdown-menu")) {
                    SQ.$$(".sq-dropdown-menu.is-open").forEach(function (item) {
                        item.classList.remove("is-open");
                    });
                }
            });
        }
    };

    /* ======================================================
       Tabs
    ====================================================== */

    SQ.tabs = {
        init: function () {
            SQ.on("[data-sq-tab]", "click", function (event, tab) {
                event.preventDefault();

                const target = tab.getAttribute("data-sq-tab");
                const group = tab.closest("[data-sq-tabs]");

                if (!group) {
                    return;
                }

                group.querySelectorAll("[data-sq-tab]").forEach(function (item) {
                    item.classList.remove("is-active");
                    item.setAttribute("aria-selected", "false");
                });

                group.querySelectorAll("[data-sq-tab-panel]").forEach(function (panel) {
                    panel.classList.remove("is-active");
                    panel.hidden = true;
                });

                tab.classList.add("is-active");
                tab.setAttribute("aria-selected", "true");

                const panel = group.querySelector('[data-sq-tab-panel="' + target + '"]');

                if (panel) {
                    panel.classList.add("is-active");
                    panel.hidden = false;
                }
            });
        }
    };

    /* ======================================================
       Accordion
    ====================================================== */

    SQ.accordion = {
        init: function () {
            SQ.on("[data-sq-accordion-trigger]", "click", function (event, trigger) {
                event.preventDefault();

                const item = trigger.closest("[data-sq-accordion-item]");
                const panel = item.querySelector("[data-sq-accordion-panel]");

                if (!item || !panel) {
                    return;
                }

                const isOpen = item.classList.contains("is-open");

                item.classList.toggle("is-open", !isOpen);
                panel.hidden = isOpen;
                trigger.setAttribute("aria-expanded", String(!isOpen));
            });
        }
    };

    /* ======================================================
       Progress Helper
    ====================================================== */

    SQ.progress = function (selector, value) {
        const el = SQ.$(selector);

        if (!el) {
            return;
        }

        const bar = el.querySelector(".sq-progress-bar");

        if (bar) {
            const percent = Math.max(0, Math.min(100, Number(value)));
            bar.style.width = percent + "%";
            bar.setAttribute("aria-valuenow", percent);
        }
    };

    /* ======================================================
       Form Helpers
    ====================================================== */

    SQ.form = {
        serialize: function (form) {
            const formEl = typeof form === "string" ? SQ.$(form) : form;
            const data = {};

            if (!formEl) {
                return data;
            }

            new FormData(formEl).forEach(function (value, key) {
                data[key] = value;
            });

            return data;
        },

        reset: function (form) {
            const formEl = typeof form === "string" ? SQ.$(form) : form;

            if (formEl) {
                formEl.reset();
            }
        },

        setErrors: function (errors = {}, form = document) {
            Object.keys(errors).forEach(function (field) {
                const input = form.querySelector("[name='" + field + "']");
                if (!input) {
                    return;
                }

                input.classList.add("sq-is-invalid");

                let error = input.parentElement.querySelector(".sq-field-error");

                if (!error) {
                    error = document.createElement("div");
                    error.className = "sq-field-error";
                    input.parentElement.appendChild(error);
                }

                error.textContent = errors[field];
            });
        },

        clearErrors: function (form = document) {
            form.querySelectorAll(".sq-is-invalid").forEach(function (el) {
                el.classList.remove("sq-is-invalid");
            });

            form.querySelectorAll(".sq-field-error").forEach(function (el) {
                el.remove();
            });
        }
    };

    /* ======================================================
       Utility Helpers
    ====================================================== */

    SQ.escape = function (value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    SQ.copy = async function (text) {
        try {
            await navigator.clipboard.writeText(text);
            SQ.toast("Copied to clipboard", "success");
            return true;
        } catch (e) {
            SQ.toast("Copy failed", "danger");
            return false;
        }
    };

    SQ.formatDate = function (dateString) {
        if (!dateString) {
            return "";
        }

        const date = new Date(dateString);

        if (Number.isNaN(date.getTime())) {
            return dateString;
        }

        return date.toLocaleDateString();
    };

    SQ.formatDateTime = function (dateString) {
        if (!dateString) {
            return "";
        }

        const date = new Date(dateString);

        if (Number.isNaN(date.getTime())) {
            return dateString;
        }

        return date.toLocaleString();
    };

    SQ.number = function (value, decimals = 0) {
        const number = Number(value || 0);
        return number.toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    };

    SQ.percent = function (value, decimals = 2) {
        return SQ.number(value, decimals) + "%";
    };

    /* ======================================================
       Init
    ====================================================== */

    SQ.init = function () {
        SQ.theme.init();
        SQ.dropdown.init();
        SQ.tabs.init();
        SQ.accordion.init();

        SQ.on("[data-sq-sidebar-toggle]", "click", function () {
            SQ.sidebar.toggle();
        });

        SQ.on(".sq-sidebar-overlay", "click", function () {
            SQ.sidebar.close();
        });

        SQ.on("[data-sq-modal-open]", "click", function (event, trigger) {
            event.preventDefault();
            SQ.modal.open(trigger.getAttribute("data-sq-modal-open"));
        });

        SQ.on("[data-sq-modal-close]", "click", function (event, trigger) {
            event.preventDefault();
            SQ.modal.close(trigger.getAttribute("data-sq-modal-close"));
        });
    };

    SQ.ready(function () {
        SQ.init();
    });

    window.SQ = SQ;

})(window, document);
