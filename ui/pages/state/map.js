/*!
 * ==========================================================
 * SaQshi Open Source
 * State Certification Map
 * map.js
 * Version 1.1.0 | Updated 2026-07-10
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    let map = null;
    let tileLayer = null;
    let markerLayer = null;
    let boundaryLayer = null;
    let boundaryMaskLayer = null;
    let boundaryBounds = null;
    let boundaryData = null;
    let districtScoreMap = {};
    let activeMapMode = "facility";
    let activeDomain = "";
    let mapListPoints = [];
    let mapListPage = 1;
    // Keep the details panel compact so the map remains the focus of the page.
    const MAP_LIST_PAGE_SIZE = 5;

    function resetMap() {
        if (map && typeof map.remove === "function") {
            try {
                map.remove();
            } catch (error) {
                console.warn("[State Map] Previous map cleanup skipped", error);
            }
        }

        map = null;
        tileLayer = null;
        markerLayer = null;
        boundaryLayer = null;
        boundaryMaskLayer = null;
        boundaryBounds = null;
        boundaryData = null;
        districtScoreMap = {};
    }

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function color(status) {
        const value = String(status || "").toUpperCase();
        if (value === "CERTIFIED") return "#16a34a";
        if (value === "CONDITIONAL") return "#f59e0b";
        if (value === "EXPIRED") return "#dc2626";
        return "#2563eb";
    }

    function categoryColor(category) {
        const value = String(category || "").toUpperCase();
        if (value === "ABHILASHA") return "#dc2626";
        if (value === "PRAGATI") return "#f59e0b";
        if (value === "JAGRITI") return "#16a34a";
        return "#cbd5e1";
    }

    function districtKey(value) {
        const key = String(value || "").trim().toUpperCase().replace(/[^A-Z0-9]+/g, "_");
        return ({ PASHCHIM_CHAMPARAN: "WEST_CHAMPARAN", PURBA_CHAMPARAN: "EAST_CHAMPARAN" })[key] || key;
    }

    function isSchool() { return String(SQ.deployment?.label?.("facility", "Facility") || "Facility").toLowerCase() === "school"; }
    function isEducationProfile() {
        const profile = SQ.deployment?.current?.domain?.profile_code || SQ.deployment?.current?.modules?.active_profile || "";
        return String(profile).toLowerCase() === "education";
    }
    function facilityLabel() { return SQ.deployment?.label?.("facility", "Facility") || "Facility"; }
    function facilityCodeLabel() { return SQ.deployment?.label?.("facility_code", "NIN") || "NIN"; }

    function setHtml(id, value) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = value;
    }

    function popup(point) {
        return `
            <div class="sq-state-map-popup">
                <strong>${esc(point.fac_name || facilityLabel())}</strong>
                <span>${esc(point.facility_type || "-")} | ${esc(facilityCodeLabel())} ${esc(point.fac_nin || "-")}</span>
                <span>${esc(point.district || "-")} ${point.block ? " / " + esc(point.block) : ""}</span>
                <span>Status: <b>${esc(point.status || "-")}</b></span>
                ${isSchool() ? `<span>Assessment: <b>${esc(point.assessment_name || "Not started")}</b></span><span>Assessment status: <b>${esc(point.assessment_status || "NOT STARTED")}</b></span>` : ""}
                <span>Score: <b>${point.score !== null && point.score !== undefined ? esc(point.score) : "-"}</b></span>
                <span>Valid To: <b>${esc(point.valid_to || "-")}</b></span>
                <button class="sq-btn sq-btn-primary sq-state-map-detail-btn" type="button" data-map-facility="${esc(point.fac_id)}">Open ${esc(facilityLabel())} Details</button>
            </div>
        `;
    }

    function ensureMap(config) {
        if (!window.L) {
            setHtml("stateMapCanvas", `<div class="sq-state-empty">Map library could not load. Please check Leaflet assets.</div>`);
            return null;
        }

        if (map) return map;

        const center = Array.isArray(config.center) ? config.center : [26.8467, 80.9462];
        map = L.map("stateMapCanvas", {
            center,
            zoom: Number(config.zoom) || 7,
            minZoom: Number(config.min_zoom) || 5,
            maxZoom: Number(config.max_zoom) || 18
        });

        tileLayer = L.tileLayer(config.tile_url || "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: config.attribution || "&copy; OpenStreetMap contributors",
            maxZoom: Number(config.max_zoom) || 18,
            noWrap: true
        }).addTo(map);

        markerLayer = L.layerGroup().addTo(map);

        window.requestAnimationFrame(function () {
            if (map) map.invalidateSize();
        });

        return map;
    }

    async function renderBoundary(config, force) {
        if (!map || !config.boundary_url) return boundaryBounds;
        if (boundaryLayer && !force) return boundaryBounds;

        try {
            if (!boundaryData) {
                const response = await fetch(config.boundary_url, { cache: "no-store" });
                const payload = await response.json();
                boundaryData = payload && payload.status === "success" && payload.data ? payload.data : payload;
            }
            const data = boundaryData;

            if (data.type !== "FeatureCollection") return;

            renderBoundaryMask(data);
            if (boundaryLayer) map.removeLayer(boundaryLayer);
            boundaryLayer = L.geoJSON(data, {
                style: function (feature) {
                    const district = feature?.properties?.district || feature?.properties?.DISTRICT || feature?.properties?.Dist_Name || feature?.properties?.DIST_NAME || "";
                    const score = districtScoreMap[districtKey(district)];
                    return {
                        color: "#334155",
                        weight: activeMapMode === "domain" ? 1.5 : 1,
                        fillColor: activeMapMode === "domain" ? categoryColor(score?.category?.name) : "#dbeafe",
                        fillOpacity: activeMapMode === "domain" ? (score ? 0.72 : 0.28) : 0.08
                    };
                },
                onEachFeature: function (feature, layer) {
                    if (activeMapMode !== "domain") return;
                    const district = feature?.properties?.district || feature?.properties?.DISTRICT || feature?.properties?.Dist_Name || feature?.properties?.DIST_NAME || "District";
                    const score = districtScoreMap[districtKey(district)];
                    const details = score
                        ? `<strong>${esc(district)}</strong><br>${esc(activeDomain)}<br>Score: <b>${esc(score.percentage)}%</b><br>Category: <b>${esc(score.category?.name || "-")}</b><br>Schools assessed: <b>${esc(score.school_count)}</b>`
                        : `<strong>${esc(district)}</strong><br>No score available for ${esc(activeDomain)}.`;
                    layer.bindTooltip(details, { sticky: true, opacity: 0.96 });
                }
            }).addTo(map);
            boundaryBounds = boundaryLayer.getBounds();
            boundaryLayer.bringToFront();
            applyConfiguredBounds();
        } catch (error) {
            console.warn("[State Map] Boundary skipped", error);
        }

        return boundaryBounds;
    }

    function renderBoundaryMask(data) {
        if (boundaryMaskLayer || !window.L || !data || data.type !== "FeatureCollection") return;

        const holes = [];
        (data.features || []).forEach(function (feature) {
            const geometry = feature && feature.geometry;
            if (!geometry || !Array.isArray(geometry.coordinates)) return;

            if (geometry.type === "Polygon") {
                addMaskHole(holes, geometry.coordinates[0]);
            } else if (geometry.type === "MultiPolygon") {
                geometry.coordinates.forEach(function (polygon) {
                    addMaskHole(holes, polygon && polygon[0]);
                });
            }
        });

        if (!holes.length) return;

        const world = [[-90, -360], [-90, 360], [90, 360], [90, -360]];
        boundaryMaskLayer = L.polygon([world].concat(holes), {
            stroke: false,
            fillColor: "#f8fafc",
            fillOpacity: 0.72,
            interactive: false
        }).addTo(map);
    }

    function addMaskHole(holes, ring) {
        if (!Array.isArray(ring) || ring.length < 4) return;

        const hole = ring
            .map(function (coordinate) {
                const lng = Number(coordinate && coordinate[0]);
                const lat = Number(coordinate && coordinate[1]);
                return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
            })
            .filter(Boolean);

        if (hole.length >= 4) holes.push(hole);
    }

    function applyConfiguredBounds() {
        if (!map || !boundaryBounds || !boundaryBounds.isValid()) return false;

        const paddedBounds = boundaryBounds.pad(0.08);
        map.setMaxBounds(paddedBounds);
        map.options.maxBoundsViscosity = 1.0;
        map.fitBounds(boundaryBounds, { padding: [18, 18], maxZoom: 9 });
        return true;
    }

    function configuredAreaPoints(points) {
        if (!boundaryBounds || !boundaryBounds.isValid()) return points;

        return points.filter(function (point) {
            const lat = Number(point.lat);
            const lng = Number(point.longit);
            return Number.isFinite(lat) && Number.isFinite(lng) && boundaryBounds.contains([lat, lng]);
        });
    }

    function renderList(points) {
        mapListPoints = points;
        const totalPages = Math.max(1, Math.ceil(points.length / MAP_LIST_PAGE_SIZE));
        mapListPage = Math.min(Math.max(1, mapListPage), totalPages);
        const start = (mapListPage - 1) * MAP_LIST_PAGE_SIZE;
        const pagePoints = points.slice(start, start + MAP_LIST_PAGE_SIZE);
        const subject = isSchool() ? "schools with saved GPS coordinates" : `certified facilit${points.length === 1 ? "y" : "ies"}`;
        setHtml("stateMapSummary", `${esc(points.length)} ${subject} ${points.length === 1 ? "is" : "are"} shown inside the configured map boundary.`);
        setHtml("stateMapList", points.length
            ? `<table class="sq-state-table">
                <caption class="sq-sr-only">Mapped locations with status, district and coordinates.</caption>
                <thead><tr><th>${esc(facilityLabel())}</th><th>Status</th><th>District</th><th>Coordinates</th></tr></thead>
                <tbody>${pagePoints.map(point => `
                    <tr>
                        <td><strong>${esc(point.fac_name)}</strong><br><small>${esc(facilityCodeLabel())} ${esc(point.fac_nin || "-")}</small><br><button class="sq-btn sq-btn-light sq-state-map-list-detail" type="button" data-map-facility="${esc(point.fac_id)}">View details</button></td>
                        <td><span class="sq-state-badge">${esc(point.status)}</span></td>
                        <td>${esc(point.district || "-")}<br><small>${esc(point.block || "")}</small></td>
                        <td>${esc(point.lat)}, ${esc(point.longit)}</td>
                    </tr>
                `).join("")}</tbody>
            </table>
            <div class="sq-state-pager"><span>Showing ${start + 1}-${Math.min(start + MAP_LIST_PAGE_SIZE, points.length)} of ${points.length}</span><div><button class="sq-btn sq-btn-muted" type="button" data-map-page="${mapListPage - 1}" ${mapListPage === 1 ? "disabled" : ""}>Previous</button><strong>Page ${mapListPage} / ${totalPages}</strong><button class="sq-btn sq-btn-muted" type="button" data-map-page="${mapListPage + 1}" ${mapListPage === totalPages ? "disabled" : ""}>Next</button></div></div>`
            : `<div class="sq-state-empty">No ${esc(facilityLabel().toLowerCase())} locations found inside configured map boundary.</div>`);
    }

    function renderMarkers(points) {
        if (!map || !markerLayer) return;

        markerLayer.clearLayers();
        let bounds = null;

        points.forEach(function (point) {
            const lat = Number(point.lat);
            const lng = Number(point.longit);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

            const marker = L.circleMarker([lat, lng], {
                radius: 3,
                color: "#ffffff",
                weight: 0.75,
                fillColor: color(point.status),
                fillOpacity: 0.86,
                opacity: 0.9
            }).bindPopup(popup(point));

            if (isSchool()) {
                marker.bindTooltip(`<strong>${esc(point.fac_name)}</strong><br>${esc(facilityCodeLabel())}: ${esc(point.fac_nin || "-")}<br>Assessment: ${esc(point.assessment_name || "Not started")}<br>Status: ${esc(point.assessment_status || "NOT STARTED")}`, { direction: "top", sticky: true, opacity: 0.95 });
            }

            marker.addTo(markerLayer);
            bounds = bounds ? bounds.extend([lat, lng]) : L.latLngBounds([[lat, lng]]);
        });

        if (applyConfiguredBounds()) {
            return;
        }

        if (bounds && bounds.isValid()) {
            map.fitBounds(bounds, { padding: [24, 24], maxZoom: 9 });
        }
    }

    function renderStats(data) {
        const status = data.status || [];
        const categories = data.certification_categories || [];
        setHtml("stateMapCategories", categories.length
            ? categories
                .filter(item => item.type !== "UNKNOWN" || Number(item.count) > 0)
                .map(item => `
                    <div>
                        <span>${esc(item.type)}</span>
                        <strong>${esc(item.count || 0)}</strong>
                    </div>
                `).join("")
            : `<div class="sq-state-empty">No State/National certification category found.</div>`);
        setHtml("stateMapStatus", status.length
            ? status.map(item => `<span class="sq-state-dot" style="background:${color(item.status)}">${esc(item.status)}: ${esc(item.count)}</span>`).join("")
            : `<span class="sq-state-dot">No certified coordinates</span>`);
    }

    function renderDomainLegend() {
        const legend = document.getElementById("stateMapLegend");
        const listCard = document.getElementById("stateMapListCard");
        if (legend) {
            legend.hidden = activeMapMode !== "domain";
            legend.innerHTML = activeMapMode === "domain" ? `<strong>${esc(activeDomain)}</strong><span><i style="background:#dc2626"></i>Abhilasha (&lt;60%)</span><span><i style="background:#f59e0b"></i>Pragati (60-75%)</span><span><i style="background:#16a34a"></i>Jagriti (&gt;75%)</span><span><i style="background:#cbd5e1"></i>No score</span>` : "";
        }
        if (listCard) listCard.hidden = activeMapMode === "domain";
    }

    function populateDomains(domains) {
        const select = document.getElementById("stateMapDomain");
        if (!select || !Array.isArray(domains)) return;
        const previous = select.value || activeDomain;
        select.innerHTML = `<option value="">Select domain</option>${domains.map(name => `<option value="${esc(name)}">${esc(name)}</option>`).join("")}`;
        if (domains.includes(previous)) select.value = previous;
    }

    async function load() {
        try {
            activeMapMode = isEducationProfile() ? (document.getElementById("stateMapMode")?.value || "facility") : "facility";
            activeDomain = document.getElementById("stateMapDomain")?.value || "";
            const response = await SQ.api.get("/state/v1/map.php", {
                _: Date.now(),
                search: document.getElementById("stateMapSearch")?.value || "",
                map_mode: activeMapMode,
                domain: activeDomain
            }, {
                loader: false,
                showError: false
            });
            const data = response.data || {};
            const points = data.map_points || [];
            const config = data.map_config || {};
            populateDomains(data.domain_options || []);
            districtScoreMap = {};
            (data.district_domain_scores || []).forEach(item => { districtScoreMap[districtKey(item.district)] = item; });

            ensureMap(config);
            await renderBoundary(config, true);
            const visiblePoints = configuredAreaPoints(points);
            renderMarkers(visiblePoints);
            if (activeMapMode === "facility") renderList(visiblePoints);
            else setHtml("stateMapSummary", `${esc(Object.keys(districtScoreMap).length)} districts are coloured by ${esc(activeDomain)} category. Hover over a district to view its score.`);
            renderDomainLegend();
        } catch (error) {
            console.error("[State Map]", error);
            setHtml("stateMapList", `<div class="sq-state-empty">${esc(error.message || "Unable to load certification map.")}</div>`);
        }
    }

    async function init() {
        if (SQ.deployment && typeof SQ.deployment.load === "function") {
            await SQ.deployment.load();
            SQ.deployment.applyLabels(document);
        }
        resetMap();
        const mapMode = document.getElementById("stateMapMode");
        const domainSelect = document.getElementById("stateMapDomain");
        if (!isEducationProfile()) {
            if (mapMode) mapMode.hidden = true;
            if (domainSelect) domainSelect.hidden = true;
        }
        const fullMap = new URLSearchParams(window.location.search).get("full") === "1";
        document.querySelector(".sq-state-map-page")?.classList.toggle("is-full-map", fullMap);
        document.getElementById("stateMapCanvas")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-map-facility]");
            if (button && SQ.router?.navigate) SQ.router.navigate("state/facility-detail", { fac_id: Number(button.getAttribute("data-map-facility")) });
        });
        document.getElementById("stateMapList")?.addEventListener("click", function (event) {
            const button = event.target.closest("[data-map-page]");
            if (button && !button.disabled) {
                mapListPage = Number(button.getAttribute("data-map-page"));
                renderList(mapListPoints);
                return;
            }
            const facilityButton = event.target.closest("[data-map-facility]");
            if (facilityButton && SQ.router?.navigate) SQ.router.navigate("state/facility-detail", { fac_id: Number(facilityButton.getAttribute("data-map-facility")) });
        });
        document.getElementById("stateMapRefresh")?.addEventListener("click", function () { mapListPage = 1; load(); });
        document.getElementById("stateMapMode")?.addEventListener("change", function () {
            const domainSelect = document.getElementById("stateMapDomain");
            const domainMode = this.value === "domain";
            if (domainSelect) {
                domainSelect.disabled = !domainMode;
                if (domainMode && !domainSelect.value && domainSelect.options.length > 1) domainSelect.selectedIndex = 1;
            }
            mapListPage = 1;
            load();
        });
        document.getElementById("stateMapDomain")?.addEventListener("change", function () { if (this.value) load(); });
        document.getElementById("stateMapOpenFull")?.addEventListener("click", function () {
            window.open("/ui/dashboard.html?route=state%2Fmap&full=1", "_blank", "noopener");
        });
        let searchTimer = null;
        document.getElementById("stateMapSearch")?.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { mapListPage = 1; load(); }, 300);
        });
        await new Promise(resolve => window.requestAnimationFrame(resolve));
        await load();
    }

    SQ.stateMap = { init };
})(window, document);
