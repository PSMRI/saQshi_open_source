/*!
 * ==========================================================
 * SQ Notification Component v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Notification / Toast
 * File      : notification.js
 * License   : GPL-3.0
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    const CONFIG = {
        duration: 4000,
        maxToasts: 5
    };

    const notifications = [];

    function root() {
        return document.getElementById("sq-notification-root");
    }

    function panel() {
        return document.getElementById("sq-notification-panel");
    }

    function list() {
        return document.getElementById("sq-notification-list");
    }

    function icon(type) {
        const icons = {
            success: "✓",
            error: "✕",
            warning: "!",
            info: "i"
        };

        return icons[type] || icons.info;
    }

    function title(type) {
        const titles = {
            success: "Success",
            error: "Error",
            warning: "Warning",
            info: "Information"
        };

        return titles[type] || titles.info;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function add(message, type = "info", options = {}) {
        const item = {
            id: Date.now() + "-" + Math.random().toString(16).slice(2),
            type,
            title: options.title || title(type),
            message,
            time: new Date()
        };

        notifications.unshift(item);
        renderPanel();
        showToast(item, options);

        return item;
    }

    function showToast(item, options = {}) {
        const container = root();

        if (!container) {
            return;
        }

        while (container.children.length >= CONFIG.maxToasts) {
            container.firstElementChild.remove();
        }

        const toast = document.createElement("div");
        toast.className = "sq-toast sq-toast-" + item.type;
        toast.setAttribute("role", item.type === "error" ? "alert" : "status");

        toast.innerHTML = `
            <div class="sq-toast-icon">${escapeHtml(icon(item.type))}</div>
            <div class="sq-toast-content">
                <div class="sq-toast-title">${escapeHtml(item.title)}</div>
                <div class="sq-toast-message">${escapeHtml(item.message)}</div>
            </div>
            <button type="button" class="sq-toast-close" aria-label="Close notification">×</button>
        `;

        toast.querySelector(".sq-toast-close").addEventListener("click", function () {
            removeToast(toast);
        });

        container.appendChild(toast);

        const duration =
            options.duration === 0
                ? 0
                : options.duration || CONFIG.duration;

        if (duration > 0) {
            setTimeout(function () {
                removeToast(toast);
            }, duration);
        }
    }

    function removeToast(toast) {
        if (!toast) {
            return;
        }

        toast.style.animation = "sqToastOut .2s ease forwards";

        setTimeout(function () {
            toast.remove();
        }, 200);
    }

    function renderPanel() {
        const target = list();

        if (!target) {
            return;
        }

        if (!notifications.length) {
            target.innerHTML = `
                <div class="sq-notification-empty">
                    No notifications available.
                </div>
            `;
            updateCount();
            return;
        }

        target.innerHTML = "";

        notifications.forEach(function (item) {
            const row = document.createElement("div");
            row.className = "sq-notification-item";

            row.innerHTML = `
                <div class="sq-notification-item-title">
                    ${escapeHtml(item.title)}
                </div>
                <div class="sq-notification-item-message">
                    ${escapeHtml(item.message)}
                </div>
                <div class="sq-notification-item-time">
                    ${item.time.toLocaleString()}
                </div>
            `;

            target.appendChild(row);
        });

        updateCount();
    }

    function updateCount() {
        const count = document.getElementById("sq-notification-count");

        if (count) {
            count.textContent = String(notifications.length);
        }
    }

    function openPanel() {
        const p = panel();

        if (!p) {
            return;
        }

        p.hidden = false;
    }

    function closePanel() {
        const p = panel();

        if (!p) {
            return;
        }

        p.hidden = true;
    }

    function togglePanel() {
        const p = panel();

        if (!p) {
            return;
        }

        if (p.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    }

    function clear() {
        notifications.length = 0;
        renderPanel();
    }

    function bindEvents() {
        document.querySelectorAll("[data-sq-notification-close]").forEach(function (btn) {
            btn.addEventListener("click", closePanel);
        });

        document.querySelectorAll("[data-sq-notification-toggle]").forEach(function (btn) {
            btn.addEventListener("click", togglePanel);
        });
    }

    function init() {
        bindEvents();
        renderPanel();
    }

    SQ.notification = {
        init,
        add,
        success: function (message, options = {}) {
            return add(message, "success", options);
        },
        error: function (message, options = {}) {
            return add(message, "error", options);
        },
        warning: function (message, options = {}) {
            return add(message, "warning", options);
        },
        info: function (message, options = {}) {
            return add(message, "info", options);
        },
        open: openPanel,
        close: closePanel,
        toggle: togglePanel,
        clear
    };

    document.addEventListener("DOMContentLoaded", init);

    document.addEventListener("sq:component-loaded", function (event) {
        if (event.detail && event.detail.name === "notification") {
            init();
        }
    });

})(window, document);