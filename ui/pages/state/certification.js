/*!
 * ==========================================================
 * SaQshi Open Source
 * State Certification Status
 * certification.js
 * Version 1.2.0 | Updated 2026-07-10
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const state = {
        facilities: [],
        status: [],
        warning: "",
        pager: null
    };

    const statuses = ["CONDITIONAL", "CERTIFIED"];

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function html(id, value) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = value;
    }

    function text(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function renderWarning() {
        html("stateCertWarning", state.warning
            ? `<div class="sq-alert sq-alert-warning"><div class="sq-alert-content"><div class="sq-alert-title">Certification history notice</div><div class="sq-alert-text">${esc(state.warning)}</div></div></div>`
            : "");
    }

    function renderCards() {
        const counts = {};
        state.status.forEach(row => {
            counts[row.status] = Number(row.count) || 0;
        });

        const cards = [
            ["TOTAL FACILITIES", state.status.reduce((sum, row) => sum + (Number(row.count) || 0), 0)],
            ["CERTIFIED", counts.CERTIFIED || 0],
            ["CONDITIONAL", counts.CONDITIONAL || 0],
            ["NOT CERTIFIED", counts["NOT CERTIFIED"] || 0]
        ];

        html("stateCertCards", cards.map(card => `
            <div>
                <span>${esc(card[0])}</span>
                <strong>${esc(card[1])}</strong>
            </div>
        `).join(""));
    }

    function renderSummary() {
        html("stateCertRows", state.status.length
            ? `<table class="sq-state-table">
                <thead><tr><th>Status</th><th>Facilities</th><th>%</th></tr></thead>
                <tbody>${state.status.map(row => `
                    <tr>
                        <td>${esc(row.status)}</td>
                        <td>${esc(row.count)}</td>
                        <td>${esc(row.percentage ?? 0)}%</td>
                    </tr>
                `).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No certification status available.</div>`);
    }

    function renderChart() {
        const max = Math.max(...state.status.map(row => Number(row.count) || 0), 1);

        html("stateCertChart", state.status.length
            ? `<div class="sq-state-chart-bars">${state.status.map(row => `
                <div class="sq-state-chart-row">
                    <span>${esc(row.status)}</span>
                    <div><i style="width:${Math.max(3, (Number(row.count) || 0) * 100 / max)}%"></i></div>
                    <b>${esc(row.count || 0)}</b>
                </div>
            `).join("")}</div>`
            : `<div class="sq-state-empty">No comparison data.</div>`);
    }

    function renderTable() {
        const visibleRows = state.facilities;
        const pagerState = state.pager ? state.pager.state() : { totalRows: visibleRows.length };
        text("stateCertCount", `${pagerState.totalRows} facilities`);

        html("stateCertTable", visibleRows.length
            ? `<table class="sq-state-table">
                <thead>
                    <tr>
                        <th>Facility</th>
                        <th>NIN</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Certification</th>
                        <th>Score</th>
                        <th>Renewal</th>
                        <th>History</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>${visibleRows.map(row => `
                    <tr>
                        <td><strong>${esc(row.fac_name || "-")}</strong><br><small>${esc(row.facility_type || "-")} | ID ${esc(row.fac_id || "-")}</small></td>
                        <td>${esc(row.fac_nin || "-")}</td>
                        <td>${esc(row.district || "-")}<br><small>${esc(row.block || "")}</small></td>
                        <td><span class="sq-state-badge">${esc(row.status || "NOT CERTIFIED")}</span></td>
                        <td>${esc(row.certification_type || "-")}<br><small>${esc(row.assessment_mode || "-")} | ${esc(row.certification_date || "-")} ${row.valid_to ? `to ${esc(row.valid_to)}` : ""}</small><br><small>Applied ${esc(row.applied_date || "-")}</small></td>
                        <td>${row.score !== null && row.score !== undefined ? esc(row.score) : "-"}</td>
                        <td>${esc(row.renewal_status || "-")}<br><small>${row.valid_from ? `From ${esc(row.valid_from)}` : ""}</small></td>
                        <td>${esc(row.last_action || "-")}<br><small>${esc(row.last_updated_on || "")}</small></td>
                        <td>
                            <div class="sq-state-inline-update">
                                <button class="sq-btn sq-btn-primary" data-cert-update="${esc(row.fac_id)}">Update</button>
                            </div>
                        </td>
                    </tr>
                `).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No facilities match the selected filter.</div>`);

        renderPager();
    }

    function renderPager() {
        if (state.pager) {
            state.pager.render("stateCertPagerTop", "Showing");
            state.pager.render("stateCertPager", "Showing");
        }
    }

    function renderAll() {
        renderWarning();
        renderCards();
        renderSummary();
        renderChart();
        renderTable();
    }

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/certification_summary.php", state.pager.params({
                search: document.getElementById("stateCertSearch")?.value || "",
                status: document.getElementById("stateCertFilter")?.value || "",
                _: Date.now()
            }), {
                loader: false,
                showError: false
            });

            state.facilities = response.data?.facilities || [];
            state.status = response.data?.status || [];
            state.warning = response.data?._warning || "";
            state.pager.set(response.data?.pagination || {
                page: 1,
                per_page: 50,
                total_rows: state.facilities.length,
                total_pages: 1
            });

            renderAll();
        } catch (error) {
            console.error(error);
            html("stateCertTable", `<div class="sq-state-empty">${esc(error.message || "Unable to load certification history.")}</div>`);
        }
    }

    function setValue(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value ?? "";
    }

    function openUpdateModal(facId) {
        const row = state.facilities.find(item => Number(item.fac_id) === Number(facId));
        if (!row) return;

        html("stateCertEditStatus", statuses.map(status => `<option value="${esc(status)}" ${row.status === status ? "selected" : ""}>${esc(status)}</option>`).join(""));
        setValue("stateCertFacId", row.fac_id);
        setValue("stateCertFacilityName", row.fac_name || "");
        setValue("stateCertFacilityNin", row.fac_nin || "");
        setValue("stateCertType", row.certification_type || "STATE");
        setValue("stateCertAssessmentMode", row.assessment_mode || "PHYSICAL");
        setValue("stateCertDate", row.certification_date || "");
        setValue("stateCertAppliedDate", row.applied_date || "");
        setValue("stateCertScore", row.score ?? "");
        setValue("stateCertRemarks", row.remarks || "");

        const modal = document.getElementById("stateCertModal");
        if (modal) modal.hidden = false;
    }

    function closeUpdateModal() {
        const modal = document.getElementById("stateCertModal");
        if (modal) modal.hidden = true;
    }

    async function saveUpdate(event) {
        event.preventDefault();
        const facId = document.getElementById("stateCertFacId")?.value || "";
        const row = state.facilities.find(item => Number(item.fac_id) === Number(facId));
        if (!row) return;
        const certDate = document.getElementById("stateCertDate")?.value || "";
        const appliedDate = document.getElementById("stateCertAppliedDate")?.value || "";
        const today = new Date().toISOString().slice(0, 10);

        if (certDate && certDate > today) {
            if (SQ.notification) SQ.notification.warning("Certification date cannot be a future date.");
            return;
        }

        if (appliedDate && certDate && appliedDate > certDate) {
            if (SQ.notification) SQ.notification.warning("Applied date cannot be greater than certification date.");
            return;
        }

        try {
            await SQ.api.post("/state/v1/certification_update.php", {
                fac_id: row.fac_id,
                fac_nin: row.fac_nin,
                fac_name: row.fac_name,
                fac_type: row.facility_type,
                status: document.getElementById("stateCertEditStatus")?.value || "",
                certification_type: document.getElementById("stateCertType")?.value || "",
                assessment_mode: document.getElementById("stateCertAssessmentMode")?.value || "",
                certification_date: certDate,
                applied_date: appliedDate,
                score: document.getElementById("stateCertScore")?.value || "",
                remarks: document.getElementById("stateCertRemarks")?.value || ""
            }, { loader: true });

            if (SQ.notification) SQ.notification.success("Certification status updated");
            closeUpdateModal();
            await load();
        } catch (error) {
            if (SQ.notification) SQ.notification.error(error.message || "Update failed");
        }
    }

    function downloadCsv() {
        const rows = state.facilities;
        const header = [
            "Facility ID",
            "Facility Name",
            "NIN",
            "Division",
            "District",
            "Block",
            "Facility Type",
            "Status",
            "Certification Type",
            "Assessment Mode",
            "Certification Date",
            "Applied Date",
            "Valid From",
            "Valid To",
            "Renewal Status",
            "Score",
            "Last Action",
            "Last Updated",
            "Remarks"
        ];
        const body = rows.map(row => [
            row.fac_id,
            row.fac_name,
            row.fac_nin,
            row.division,
            row.district,
            row.block,
            row.facility_type,
            row.status,
            row.certification_type,
            row.assessment_mode,
            row.certification_date,
            row.applied_date,
            row.valid_from,
            row.valid_to,
            row.renewal_status,
            row.score,
            row.last_action,
            row.last_updated_on,
            row.remarks
        ]);
        const lines = [header, ...body]
            .map(row => row.map(value => `"${String(value ?? "").replace(/"/g, '""')}"`).join(","))
            .join("\r\n");
        const blob = new Blob([lines], { type: "text/csv;charset=utf-8" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `state-certification-status-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    async function init() {
        document.getElementById("stateCertRefresh")?.addEventListener("click", load);
        let searchTimer = null;
        document.getElementById("stateCertSearch")?.addEventListener("input", function () {
            state.pager.reset();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(load, 300);
        });
        document.getElementById("stateCertFilter")?.addEventListener("change", function () {
            state.pager.reset();
            load();
        });
        document.getElementById("stateCertDownload")?.addEventListener("click", downloadCsv);
        document.getElementById("stateCertForm")?.addEventListener("submit", saveUpdate);
        document.getElementById("stateCertModalClose")?.addEventListener("click", closeUpdateModal);
        document.getElementById("stateCertCancel")?.addEventListener("click", closeUpdateModal);
        document.addEventListener("click", function (event) {
            const button = event.target.closest("[data-cert-update]");
            if (button) openUpdateModal(button.getAttribute("data-cert-update"));

        });

        state.pager = SQ.pagination.create({
            page: 1,
            perPage: 50,
            onChange: load
        });
        await load();
    }

    SQ.stateCertification = { init };
})(window, document);
