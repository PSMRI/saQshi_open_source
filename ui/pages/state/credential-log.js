(function (window, document) {
    "use strict";
    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    let rows = [];

    function esc(value) { return String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;"); }
    function csv(value) { return `"${String(value ?? "").replace(/\"/g, '""')}"`; }

    function render() {
        const target = document.getElementById("credentialLogRows");
        if (!target) return;
        target.innerHTML = rows.length ? `<table class="sq-state-table"><thead><tr><th>Name</th><th>Mobile</th><th>Email</th><th>Mapped School / District</th><th>Username</th><th>Temporary Password</th><th>Delivered</th><th>Status</th></tr></thead><tbody>${rows.map(row => `<tr><td>${esc(row.name || "-")}</td><td>${esc(row.mobile_no || "-")}</td><td>${esc(row.email)}</td><td>${esc(row.mapped_schools || "Not mapped")}</td><td>${esc(row.username)}</td><td><code>${esc(row.temporary_password)}</code></td><td>${esc(row.created_at)}</td><td>${esc(row.status)}</td></tr>`).join("")}</tbody></table>` : '<div class="sq-state-empty">No credential delivery records found.</div>';
    }

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/credential_delivery_log.php", { search: document.getElementById("credentialLogSearch")?.value.trim() || "" }, { loader: true, showError: false });
            rows = response.data?.rows || [];
            render();
        } catch (error) { SQ.notification?.error(error.message || "Unable to load credential delivery records."); }
    }

    function download() {
        if (!rows.length) return;
        const content = [["name", "mobile_no", "email", "mapped_school_district", "username", "temporary_password", "delivered_at", "status"], ...rows.map(row => [row.name, row.mobile_no, row.email, row.mapped_schools, row.username, row.temporary_password, row.created_at, row.status])].map(row => row.map(csv).join(",")).join("\r\n");
        const url = URL.createObjectURL(new Blob([content], { type: "text/csv;charset=utf-8" }));
        const link = document.createElement("a"); link.href = url; link.download = "assessor-credential-delivery-log.csv"; link.click(); URL.revokeObjectURL(url);
    }

    SQ.stateCredentialLog = { init: function () {
        const user = SQ.auth?.getUser?.();
        if (Number(user?.role_id) !== 11) { SQ.notification?.error("This page is available only to role 11."); return; }
        document.getElementById("credentialLogRefresh")?.addEventListener("click", load);
        document.getElementById("credentialLogDownload")?.addEventListener("click", download);
        let timer; document.getElementById("credentialLogSearch")?.addEventListener("input", function () { clearTimeout(timer); timer = setTimeout(load, 300); });
        return load();
    }};
})(window, document);
