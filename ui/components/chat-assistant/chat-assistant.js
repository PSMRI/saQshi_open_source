(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    const state = { open: false, sending: false, history: [], initialized: false };

    function $(id) { return document.getElementById(id); }
    function esc(value) {
        return String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
    function currentRoute() {
        return SQ.router?.state?.currentRoute || new URLSearchParams(window.location.search).get("route") || "dashboard";
    }
    function render() {
        const target = $("sqAiChatMessages");
        if (!target) return;
        const rows = state.history.length ? state.history : [{
            role: "assistant",
            message: "Hi. I can help with assessment, checklist, CQI, KPI/Outcome, reports and certification workflows."
        }];
        target.innerHTML = rows.map(row => `
            <div class="sq-ai-chat-msg is-${esc(row.role === "user" ? "user" : "assistant")}">${esc(row.message)}</div>
        `).join("");
        target.scrollTop = target.scrollHeight;
    }
    function setOpen(open) {
        state.open = open;
        const panel = $("sqAiChatPanel");
        const toggle = $("sqAiChatToggle");
        if (panel) panel.hidden = !open;
        if (toggle) toggle.setAttribute("aria-expanded", open ? "true" : "false");
        if (open) $("sqAiChatInput")?.focus();
    }
    async function loadHistory() {
        try {
            const res = await SQ.api.get("/chat/v1/history.php", {}, { loader: false, showError: false, redirectOnUnauthorized: false });
            state.history = res.data?.history || [];
            render();
        } catch (error) {
            render();
        }
    }
    async function send(message) {
        if (state.sending || !message.trim()) return;
        state.sending = true;
        state.history.push({ role: "user", message: message.trim() });
        render();
        try {
            const res = await SQ.api.post("/chat/v1/send.php", {
                message: message.trim(),
                context_page: currentRoute()
            }, { loader: false, showError: false });
            state.history = res.data?.history || state.history.concat([{ role: "assistant", message: res.data?.reply || "Done." }]);
        } catch (error) {
            state.history.push({ role: "assistant", message: error.message || "Unable to reach AI Chat Assistant." });
        } finally {
            state.sending = false;
            render();
        }
    }
    async function clear() {
        try {
            await SQ.api.post("/chat/v1/clear.php", {}, { loader: false, showError: false });
        } catch (error) {
            /* local clear still helps the user */
        }
        state.history = [];
        render();
    }
    function bind() {
        $("sqAiChatToggle")?.addEventListener("click", function () { setOpen(!state.open); });
        $("sqAiChatClose")?.addEventListener("click", function () { setOpen(false); });
        $("sqAiChatClear")?.addEventListener("click", clear);
        $("sqAiChatForm")?.addEventListener("submit", function (event) {
            event.preventDefault();
            const input = $("sqAiChatInput");
            const message = input?.value || "";
            if (input) input.value = "";
            send(message);
        });
        document.querySelectorAll("[data-ai-chat-suggestion]").forEach(btn => {
            btn.addEventListener("click", function () {
                send(btn.getAttribute("data-ai-chat-suggestion") || "");
            });
        });
    }
    function init() {
        if (state.initialized || !$("sqAiChat")) return;
        state.initialized = true;
        bind();
        render();
        loadHistory();
    }

    SQ.aiChatAssistant = { init, open: () => setOpen(true), close: () => setOpen(false), send };

    document.addEventListener("sq:component-loaded", function (event) {
        if (event.detail?.name === "chat-assistant") init();
    });
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
