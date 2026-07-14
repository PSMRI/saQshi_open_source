(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    function $(id) { return document.getElementById(id); }
    function esc(value) {
        return String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
    function chip(value) {
        const key = String(value || "").toLowerCase();
        return `<span class="sq-cert-chip is-${esc(key)}">${esc(value || "-")}</span>`;
    }
    function setText(id, value) {
        const el = $(id);
        if (el) el.textContent = value;
    }
    async function apiGet(endpoint, params) {
        return SQ.api.get(endpoint, params || {}, { loader: false, showError: false });
    }
    async function apiPost(endpoint, data) {
        return SQ.api.post(endpoint, data || {}, { loader: true });
    }
    function todayIso() {
        return new Date().toISOString().slice(0, 10);
    }
    function notify(type, message) {
        if (SQ.notification && typeof SQ.notification[type] === "function") {
            SQ.notification[type](message);
            return;
        }
        if (SQ.toast) SQ.toast(message, type);
    }
    function setValue(id, value) {
        const el = $(id);
        if (el) el.value = value ?? "";
    }
    async function loadAssignedFacility() {
        const response = await apiGet("/admin/v1/facilities.php");
        const facility = response.data?.facility || {};

        setValue("certFacilityId", facility.fac_id || "");
        setValue("certFacilityName", facility.fac_name || "");
        setValue("certFacilityNin", facility.nin_no || facility.NIN_no || "");
        setValue("certFacilityType", facility.facilities_type || facility.Health_facilty_type || facility.fac_type_id || "");
        setValue("certDistrictName", facility.Dist_Name || facility.dist_name || "");
        setValue("certBlockName", facility.Block_Name || facility.block_name || "");
        setValue("certDistrictId", facility.dist_id || facility.district_id || "");
        setValue("certBlockId", facility.block_id || "");
        setValue("certStateId", facility.state_id || "");
    }
    function renderRecord(row) {
        return `
            <div class="sq-cert-record">
                <div class="sq-cert-record-head">
                    <strong>${esc(row.certification_type || "-")}</strong>
                    ${chip(row.status)}
                </div>
                <div class="sq-cert-meta">
                    <span>Mode <b>${esc(row.assessment_mode || "-")}</b></span>
                    <span>Score <b>${esc(row.score ?? "-")}</b></span>
                    <span>Applied On <b>${esc(row.applied_date || "-")}</b></span>
                    <span>Certified On <b>${esc(row.certification_date || "-")}</b></span>
                    <span>Valid To <b>${esc(row.valid_to || "-")}</b></span>
                    <span>Renewal <b>${esc(row.renewal_status || "-")}</b></span>
                    <span>Facility <b>${esc(row.fac_name || row.fac_nin || "-")}</b></span>
                </div>
            </div>
        `;
    }
    async function loadDashboard() {
        const response = await apiGet("/certification/v1/dashboard.php");
        const data = response.data || {};
        const latest = data.latest || {};
        const rows = Object.keys(latest).map(key => latest[key]);

        setText("certStateStatus", data.state_status || "-");
        setText("certNationalStatus", data.national_status || "-");
        setText("certNextExpiry", data.next_expiry?.valid_to || "-");
        setText("certRenewalStatus", data.renewal_status || "-");
        setText("certRecordCount", `${data.records_count || 0} records`);

        const target = $("certLatestRows");
        if (target) {
            target.innerHTML = rows.length ? rows.map(renderRecord).join("") : `<div class="sq-cert-empty">No certification records available.</div>`;
        }
    }
    function payloadFromForm(form) {
        const data = Object.fromEntries(new FormData(form).entries());
        data.score = Number(data.score);
        return data;
    }
    function syncAppliedDateLimit() {
        const certDate = $("certificationDate");
        const appliedDate = $("certAppliedDate");

        if (!appliedDate) return;

        appliedDate.max = certDate?.value || todayIso();

        if (appliedDate.value && certDate?.value && appliedDate.value > certDate.value) {
            appliedDate.value = "";
        }
    }
    async function saveCertification(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const certDate = String(form.elements.certification_date?.value || "");
        const appliedDate = String(form.elements.applied_date?.value || "");

        if (certDate && certDate > todayIso()) {
            notify("warning", "Certification date cannot be a future date.");
            return;
        }

        if (appliedDate && certDate && appliedDate > certDate) {
            notify("warning", "Applied date cannot be greater than certification date.");
            return;
        }

        if (!String(form.elements.fac_nin?.value || "").trim()) {
            notify("warning", "Facility NIN was not found for the logged-in facility.");
            return;
        }

        const result = await apiPost("/certification/v1/save.php", payloadFromForm(form));
        notify("success", result.message || "Certification saved.");
        form.reset();
        if (SQ.router) SQ.router.navigate("certification/dashboard");
    }
    async function loadHistory(targetId, renewalOnly) {
        const response = await apiGet(renewalOnly ? "/certification/v1/renewal_status.php" : "/certification/v1/history.php");
        const rows = response.data || [];
        const target = $(targetId);
        if (!target) return;

        if (!rows.length) {
            target.innerHTML = `<div class="sq-cert-empty">No certification records available.</div>`;
            return;
        }

        target.innerHTML = `
            <div class="sq-cert-table-wrap">
                <table class="sq-cert-table">
                    <thead>
                        <tr>
                            <th>Type</th><th>Status</th><th>Mode</th><th>Score</th><th>Applied Date</th><th>Certification Date</th><th>Valid To</th><th>Renewal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map(row => `
                            <tr>
                                <td>${esc(row.certification_type || "-")}</td>
                                <td>${chip(row.status || row.renewal_status)}</td>
                                <td>${esc(row.assessment_mode || "-")}</td>
                                <td>${esc(row.score ?? "-")}</td>
                                <td>${esc(row.applied_date || "-")}</td>
                                <td>${esc(row.certification_date || "-")}</td>
                                <td>${esc(row.valid_to || "-")}</td>
                                <td>${chip(row.renewal_status)}</td>
                            </tr>
                        `).join("")}
                    </tbody>
                </table>
            </div>
        `;
    }
    SQ.certificationDashboard = {
        init() {
            $("btnCertRefresh")?.addEventListener("click", loadDashboard);
            return loadDashboard().catch(console.error);
        }
    };
    SQ.certificationManage = {
        init() {
            const date = $("certificationDate");
            if (date) {
                date.max = todayIso();
                date.addEventListener("change", syncAppliedDateLimit);
            }
            syncAppliedDateLimit();
            $("certManageForm")?.addEventListener("submit", saveCertification);
            return loadAssignedFacility().catch(function (error) {
                console.error(error);
                notify("error", error.message || "Unable to load assigned facility.");
            });
        }
    };
    SQ.certificationHistory = {
        init() {
            return loadHistory("certHistoryRows", false).catch(console.error);
        }
    };
    SQ.certificationRenewal = {
        init() {
            return loadHistory("certRenewalRows", true).catch(console.error);
        }
    };
})(window, document);
