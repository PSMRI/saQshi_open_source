(function (window, document) {
    "use strict";
    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    let canManage = false;
    let selectedFacilityType = -1;
    let currentPage = 1;

    const esc = value => String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    const typeLabel = type => ({ LETTER: "Letter", FORM: "Form", DOCUMENT: "Document", GUIDELINE: "Guideline", OTHER: "Other" }[type] || "Other");
    const fileSize = value => {
        const bytes = Number(value || 0); if (!bytes) return "-";
        if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };
    const fileDate = value => { const date = new Date(String(value || "").replace(" ", "T")); return Number.isNaN(date.getTime()) ? "" : date.toLocaleDateString(); };
    const fileVisual = resource => {
        if (resource.preview_url) return `<img src="${esc(resource.preview_url)}" alt="" loading="lazy">`;
        const extension = String(resource.original_name || "").split(".").pop().toLowerCase();
        const map = { pdf: ["bi-file-earmark-pdf", "pdf"], doc: ["bi-file-earmark-word", "word"], docx: ["bi-file-earmark-word", "word"], xls: ["bi-file-earmark-excel", "excel"], xlsx: ["bi-file-earmark-excel", "excel"], csv: ["bi-file-earmark-spreadsheet", "excel"], ppt: ["bi-file-earmark-slides", "slides"], pptx: ["bi-file-earmark-slides", "slides"], mp4: ["bi-file-earmark-play", "video"], mov: ["bi-file-earmark-play", "video"], avi: ["bi-file-earmark-play", "video"], zip: ["bi-file-earmark-zip", "archive"] };
        const [icon, kind] = map[extension] || ["bi-file-earmark", "file"];
        return `<i class="bi ${icon} ${kind}" aria-hidden="true"></i>`;
    };

    function query() { return { search: document.getElementById("resourcesSearch")?.value.trim() || "", facility_type_id: selectedFacilityType >= 0 ? selectedFacilityType : "", page: currentPage, per_page: 10 }; }
    function renderTabs(types, counts) {
        const tabs = [[-1, "All"], ...types.map(type => [Number(type.fac_type_id), type.fac_type_code || type.fac_type_name])];
        document.getElementById("resourcesTabs").innerHTML = tabs.map(([id, label]) => {
            const countKey = id === -1 ? "ALL" : id;
            return `<button type="button" class="sq-resource-tab ${selectedFacilityType === id ? "is-active" : ""}" data-facility-type-id="${id}" role="tab" aria-selected="${selectedFacilityType === id}">${esc(label)}<span>${Number(counts[countKey] || 0)}</span></button>`;
        }).join("");
    }
    function renderApplicableTypes(types) {
        const select = document.getElementById("resourceApplicableType"); if (!select) return;
        const selected = select.value;
        select.innerHTML = types.map(type => `<option value="${Number(type.fac_type_id)}">${esc(type.fac_type_code || type.fac_type_name)}${Number(type.fac_type_id) === 0 ? "" : ` — ${esc(type.fac_type_name)}`}</option>`).join("");
        select.value = selected;
    }
    function renderPagination(pagination) {
        const target = document.getElementById("resourcesPagination");
        const page = Number(pagination.page || 1), pages = Number(pagination.page_count || 1), total = Number(pagination.total || 0), perPage = Number(pagination.per_page || 10);
        if (total <= perPage) { target.innerHTML = total ? `<span>Showing ${total} resource${total === 1 ? "" : "s"}</span>` : ""; return; }
        const start = (page - 1) * perPage + 1, end = Math.min(page * perPage, total);
        const numbers = Array.from({ length: pages }, (_, index) => index + 1).filter(number => number === 1 || number === pages || Math.abs(number - page) <= 1);
        let previous = 0;
        const buttons = numbers.map(number => `${previous && number - previous > 1 ? '<span class="sq-resource-page-gap">…</span>' : ""}<button type="button" class="sq-resource-page ${number === page ? "is-active" : ""}" data-resource-page="${number}" ${number === page ? "aria-current=page" : ""}>${number}</button>` + (previous = number, "")).join("");
        target.innerHTML = `<span>Showing ${start}–${end} of ${total}</span><div><button type="button" class="sq-resource-page" data-resource-page="${page - 1}" ${page === 1 ? "disabled" : ""}>Previous</button>${buttons}<button type="button" class="sq-resource-page" data-resource-page="${page + 1}" ${page === pages ? "disabled" : ""}>Next</button></div>`;
    }
    function render(resources) {
        const list = document.getElementById("resourcesList");
        document.getElementById("resourceCount").textContent = `${resources.length} resource${resources.length === 1 ? "" : "s"}`;
        if (!resources.length) { list.innerHTML = '<div class="sq-resources-empty"><i class="bi bi-folder2-open"></i><p>No resources match your search.</p></div>'; return; }
        list.innerHTML = `<div class="sq-resource-table-wrap"><table class="sq-resource-table"><thead><tr><th>Resource</th><th>File type</th><th>Applicable for</th><th>Download</th><th>Downloads</th><th>Uploaded at</th>${canManage ? "<th></th>" : ""}</tr></thead><tbody>${resources.map(resource => `<tr><td><div class="sq-resource-name-cell"><span class="sq-resource-file-visual">${fileVisual(resource)}</span><div><strong>${esc(resource.title)}</strong>${resource.description ? `<small>${esc(resource.description)}</small>` : ""}</div></div></td><td><span class="sq-resource-type">${esc(typeLabel(resource.resource_type))}</span><small>${esc(resource.original_name)} · ${esc(fileSize(resource.file_size))}</small></td><td><span class="sq-resource-applicability">${esc(resource.fac_type_code || resource.fac_type_name || "Others")}</span></td><td><a class="sq-resource-download" href="${esc(resource.download_url)}" data-resource-download title="Download ${esc(resource.title)}"><i class="bi bi-download"></i></a></td><td><span class="sq-resource-download-count" data-resource-download-count="${Number(resource.resource_id)}">${Number(resource.download_count || 0)}</span></td><td>${esc(fileDate(resource.created_on))}</td>${canManage ? `<td><button class="sq-btn sq-btn-danger" type="button" data-resource-delete="${Number(resource.resource_id)}" aria-label="Delete ${esc(resource.title)}"><i class="bi bi-trash"></i></button></td>` : ""}</tr>`).join("")}</tbody></table></div>`;
    }
    async function load() {
        const response = await SQ.api.get("/resources/v1/list.php", query(), { loader: false, showError: false });
        const data = response.data || {}; canManage = data.can_manage === true;
        document.getElementById("resourcesAdminPanel").hidden = !canManage;
        renderTabs(data.facility_types || [], data.counts || {});
        renderApplicableTypes(data.facility_types || []);
        render(Array.isArray(data.resources) ? data.resources : []);
        renderPagination(data.pagination || {});
    }
    function updateProgress(percent, label) {
        const progress = document.getElementById("resourceUploadProgress");
        const safePercent = Math.max(0, Math.min(100, Math.round(percent || 0)));
        progress.hidden = false;
        document.getElementById("resourceUploadProgressLabel").textContent = label;
        document.getElementById("resourceUploadProgressPercent").textContent = `${safePercent}%`;
        const track = progress.querySelector("[role=progressbar]");
        track.setAttribute("aria-valuenow", String(safePercent));
        document.getElementById("resourceUploadProgressBar").style.width = `${safePercent}%`;
    }

    async function csrfToken() {
        const response = await fetch("/api/auth/v1/csrf.php", { credentials: "include", headers: { Accept: "application/json" } });
        const result = await response.json();
        const token = result?.data?.csrf_token || "";
        if (!response.ok || !token) throw new Error(result?.message || "Unable to start secure upload.");
        localStorage.setItem("sq_csrf_token", token);
        return token;
    }

    function uploadWithProgress(formData, token) {
        return new Promise(function (resolve, reject) {
            const request = new XMLHttpRequest();
            request.open("POST", "/api/resources/v1/upload.php", true);
            request.withCredentials = true;
            request.setRequestHeader("Accept", "application/json");
            request.setRequestHeader("X-CSRF-TOKEN", token);
            request.upload.addEventListener("progress", function (event) {
                if (!event.lengthComputable) return;
                const percent = (event.loaded / event.total) * 100;
                updateProgress(percent, `Uploading ${fileSize(event.loaded)} of ${fileSize(event.total)}`);
            });
            request.addEventListener("load", function () {
                let result = null; try { result = JSON.parse(request.responseText || "{}"); } catch (_) { /* handled below */ }
                if (request.status >= 200 && request.status < 300 && result?.status === "success") return resolve(result);
                reject(new Error(result?.message || "Resource upload failed."));
            });
            request.addEventListener("error", () => reject(new Error("Network error during upload.")));
            request.addEventListener("abort", () => reject(new Error("Upload cancelled.")));
            request.send(formData);
        });
    }

    async function upload(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector("button[type=submit]"); button.disabled = true;
        try {
            updateProgress(0, "Preparing secure upload…");
            const token = await csrfToken();
            const payload = new FormData(form); payload.append("csrf_token", token);
            await uploadWithProgress(payload, token);
            updateProgress(100, "Published. Updating the resource list…");
            form.reset(); SQ.toast?.("Resource published for all users.", "success"); await load();
            setTimeout(() => { document.getElementById("resourceUploadProgress").hidden = true; }, 1500);
        }
        catch (error) { SQ.toast?.(error.message || "Resource upload failed.", "danger"); }
        finally { button.disabled = false; }
    }
    async function deleteResource(id) {
        if (!window.confirm("Delete this resource? It will no longer be available to users.")) return;
        try { await SQ.api.post("/resources/v1/delete.php", { resource_id: id }, { loader: true, showError: false }); SQ.toast?.("Resource deleted.", "success"); await load(); }
        catch (error) { SQ.toast?.(error.message || "Resource could not be deleted.", "danger"); }
    }
    async function downloadResource(link) {
        const response = await fetch(link.href, { credentials: "include" });
        if (!response.ok) throw new Error("Download failed.");
        const blob = await response.blob();
        const disposition = response.headers.get("content-disposition") || "";
        const match = disposition.match(/filename\*=UTF-8''([^;]+)/i) || disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? decodeURIComponent(match[1]) : "resource-download";
        const fileLink = document.createElement("a"); fileLink.href = URL.createObjectURL(blob); fileLink.download = filename;
        document.body.appendChild(fileLink); fileLink.click(); fileLink.remove(); setTimeout(() => URL.revokeObjectURL(fileLink.href), 1000);
        const row = link.closest("tr"); const count = row?.querySelector("[data-resource-download-count]");
        if (count) count.textContent = String(Number(count.textContent || 0) + 1);
    }
    async function init() {
        document.getElementById("resourceUploadForm")?.addEventListener("submit", upload);
        document.getElementById("resourcesRefresh")?.addEventListener("click", () => { currentPage = 1; load(); });
        document.getElementById("resourcesTabs")?.addEventListener("click", event => { const tab = event.target.closest("[data-facility-type-id]"); if (!tab) return; selectedFacilityType = Number(tab.dataset.facilityTypeId); currentPage = 1; load(); });
        let timer; document.getElementById("resourcesSearch")?.addEventListener("input", () => { clearTimeout(timer); timer = setTimeout(() => { currentPage = 1; load(); }, 300); });
        document.getElementById("resourcesList")?.addEventListener("click", event => { const button = event.target.closest("[data-resource-delete]"); if (button) { deleteResource(Number(button.dataset.resourceDelete)); return; } const link = event.target.closest("[data-resource-download]"); if (link) { event.preventDefault(); downloadResource(link).catch(error => SQ.toast?.(error.message || "Download failed.", "danger")); } });
        document.getElementById("resourcesPagination")?.addEventListener("click", event => { const button = event.target.closest("[data-resource-page]"); if (!button || button.disabled) return; currentPage = Number(button.dataset.resourcePage); load(); });
        await load();
    }
    SQ.resources = { init };
})(window, document);
