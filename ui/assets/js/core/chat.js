/*!
 * ==========================================================
 * SQ Chat Assistant v1.0
 * ----------------------------------------------------------
 * Project  : SaQshi Open Source
 * Module   : Frontend Chat / AI Assistant UI
 * File     : chat.js
 * License  : GPL-3.0
 * ==========================================================
 *
 * PURPOSE
 * ----------------------------------------------------------
 * Provides a reusable chat assistant widget for SaQshi.
 *
 * Used for:
 * - Ask SaQshi help
 * - Explain checklist point
 * - Explain NQAS / MusQan / LaQshya terms
 * - Suggest action plan
 * - Explain gap analysis
 * - Help user during assessment
 * - Future AI integration
 *
 * NOTE
 * ----------------------------------------------------------
 * This file only handles frontend chat UI and client logic.
 * Actual AI response should come from backend endpoint:
 *
 *      /api/ai/v1/chat.php
 *
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    const CONFIG = {
        endpoint: "/ai/v1/chat.php",
        title: "Ask SaQshi",
        subtitle: "Quality assessment assistant",
        storageKey: "sq_chat_history",
        maxHistory: 50,
        welcomeMessage: "नमस्ते, मैं SaQshi Assistant हूँ। आप assessment, gap analysis, action plan या report से जुड़ा सवाल पूछ सकते हैं।"
    };

    let isOpen = false;
    let isSending = false;

    function saveHistory(messages) {
        if (SQ.storage) {
            SQ.storage.set(CONFIG.storageKey, messages);
        } else {
            localStorage.setItem(CONFIG.storageKey, JSON.stringify(messages));
        }
    }

    function getHistory() {
        if (SQ.storage) {
            return SQ.storage.get(CONFIG.storageKey, []);
        }

        try {
            return JSON.parse(localStorage.getItem(CONFIG.storageKey)) || [];
        } catch (e) {
            return [];
        }
    }

    function clearHistory() {
        if (SQ.storage) {
            SQ.storage.remove(CONFIG.storageKey);
        } else {
            localStorage.removeItem(CONFIG.storageKey);
        }
    }

    function escape(value) {
        if (SQ.escape) {
            return SQ.escape(value);
        }

        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    function createWidget() {
        if (document.querySelector("#sq-chat-widget")) {
            return;
        }

        const wrapper = document.createElement("div");
        wrapper.id = "sq-chat-widget";
        wrapper.className = "sq-chat-widget";
        wrapper.innerHTML = `
            <button
                type="button"
                class="sq-chat-fab"
                id="sq-chat-toggle"
                aria-label="Open SaQshi assistant"
                aria-expanded="false"
            >
                ?
            </button>

            <section
                class="sq-chat-panel"
                id="sq-chat-panel"
                role="dialog"
                aria-labelledby="sq-chat-title"
                aria-modal="false"
                hidden
            >
                <header class="sq-chat-header">
                    <div>
                        <h2 class="sq-chat-title" id="sq-chat-title">${escape(CONFIG.title)}</h2>
                        <p class="sq-chat-subtitle">${escape(CONFIG.subtitle)}</p>
                    </div>

                    <div class="sq-chat-header-actions">
                        <button type="button" class="sq-chat-icon-btn" id="sq-chat-clear" aria-label="Clear chat">
                            ⟳
                        </button>
                        <button type="button" class="sq-chat-icon-btn" id="sq-chat-close" aria-label="Close chat">
                            ×
                        </button>
                    </div>
                </header>

                <div class="sq-chat-body" id="sq-chat-body" aria-live="polite"></div>

                <div class="sq-chat-suggestions" id="sq-chat-suggestions">
                    <button type="button" data-sq-chat-suggestion="Explain this checkpoint">
                        Explain checkpoint
                    </button>
                    <button type="button" data-sq-chat-suggestion="Suggest action plan">
                        Suggest action plan
                    </button>
                    <button type="button" data-sq-chat-suggestion="Explain gap analysis">
                        Explain gap
                    </button>
                </div>

                <form class="sq-chat-form" id="sq-chat-form">
                    <textarea
                        class="sq-chat-input"
                        id="sq-chat-input"
                        rows="1"
                        placeholder="Type your question..."
                        aria-label="Chat message"
                    ></textarea>

                    <button type="submit" class="sq-chat-send" id="sq-chat-send">
                        Send
                    </button>
                </form>
            </section>
        `;

        document.body.appendChild(wrapper);

        bindEvents();
        renderHistory();
    }

    function bindEvents() {
        const toggle = document.querySelector("#sq-chat-toggle");
        const close = document.querySelector("#sq-chat-close");
        const clear = document.querySelector("#sq-chat-clear");
        const form = document.querySelector("#sq-chat-form");
        const input = document.querySelector("#sq-chat-input");

        toggle.addEventListener("click", toggleChat);
        close.addEventListener("click", closeChat);

        clear.addEventListener("click", function () {
            if (window.confirm("Clear chat history?")) {
                clearHistory();
                renderHistory();
            }
        });

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const message = input.value.trim();

            if (!message) {
                return;
            }

            input.value = "";
            sendMessage(message);
        });

        input.addEventListener("keydown", function (event) {
            if (event.key === "Enter" && !event.shiftKey) {
                event.preventDefault();
                form.dispatchEvent(new Event("submit"));
            }
        });

        document.querySelectorAll("[data-sq-chat-suggestion]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                sendMessage(btn.getAttribute("data-sq-chat-suggestion"));
            });
        });
    }

    function openChat() {
        const panel = document.querySelector("#sq-chat-panel");
        const toggle = document.querySelector("#sq-chat-toggle");

        if (!panel || !toggle) {
            return;
        }

        panel.hidden = false;
        panel.classList.add("is-open");
        toggle.setAttribute("aria-expanded", "true");

        isOpen = true;

        const input = document.querySelector("#sq-chat-input");

        if (input) {
            input.focus();
        }

        scrollToBottom();
    }

    function closeChat() {
        const panel = document.querySelector("#sq-chat-panel");
        const toggle = document.querySelector("#sq-chat-toggle");

        if (!panel || !toggle) {
            isOpen = false;
            return;
        }

        panel.classList.remove("is-open");
        panel.hidden = true;
        toggle.setAttribute("aria-expanded", "false");

        isOpen = false;
    }

    function toggleChat() {
        if (isOpen) {
            closeChat();
        } else {
            openChat();
        }
    }

    function addMessage(role, text, meta = {}) {
        const history = getHistory();

        history.push({
            role: role,
            text: text,
            meta: meta,
            time: new Date().toISOString()
        });

        const trimmed = history.slice(-CONFIG.maxHistory);
        saveHistory(trimmed);

        renderHistory();
    }

    function renderHistory() {
        const body = document.querySelector("#sq-chat-body");

        if (!body) {
            return;
        }

        const history = getHistory();

        body.innerHTML = "";

        if (!history.length) {
            renderMessage("assistant", CONFIG.welcomeMessage, {
                time: new Date().toISOString()
            });
            return;
        }

        history.forEach(function (msg) {
            renderMessage(msg.role, msg.text, msg);
        });

        scrollToBottom();
    }

    function renderMessage(role, text, meta = {}) {
        const body = document.querySelector("#sq-chat-body");

        if (!body) {
            return;
        }

        const message = document.createElement("div");
        message.className = "sq-chat-message sq-chat-message-" + role;

        const time = meta.time
            ? new Date(meta.time).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
            : "";

        message.innerHTML = `
            <div class="sq-chat-bubble">
                ${escape(text).replace(/\n/g, "<br>")}
            </div>
            <div class="sq-chat-time">${escape(time)}</div>
        `;

        body.appendChild(message);
    }

    function showTyping() {
        const body = document.querySelector("#sq-chat-body");

        const typing = document.createElement("div");
        typing.className = "sq-chat-message sq-chat-message-assistant";
        typing.id = "sq-chat-typing";
        typing.innerHTML = `
            <div class="sq-chat-bubble">
                Typing...
            </div>
        `;

        body.appendChild(typing);
        scrollToBottom();
    }

    function hideTyping() {
        const typing = document.querySelector("#sq-chat-typing");

        if (typing) {
            typing.remove();
        }
    }

    async function sendMessage(message, context = {}) {
        if (isSending) {
            return;
        }

        isSending = true;

        addMessage("user", message);

        showTyping();

        try {
            let response;

            if (SQ.api) {
                response = await SQ.api.post(
                    CONFIG.endpoint,
                    {
                        message: message,
                        context: context,
                        history: getHistory()
                    },
                    {
                        loader: false,
                        showError: false
                    }
                );
            }

            hideTyping();

            const reply =
                response?.data?.reply ||
                response?.reply ||
                "I received your question. AI response endpoint is not connected yet.";

            addMessage("assistant", reply, {
                source: response?.data?.source || null
            });

        } catch (error) {
            hideTyping();

            addMessage(
                "assistant",
                "Sorry, I could not connect to the assistant service. Please try again later."
            );
        } finally {
            isSending = false;
        }
    }

    function scrollToBottom() {
        const body = document.querySelector("#sq-chat-body");

        if (body) {
            body.scrollTop = body.scrollHeight;
        }
    }

    SQ.chat = {
        config: function (settings = {}) {
            Object.assign(CONFIG, settings);
        },

        init: createWidget,
        open: openChat,
        close: closeChat,
        toggle: toggleChat,
        send: sendMessage,
        clear: function () {
            clearHistory();
            renderHistory();
        },
        history: getHistory
    };

    document.addEventListener("DOMContentLoaded", function () {
        if (document.body && document.body.hasAttribute("data-enable-legacy-chat")) {
            createWidget();
        }
    });

})(window, document);
