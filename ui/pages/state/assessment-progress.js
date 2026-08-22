/*!
 * ==========================================================
 * SaQshi Open Source
 * State Assessment Progress
 * assessment-progress.js
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;
    const state = { pager: null, rows: [] };

    function domainLabel(key, fallback) {
        return SQ.deployment && typeof SQ.deployment.label === "function"
            ? SQ.deployment.label(key, fallback)
            : fallback;
    }

    function assessmentUnitLabel() {
        return domainLabel("departments", "Departments");
    }

    function isEducationProfile() {
        const profile = SQ.deployment?.current?.domain?.profile_code || SQ.deployment?.current?.modules?.active_profile || "";
        return String(profile).toLowerCase() === "education";
    }

    function scoreDimensionLabel() {
        return isEducationProfile() ? "Domain" : "Area of Concern";
    }

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function number(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function percent(value) {
        return Math.max(0, Math.min(100, number(value)));
    }

    function progress(done, total) {
        const safeDone = number(done);
        const safeTotal = number(total);
        const pct = safeTotal > 0 ? Math.round((safeDone / safeTotal) * 100) : 0;

        return `
            <div class="sq-state-mini-progress">
                <div><span style="width:${percent(pct)}%"></span></div>
                <small>${esc(safeDone)} / ${esc(safeTotal)}</small>
            </div>
        `;
    }

    function scoreCell(row) {
        const finalScore = number(row.score_percent).toFixed(2);
        const baseline = number(row.baseline_score_percent).toFixed(2);

        return `
            <strong>${esc(finalScore)}%</strong>
            <small>Baseline ${esc(baseline)}%</small>
            <small>${esc(row.final_obtained_score || 0)} / ${esc(row.total_score || 0)}</small>
        `;
    }

    function queryParams() {
        return state.pager.params({
            search: document.getElementById("stateAssessSearch")?.value || ""
        });
    }

    function domainClass(index) {
        return ["is-domain-blue", "is-domain-teal", "is-domain-purple", "is-domain-orange", "is-domain-red"][index % 5];
    }

    function categoryBadge(category) {
        if (!isEducationProfile()) return "";
        const name = String(category?.name || "-");
        const key = name.toLowerCase();
        const css = key === "jagriti" ? "is-jagriti" : (key === "pragati" ? "is-pragati" : (key === "abhilasha" ? "is-abhilasha" : ""));
        return `<span class="sq-score-category ${css}">${esc(name)}</span>`;
    }

    function openDomainDialog(assessmentId, showRound) {
        const row = state.rows.find(function (item) { return Number(item.assessment_id) === Number(assessmentId); });
        const modelScore = showRound ? row?.round_score : row?.model_score;
        if (!modelScore?.models?.length) return;
        document.getElementById("stateDomainScoreDialog")?.remove();
        const modal = document.createElement("div");
        modal.id = "stateDomainScoreDialog";
        modal.className = "sq-state-modal";
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        const dimension = scoreDimensionLabel();
        const title = showRound ? `Assessment Round ${dimension}-wise Scores` : `${dimension}-wise Scores`;
        const overall = isEducationProfile()
            ? `${showRound ? "Whole School / Facility round average" : "Class domain average"}</span><strong>${esc(number(modelScore.percentage).toFixed(2))}% · ${esc(modelScore.category?.name || "-")}`
            : `Overall assessment score</span><strong>${esc(number(modelScore.percentage).toFixed(2))}%`;
        modal.innerHTML = `<div class="sq-state-modal-panel sq-state-domain-dialog">
            <div class="sq-card-header"><div><h3>${esc(title)}</h3><p>${esc(row.fac_name || "School / Facility")} · ${showRound ? `Round ${esc(row.round_no || row.round_id || "-")}` : esc(row.assessment_name || "Assessment")}</p></div><button type="button" class="sq-btn sq-btn-light" data-close-domain-dialog>Close</button></div>
            <div class="sq-card-body">${modelScore.models.map(function (model, index) {
                const value = Math.max(0, Math.min(100, number(model.percentage)));
                const checkpoints = model.total_checkpoints !== undefined ? `${number(model.answered_checkpoints)} / ${number(model.total_checkpoints)} checkpoints` : "Round aggregate";
                const score = model.total_score !== undefined ? `${number(model.obtained_score).toFixed(2)} / ${number(model.total_score).toFixed(2)} score` : "";
                return `<div class="sq-domain-score ${domainClass(index)}"><div class="sq-domain-score-title"><strong>${esc(model.model_name)}</strong><b>${esc(value.toFixed(2).replace(/\.00$/, ""))}%</b></div><div class="sq-domain-score-bar"><span style="width:${value}%"></span></div><div class="sq-domain-score-meta">${esc(checkpoints)}${score ? ` · ${esc(score)}` : ""}</div>${isEducationProfile() ? `<div class="sq-domain-score-category">${esc(model.category?.name || "-")}</div>` : ""}</div>`;
            }).join("")}<div class="sq-domain-score-total"><span>${overall}</strong></div></div>
        </div>`;
        document.body.appendChild(modal);
        modal.addEventListener("click", function (event) {
            if (event.target === modal || event.target.closest("[data-close-domain-dialog]")) modal.remove();
        });
    }

    function createPager() {
        if (SQ.pagination && typeof SQ.pagination.create === "function") {
            return SQ.pagination.create({ page: 1, perPage: 50, onChange: load });
        }

        return {
            params(extra) { return Object.assign({ page: 1, per_page: 50 }, extra || {}); },
            set() { return this; },
            render() { return this; },
            reset() { return this; }
        };
    }

    function bindSearch(inputId) {
        let timer = null;
        document.getElementById(inputId)?.addEventListener("input", function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                state.pager.reset();
                load();
            }, 300);
        });
    }

    function renderSummary(summary) {
        document.getElementById("stateAssessSummary").innerHTML = `
            <div><span>Total</span><strong>${esc(summary.total || 0)}</strong></div>
            <div><span>Active</span><strong>${esc(summary.active || 0)}</strong></div>
            <div><span>Completed</span><strong>${esc(summary.completed || 0)}</strong></div>
            <div><span>Cancelled</span><strong>${esc(summary.cancelled || 0)}</strong></div>
        `;
    }

    function renderRows(rows) {
        const schools = Object.values(rows.reduce(function (map, row) {
            const key = String(row.fac_id_fk || row.NIN_no || row.fac_name || "unknown");
            if (!map[key]) map[key] = { name: row.fac_name, code: row.NIN_no, district: row.district, block: row.block, rounds: {} };
            const round = String(row.round_no || row.round_id || "Unassigned");
            if (!map[key].rounds[round]) map[key].rounds[round] = [];
            map[key].rounds[round].push(row);
            return map;
        }, {}));
        document.getElementById("stateAssessRows").innerHTML = schools.length
            ? `<div class="sq-state-school-list">${schools.map(function (school) {
                return `<details class="sq-state-school-drill"><summary><strong>${esc(school.name || "School / Facility")}</strong><small>${esc(school.district || "-")} / ${esc(school.block || "-")} · ${esc(school.code || "-")}</small></summary>${Object.keys(school.rounds).map(function (round) {
                    const entries = school.rounds[round];
                    const completed = entries.filter(function (row) { return String(row.status || "").toUpperCase() === "COMPLETED"; });
                    const roundScore = completed[0]?.round_score;
                    const label = scoreDimensionLabel();
                    const roundButton = isEducationProfile() ? `View Assessment Round ${esc(round)} Domains` : `View Assessment Round ${esc(round)} ${esc(label)} Scores`;
                    const itemButton = isEducationProfile() ? "View class domains" : `View ${esc(label)} scores`;
                    return `<details class="sq-state-round-drill"><summary>Assessment Round ${esc(round)} · ${esc(completed.length)} assessed ${esc(assessmentUnitLabel())} ${roundScore ? categoryBadge(roundScore.category) : ""}</summary><div class="sq-state-round-items">${completed.length ? `<button type="button" class="sq-btn sq-btn-light" data-round-domain-dialog="${esc(completed[0].assessment_id)}">${roundButton}</button>` : ""}${entries.map(function (row) { const done = String(row.status || "").toUpperCase() === "COMPLETED"; return `<div><strong>${esc(row.assessment_name || "Assessment")}</strong><span>${esc(row.status || "-")} · ${esc(number(row.score_percent).toFixed(2))}% ${done ? categoryBadge(row.model_score?.category) : ""}</span>${done ? `<button type="button" class="sq-btn sq-btn-light" data-model-dialog="${esc(row.assessment_id)}">${itemButton}</button>` : ""}</div>`; }).join("")}</div></details>`;
                }).join("")}</details>`;
            }).join("")}</div>`
            : `<div class="sq-state-empty">No School assessment records available.</div>`;
        /* Flat assessment table retained below for future export-only use. */
        /* `<table class="sq-state-table sq-state-assess-table">
                <thead>
                    <tr>
                        <th>${esc(domainLabel("facility", "Facility"))}</th>
                        <th>Assessment</th>
                        <th>Status</th>
                        <th>${esc(domainLabel("departments", "Departments"))}</th>
                        <th>Checkpoints</th>
                        <th>Action Plans</th>
                        <th>Score</th>
                        <th>Domain Average / Category</th>
                        <th>Round Average / Category</th>
                        <th>Timeline</th>
                    </tr>
                </thead>
                <tbody>${rows.map(renderRow).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No assessment records available.</div>`; */
    }

    function renderRow(row) {
        const modelScore = row.model_score || null;
        const category = modelScore?.category?.name || "-";
        const roundScore = row.round_score || null;
        const roundCategory = roundScore?.category?.name || "-";
        return `
            <tr>
                <td>
                    <strong>${esc(row.fac_name || "-")}</strong>
                    <small>${esc(row.district || "-")} / ${esc(row.block || "-")}</small>
                    <small>${esc(domainLabel("facility_code", "NIN"))} ${esc(row.NIN_no || "-")}</small>
                </td>
                <td>
                    <strong>${esc(row.assessment_name || "-")}</strong>
                    <small>ID ${esc(row.assessment_id || "-")} | ${esc(row.framework_code || "-")}</small>
                    ${row.round_id ? `<small>Round ${esc(row.round_no || row.round_id)}</small>` : ""}
                    ${row.is_latest ? `<span class="sq-state-badge sq-state-latest">Latest</span>` : ""}
                </td>
                <td><span class="sq-state-badge">${esc(row.status || "-")}</span></td>
                <td>
                    ${progress(row.completed_departments || 0, row.total_departments || 0)}
                    <small>Left ${esc(row.pending_departments || 0)}</small>
                </td>
                <td>
                    ${progress(row.checkpoint_done || 0, row.total_checkpoints || 0)}
                    <small>Left ${esc(row.checkpoint_left || 0)}</small>
                </td>
                <td>
                    ${progress(row.completed_action_plans || 0, row.total_action_plans || 0)}
                    <small>Left ${esc(row.pending_action_plans || 0)}</small>
                </td>
                <td>${scoreCell(row)}</td>
                <td>
                    ${modelScore ? `
                        <strong>${esc(number(modelScore.percentage).toFixed(2))}%</strong>
                        <small>${esc(category)} · equal average of domains</small>
                        <button type="button" class="sq-btn sq-btn-light sq-state-domain-button" data-model-dialog="${esc(row.assessment_id)}">View five domains</button>` : "-"}
                </td>
                <td>
                    ${roundScore ? `<strong>${esc(number(roundScore.percentage).toFixed(2))}%</strong><small>${esc(roundCategory)} · ${esc(roundScore.completed_classes_departments)} completed</small><button type="button" class="sq-btn sq-btn-light sq-state-domain-button" data-round-domain-dialog="${esc(row.assessment_id)}">View round domains</button>` : "-"}
                </td>
                <td>
                    <small>Start ${esc(row.start_date || "-")}</small>
                    <small>Planned end ${esc(row.end_date || "-")}</small>
                    ${row.completed_on ? `<small>Completed ${esc(row.completed_on)}</small>` : ""}
                    ${row.cancelled_on ? `<small>Cancelled ${esc(row.cancelled_on)}</small>` : ""}
                </td>
            </tr>
        `;
    }

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/assessment_progress.php", queryParams(), {
                loader: false,
                showError: false
            });
            const data = response.data || {};

            state.rows = data.rows || [];
            renderSummary(data.summary || {});
            renderRows(state.rows);
            state.pager.set(data.pagination || {}).render("stateAssessPager", "Showing");
        } catch (error) {
            document.getElementById("stateAssessSummary").innerHTML = "";
            document.getElementById("stateAssessRows").innerHTML =
                `<div class="sq-state-empty">${esc(error.message || "Unable to load assessment progress.")}</div>`;
            state.pager.set({ page: 1, total_pages: 1, total_rows: 0 }).render("stateAssessPager", "Showing");
        }
    }

    async function init() {
        try {
            if (SQ.deployment && typeof SQ.deployment.load === "function") {
                await SQ.deployment.load();
                SQ.deployment.applyLabels(document);
                const facility = SQ.deployment.label ? SQ.deployment.label("facility", "Facility") : "Facility";
                const pageSubtitle = document.getElementById("sq-page-subtitle");
                if (pageSubtitle) pageSubtitle.textContent = `${facility}-wise assessment history and status.`;
            }
            state.pager = createPager();
            document.getElementById("stateAssessRefresh")?.addEventListener("click", function () {
                state.pager.reset();
                load();
            });
            bindSearch("stateAssessSearch");
            document.addEventListener("click", function (event) {
                const button = event.target.closest("[data-model-dialog]");
                if (button) openDomainDialog(button.getAttribute("data-model-dialog"));
                const roundButton = event.target.closest("[data-round-domain-dialog]");
                if (roundButton) openDomainDialog(roundButton.getAttribute("data-round-domain-dialog"), true);
            });
            await load();
        } catch (error) {
            document.getElementById("stateAssessRows").innerHTML = `<div class="sq-state-empty">${esc(error.message || "Unable to initialize assessment progress.")}</div>`;
        }
    }

    SQ.stateAssessmentProgress = { init };
})(window, document);
