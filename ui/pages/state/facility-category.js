/*!
 * ==========================================================
 * SaQshi Open Source
 * State Facility Categorization
 * facility-category.js
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function setHtml(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function renderFacilityTypes(rows) {
        setHtml("stateFacilityTypeRows", rows && rows.length
            ? `<table class="sq-state-table">
                <thead><tr><th>Facility Type</th><th>Count</th><th>%</th></tr></thead>
                <tbody>${rows.map(row => `
                    <tr>
                        <td>${esc(row.facility_type || "-")}</td>
                        <td>${esc(row.count || 0)}</td>
                        <td>${esc(row.percentage ?? "-")}</td>
                    </tr>
                `).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No facility type data available.</div>`);
    }

    function renderDistricts(rows) {
        setHtml("stateDistrictRows", rows && rows.length
            ? `<table class="sq-state-table sq-state-district-table">
                <thead><tr><th></th><th>District</th><th>Total Facilities</th></tr></thead>
                <tbody>${rows.map((row, index) => renderDistrictRow(row, index)).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No district data available.</div>`);
    }

    function renderDistrictRow(row, index) {
        const blocks = Array.isArray(row.blocks) ? row.blocks : [];
        const targetId = `stateDistrictBreakup${index}`;

        return `
            <tr>
                <td class="sq-state-toggle-cell">
                    <button class="sq-state-plus" type="button" data-state-district-toggle="${esc(targetId)}" aria-expanded="false">+</button>
                </td>
                <td><strong>${esc(row.district || "-")}</strong><br><small>${esc(blocks.length)} blocks</small></td>
                <td>${esc(row.count || 0)}</td>
            </tr>
            <tr id="${esc(targetId)}" class="sq-state-district-detail" hidden>
                <td></td>
                <td colspan="2">${renderBlocks(blocks)}</td>
            </tr>
        `;
    }

    function renderBlocks(blocks) {
        return blocks && blocks.length
            ? `<div class="sq-state-block-list">
                ${blocks.map(block => `
                    <div class="sq-state-block-row">
                        <div>
                            <strong>${esc(block.block || "-")}</strong>
                            <small>${esc(block.count || 0)} facilities</small>
                        </div>
                        <div class="sq-state-type-chips">${renderTypeChips(block.facility_types)}</div>
                    </div>
                `).join("")}
            </div>`
            : `<div class="sq-state-empty">No block breakup available for this district.</div>`;
    }

    function renderTypeChips(types) {
        return types && types.length
            ? types.map(type => `<span>${esc(type.facility_type || "-")}: <b>${esc(type.count || 0)}</b></span>`).join("")
            : `<span>Unknown: <b>0</b></span>`;
    }

    function bindDistrictToggle() {
        document.getElementById("stateDistrictRows")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-state-district-toggle]");
            if (!button) return;

            const target = document.getElementById(button.dataset.stateDistrictToggle);
            if (!target) return;

            const willOpen = target.hidden;
            target.hidden = !willOpen;
            button.textContent = willOpen ? "-" : "+";
            button.setAttribute("aria-expanded", willOpen ? "true" : "false");
        });
    }

    function searchParams() {
        return {
            search: document.getElementById("stateFacilitySearch")?.value || ""
        };
    }

    function bindSearch() {
        let timer = null;
        document.getElementById("stateFacilitySearch")?.addEventListener("input", function () {
            clearTimeout(timer);
            timer = setTimeout(load, 300);
        });
    }

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/facility_category.php", searchParams(), {
                loader: false,
                showError: false
            });
            const data = response.data || {};

            renderFacilityTypes(data.facility_types || []);
            renderDistricts(data.districts || []);
        } catch (error) {
            setHtml("stateFacilityTypeRows", `<div class="sq-state-empty">Unable to load facility type data.</div>`);
            setHtml("stateDistrictRows", `<div class="sq-state-empty">${esc(error.message || "Unable to load district breakup.")}</div>`);
        }
    }

    async function init() {
        bindDistrictToggle();
        document.getElementById("stateFacilityRefresh")?.addEventListener("click", load);
        bindSearch();
        await load();
    }

    SQ.stateFacilityCategory = { init };
})(window, document);
