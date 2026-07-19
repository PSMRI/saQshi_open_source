/*!
 * ==========================================================
 * SQ Header Component JS v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Header
 * File      : header.js
 * License   : GPL-3.0
 * ==========================================================
 *
 * Responsibilities:
 * - Sidebar toggle
 * - Theme toggle
 * - User dropdown
 * - Logout binding
 * - User details rendering
 * - AI assistant button
 * - Notification count placeholder
 * - Accessibility speech controls
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    function renderUser() {
        if (!SQ.auth || !SQ.auth.getUser) {
            return;
        }

        const user = SQ.auth.getUser();

        if (!user) {
            return;
        }

        document.querySelectorAll("[data-sq-user-name]").forEach(function (el) {
            el.textContent = user.full_name || user.u_name || "User";
        });

        document.querySelectorAll("[data-sq-user-role]").forEach(function (el) {
            el.textContent = user.role_name || user.role_id || "Role";
        });
    }

   function bindSidebarToggle() {
    const btn = document.getElementById("sq-sidebar-toggle");

    if (!btn) {
        return;
    }

    btn.onclick = function () {
        document.body.classList.toggle("sq-sidebar-collapsed");
    };
}

    function bindThemeToggle() {
        document.querySelectorAll("[data-sq-theme-toggle]").forEach(function (btn) {
            if (btn.dataset.sqThemeBound === "true") {
                return;
            }

            btn.dataset.sqThemeBound = "true";

            btn.addEventListener("click", function () {
                const current =
                    document.documentElement.getAttribute("data-theme") || "light";

                const next = current === "dark" ? "light" : "dark";

                document.documentElement.setAttribute("data-theme", next);

                if (SQ.storage) {
                    SQ.storage.set("theme", next);
                }

                if (SQ.toast) {
                    SQ.toast("Theme changed to " + next, "info");
                }
            });
        });
    }

    function applyAccessibilitySettings() {
        const fontSize = SQ.storage
            ? SQ.storage.get("accessibility_font_size", "normal")
            : "normal";
        const screenReaderMode = SQ.storage
            ? SQ.storage.get("accessibility_screen_reader_mode", false)
            : false;

        const validFontSize = ["small", "normal", "large", "xlarge"].indexOf(fontSize) !== -1
            ? fontSize
            : "normal";

        document.documentElement.setAttribute("data-font-size", validFontSize);
        document.documentElement.setAttribute("data-screen-reader-mode", screenReaderMode ? "true" : "false");

        document.querySelectorAll("[data-sq-font-size]").forEach(function (button) {
            const active = button.getAttribute("data-sq-font-size") === validFontSize;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-pressed", active ? "true" : "false");
        });

        document.querySelectorAll("[data-sq-screen-reader-mode]").forEach(function (button) {
            button.classList.toggle("is-active", !!screenReaderMode);
            button.setAttribute("aria-pressed", screenReaderMode ? "true" : "false");
        });
    }

    function speechSupported() {
        return "speechSynthesis" in window && "SpeechSynthesisUtterance" in window;
    }

    function cleanSpeechText(value) {
        return String(value || "")
            .replace(/\s+/g, " ")
            .trim()
            .slice(0, 4500);
    }

    function speakText(text) {
        const message = cleanSpeechText(text);

        if (!message) {
            return;
        }

        if (!speechSupported()) {
            if (SQ.toast) {
                SQ.toast("Speech is not supported in this browser.", "warning");
            }
            return;
        }

        window.speechSynthesis.cancel();

        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = document.documentElement.lang || "en-US";
        utterance.rate = 0.95;
        utterance.pitch = 1;
        utterance.volume = 1;

        window.speechSynthesis.speak(utterance);
    }

    function currentPageSpeechText() {
        const title = document.getElementById("sq-page-title");
        const subtitle = document.getElementById("sq-page-subtitle");
        const content = document.getElementById("sq-page-content");
        const parts = [];

        if (title) {
            parts.push(title.textContent);
        }

        if (subtitle) {
            parts.push(subtitle.textContent);
        }

        if (content) {
            parts.push(content.innerText || content.textContent || "");
        }

        return cleanSpeechText(parts.join(". "));
    }

    function stopSpeech() {
        if (speechSupported()) {
            window.speechSynthesis.cancel();
        }
    }

    function screenReaderModeEnabled() {
        return document.documentElement.getAttribute("data-screen-reader-mode") === "true";
    }

    function focusableSelector() {
        return "a, button, input, select, textarea, summary, [role='button'], [role='link'], [tabindex]:not([tabindex='-1'])";
    }

    function readableSelector() {
        return focusableSelector() + ", h1, h2, h3, h4, h5, h6, label, th, td";
    }

    function readableControlText(element) {
        if (!element) {
            return "";
        }

        const tag = String(element.tagName || "").toLowerCase();
        const role = element.getAttribute("role") || "";
        const id = element.getAttribute("id") || "";
        const label = id
            ? document.querySelector("label[for='" + (window.CSS && CSS.escape ? CSS.escape(id) : id) + "']")
            : null;
        const text = [
            element.getAttribute("aria-label"),
            label ? label.textContent : "",
            element.getAttribute("placeholder"),
            element.getAttribute("title"),
            element.textContent
        ].map(cleanSpeechText).find(Boolean) || "";

        let type = role || tag;

        if (tag === "input") {
            type = element.getAttribute("type") || "input";
        }

        if (tag === "a") {
            type = "link";
        } else if (tag === "button") {
            type = "button";
        } else if (tag === "select") {
            type = "selection list";
        } else if (tag === "textarea") {
            type = "text area";
        }

        if (!text && /^h[1-6]$/.test(tag)) {
            type = "heading";
        }

        return cleanSpeechText([text, type].filter(Boolean).join(", "));
    }

    function announceElement(element, delay) {
        if (!screenReaderModeEnabled()) {
            return;
        }

        const target = element && element.closest
            ? element.closest(readableSelector())
            : element;

        if (!target) {
            return;
        }

        const message = readableControlText(target);

        if (!message) {
            return;
        }

        const now = Date.now();

        if (
            window.__sqLastFocusSpeechText === message &&
            (now - (window.__sqLastFocusSpeechAt || 0)) < 1200
        ) {
            return;
        }

        window.__sqLastFocusSpeechText = message;
        window.__sqLastFocusSpeechAt = now;
        window.clearTimeout(window.__sqAutoPageSpeechTimer);
        window.clearTimeout(window.__sqFocusSpeechTimer);

        window.__sqFocusSpeechTimer = window.setTimeout(function () {
            speakText(message);
        }, typeof delay === "number" ? delay : 80);
    }

    function bindFocusSpeech() {
        if (document.documentElement.dataset.sqFocusSpeechBound === "true") {
            return;
        }

        document.documentElement.dataset.sqFocusSpeechBound = "true";

        document.addEventListener("focusin", function (event) {
            announceElement(event.target, 60);
        });

        document.addEventListener("keydown", function (event) {
            if (event.key !== "Tab") {
                return;
            }

            window.setTimeout(function () {
                announceElement(document.activeElement, 0);
            }, 90);
        });

        document.addEventListener("pointerover", function (event) {
            announceElement(event.target, 120);
        });
    }

    function speakCurrentPageAutomatically() {
        if (!screenReaderModeEnabled()) {
            return;
        }

        window.clearTimeout(window.__sqAutoPageSpeechTimer);
        window.__sqAutoPageSpeechTimer = window.setTimeout(function () {
            speakText(currentPageSpeechText() || "Page loaded.");
        }, 700);
    }

    function bindAutoPageSpeech() {
        if (document.documentElement.dataset.sqAutoPageSpeechBound === "true") {
            return;
        }

        document.documentElement.dataset.sqAutoPageSpeechBound = "true";

        document.addEventListener("sq:page-ready", speakCurrentPageAutomatically);

        if (screenReaderModeEnabled()) {
            speakCurrentPageAutomatically();
        }
    }

    function bindAccessibilityControls() {
        document.querySelectorAll("[data-sq-font-size]").forEach(function (button) {
            if (button.dataset.sqFontBound === "true") {
                return;
            }

            button.dataset.sqFontBound = "true";

            button.addEventListener("click", function () {
                const value = button.getAttribute("data-sq-font-size") || "normal";

                if (SQ.storage) {
                    SQ.storage.set("accessibility_font_size", value);
                }

                applyAccessibilitySettings();

                speakText("Text size updated.");

                if (SQ.toast) {
                    SQ.toast("Text size updated", "info");
                }
            });
        });

        document.querySelectorAll("[data-sq-screen-reader-mode]").forEach(function (button) {
            if (button.dataset.sqScreenReaderBound === "true") {
                return;
            }

            button.dataset.sqScreenReaderBound = "true";

            button.addEventListener("click", function () {
                const current = document.documentElement.getAttribute("data-screen-reader-mode") === "true";
                const next = !current;

                if (SQ.storage) {
                    SQ.storage.set("accessibility_screen_reader_mode", next);
                }

                applyAccessibilitySettings();

                if (next) {
                    speakCurrentPageAutomatically();
                } else {
                    speakText("Screen reader mode disabled.");
                }

                if (SQ.toast) {
                    SQ.toast("Screen reader mode " + (next ? "enabled" : "disabled"), "info");
                }
            });
        });

        document.querySelectorAll("[data-sq-speak-page]").forEach(function (button) {
            if (button.dataset.sqSpeakBound === "true") {
                return;
            }

            button.dataset.sqSpeakBound = "true";

            button.addEventListener("click", function () {
                speakText(currentPageSpeechText() || "No readable page content found.");
            });
        });

        document.querySelectorAll("[data-sq-stop-speech]").forEach(function (button) {
            if (button.dataset.sqStopSpeechBound === "true") {
                return;
            }

            button.dataset.sqStopSpeechBound = "true";

            button.addEventListener("click", function () {
                stopSpeech();

                if (SQ.toast) {
                    SQ.toast("Speech stopped", "info");
                }
            });
        });
    }

    function bindDropdown() {
        document.querySelectorAll("[data-sq-dropdown]").forEach(function (trigger) {
            if (trigger.dataset.sqDropdownBound === "true") {
                return;
            }

            trigger.dataset.sqDropdownBound = "true";

            trigger.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();

                const target = trigger.getAttribute("data-sq-dropdown");
                const menu = document.querySelector(target);

                if (!menu) {
                    return;
                }

                document.querySelectorAll(".sq-dropdown-menu.is-open").forEach(function (item) {
                    if (item !== menu) {
                        item.classList.remove("is-open");
                        const itemTrigger = document.querySelector("[data-sq-dropdown='#" + item.id + "']");
                        if (itemTrigger) {
                            itemTrigger.setAttribute("aria-expanded", "false");
                        }
                    }
                });

                menu.classList.toggle("is-open");
                trigger.setAttribute("aria-expanded", menu.classList.contains("is-open") ? "true" : "false");
            });
        });

        if (document.documentElement.dataset.sqHeaderDropdownCloseBound === "true") {
            return;
        }

        document.documentElement.dataset.sqHeaderDropdownCloseBound = "true";

        document.addEventListener("click", function (event) {
            if (!event.target.closest(".sq-user-menu") && !event.target.closest(".sq-accessibility-menu")) {
                document.querySelectorAll(".sq-dropdown-menu.is-open").forEach(function (menu) {
                    menu.classList.remove("is-open");
                    const trigger = document.querySelector("[data-sq-dropdown='#" + menu.id + "']");
                    if (trigger) {
                        trigger.setAttribute("aria-expanded", "false");
                    }
                });
            }
        });
    }

    function bindLogout() {
        document.querySelectorAll("[data-sq-logout]").forEach(function (btn) {
            if (btn.dataset.sqLogoutBound === "true") {
                return;
            }

            btn.dataset.sqLogoutBound = "true";

            btn.addEventListener("click", function (event) {
                event.preventDefault();

                const confirmed = window.confirm("Are you sure you want to logout?");

                if (!confirmed) {
                    return;
                }

                if (SQ.auth && SQ.auth.logout) {
                    SQ.auth.logout();
                } else {
                    window.location.href = "/ui/login.html";
                }
            });
        });
    }

    function bindAiButton() {
        const btn = document.querySelector("#sq-ai-button");

        if (!btn) {
            return;
        }

        if (btn.dataset.sqAiBound === "true") {
            return;
        }

        btn.dataset.sqAiBound = "true";

        btn.addEventListener("click", function () {
            if (SQ.aiChatAssistant && SQ.aiChatAssistant.open) {
                SQ.aiChatAssistant.open();
                return;
            }

            window.setTimeout(function () {
                if (SQ.aiChatAssistant && SQ.aiChatAssistant.open) {
                    SQ.aiChatAssistant.open();
                }
            }, 150);
        });
    }

    function bindGlobalSearch() {
        const searchForm = document.querySelector(".sq-header-search");

        if (!searchForm) {
            return;
        }

        if (searchForm.dataset.sqSearchBound === "true") {
            return;
        }

        searchForm.dataset.sqSearchBound = "true";

        searchForm.addEventListener("submit", function (event) {
            event.preventDefault();

            const input = searchForm.querySelector("input[type='search']");
            const keyword = input ? input.value.trim() : "";

            if (!keyword) {
                return;
            }

            if (SQ.router) {
                SQ.router.go("/ui/search.html", {
                    q: keyword
                });
            }
        });
    }

    function initNotificationCount() {
        const badge = document.querySelector("#sq-notification-count");

        if (!badge) {
            return;
        }

        badge.textContent = "0";
    }

    function init() {
        applyAccessibilitySettings();
        renderUser();
        bindSidebarToggle();
        bindThemeToggle();
        bindAccessibilityControls();
        bindAutoPageSpeech();
        bindFocusSpeech();
        bindDropdown();
        bindLogout();
        bindAiButton();
        bindGlobalSearch();
        initNotificationCount();
    }

    window.SQ.header = {
        init: init,
        renderUser: renderUser
    };

    document.addEventListener("DOMContentLoaded", init);
    document.addEventListener("sq:component-loaded", function (event) {
        if (event.detail && event.detail.name === "header") {
            init();
        }
    });
document.addEventListener("click", function (event) {
    const btn = event.target.closest("[data-sq-sidebar-toggle]");

    if (!btn) {
        return;
    }

    document.body.classList.toggle("sq-sidebar-collapsed");
});
})(window, document);
