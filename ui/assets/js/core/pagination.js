/*!
 * ==========================================================
 * SaQshi Open Source
 * Global Table Pagination Helper
 * pagination.js
 * Version 1.0.0 | Updated 2026-07-13
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    if (window.SQ.pagination && typeof window.SQ.pagination.create === "function") {
        return;
    }

    function toInt(value, fallback) {
        const parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
    }

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function create(options) {
        const state = {
            page: toInt(options?.page, 1),
            perPage: toInt(options?.perPage, 50),
            totalRows: toInt(options?.totalRows, 0),
            totalPages: toInt(options?.totalPages, 1)
        };
        const onChange = typeof options?.onChange === "function" ? options.onChange : function () {};

        function set(pagination) {
            state.page = toInt(pagination?.page, state.page);
            state.perPage = toInt(pagination?.per_page ?? pagination?.perPage, state.perPage);
            state.totalRows = Math.max(0, toInt(pagination?.total_rows ?? pagination?.totalRows, 0));
            state.totalPages = Math.max(1, toInt(pagination?.total_pages ?? pagination?.totalPages, 1));
            if (state.page > state.totalPages) state.page = state.totalPages;
            return api;
        }

        function params(extra) {
            return Object.assign({}, extra || {}, {
                page: state.page,
                per_page: state.perPage
            });
        }

        function render(container, label) {
            const el = typeof container === "string" ? document.getElementById(container) : container;
            if (!el) return api;

            const from = state.totalRows ? ((state.page - 1) * state.perPage) + 1 : 0;
            const to = Math.min(state.page * state.perPage, state.totalRows);

            el.innerHTML = state.totalRows
                ? `<div class="sq-state-pager" data-sq-pagination>
                    <span>${esc(label || "Showing")} ${esc(from)}-${esc(to)} of ${esc(state.totalRows)}</span>
                    <div>
                        <button class="sq-btn sq-btn-light" type="button" data-sq-page="prev" ${state.page <= 1 ? "disabled" : ""}>Previous</button>
                        <strong>Page ${esc(state.page)} / ${esc(state.totalPages)}</strong>
                        <button class="sq-btn sq-btn-light" type="button" data-sq-page="next" ${state.page >= state.totalPages ? "disabled" : ""}>Next</button>
                    </div>
                </div>`
                : "";

            el.querySelector("[data-sq-page='prev']")?.addEventListener("click", function () {
                if (state.page <= 1) return;
                state.page--;
                onChange(state);
            });
            el.querySelector("[data-sq-page='next']")?.addEventListener("click", function () {
                if (state.page >= state.totalPages) return;
                state.page++;
                onChange(state);
            });

            return api;
        }

        function reset() {
            state.page = 1;
            return api;
        }

        function getState() {
            return Object.assign({}, state);
        }

        const api = { set, params, render, reset, state: getState };
        return api;
    }

    window.SQ.pagination = { create };
})(window, document);
