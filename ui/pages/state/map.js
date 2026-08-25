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
    let activeMapMode = "presence";
    let activeArea = "";
    let activeSubtype = "";
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
        if (value === "CRITICAL") return "#dc2626";
        if (value === "NEEDS IMPROVEMENT") return "#f59e0b";
        if (value === "SATISFACTORY") return "#16a34a";
        if (value === "CERTIFIED") return "#16a34a";
        if (value === "CONDITIONAL") return "#f59e0b";
        if (value === "EXPIRED") return "#dc2626";
        return "#2563eb";
    }

    function facilityMarkerStyle(facilityType) {
        const type = String(facilityType || "").trim().toUpperCase();
        if (type === "DH" || type.includes("DISTRICT HOSPITAL")) return { shape: "triangle", color: "#dc2626", label: "DH" };
        if (type === "CHC" || type.includes("COMMUNITY HEALTH")) return { shape: "circle", color: "#7c3aed", label: "CHC" };
        if (type === "PHC" || type.includes("PRIMARY HEALTH")) return { shape: "square", color: "#2563eb", label: "PHC" };
        if (type === "AAM" || type.includes("AAM")) return { shape: "diamond", color: "#ea580c", label: "AAM" };
        return { shape: "hexagon", color: "#64748b", label: "Other" };
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

        markerLayer = typeof L.markerClusterGroup === "function"
            ? L.markerClusterGroup({
                maxClusterRadius: 48,
                disableClusteringAtZoom: 13,
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true,
                removeOutsideVisibleBounds: true,
                chunkedLoading: true,
                chunkInterval: 80,
                chunkDelay: 16
            }).addTo(map)
            : L.layerGroup().addTo(map);

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
                    const areaMode = activeMapMode === "area_of_concern" && activeArea;
                    return {
                        color: "#334155",
                        weight: areaMode ? 1.5 : 1,
                        fillColor: areaMode ? (score ? color(score.status) : "#cbd5e1") : "#dbeafe",
                        fillOpacity: areaMode ? (score ? 0.5 : 0.14) : 0.08
                    };
                },
                onEachFeature: function (feature, layer) {
                    if (activeMapMode !== "area_of_concern" || !activeArea) return;
                    const district = feature?.properties?.district || feature?.properties?.DISTRICT || feature?.properties?.Dist_Name || feature?.properties?.DIST_NAME || "District";
                    const score = districtScoreMap[districtKey(district)];
                    const detail = score
                        ? `<strong>${esc(district)}</strong><br>${esc(activeArea)}<br>Latest-assessment score: <b>${esc(score.percentage)}%</b><br>Status: <b>${esc(score.status)}</b><br>Facilities assessed: <b>${esc(score.facility_count)}</b>`
                        : `<strong>${esc(district)}</strong><br>No latest-assessment score for ${esc(activeArea)}.`;
                    layer.bindTooltip(detail, { sticky: true, opacity: 0.96 });
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

            const facilityStyle = facilityMarkerStyle(point.facility_type);
            const markerColor = activeMapMode === "area_of_concern" && activeArea
                ? color(point.status)
                : facilityStyle.color;
            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: "sq-state-map-icon",
                    html: `<span class="sq-state-map-marker sq-state-map-marker--${facilityStyle.shape}" style="--marker-color:${markerColor}" aria-label="${esc(facilityStyle.label)}"></span>`,
                    iconSize: [18, 18],
                    iconAnchor: [9, 9],
                    popupAnchor: [0, -10]
                })
            }).bindPopup(popup(point));

            marker.bindTooltip(`<strong>${esc(point.fac_name)}</strong><br>Type: ${esc(point.facility_type || facilityStyle.label)}<br>${esc(facilityCodeLabel())}: ${esc(point.fac_nin || "-")}<br>${activeMapMode === "area_of_concern" && activeArea ? `Score: ${esc(point.score ?? "-")}%<br>` : ""}Status: ${esc(point.status || "-")}`, { direction: "top", sticky: true, opacity: 0.95 });

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

    function renderMapLegend() {
        const legend = document.getElementById("stateMapLegend");
        const listCard = document.getElementById("stateMapListCard");
        if (legend) {
            legend.hidden = false;
            const typeLegend = `<strong>Facility type</strong><span><b class="sq-state-map-marker sq-state-map-marker--triangle" style="--marker-color:#dc2626"></b>DH</span><span><b class="sq-state-map-marker sq-state-map-marker--circle" style="--marker-color:#7c3aed"></b>CHC</span><span><b class="sq-state-map-marker sq-state-map-marker--square" style="--marker-color:#2563eb"></b>PHC</span><span><b class="sq-state-map-marker sq-state-map-marker--diamond" style="--marker-color:#ea580c"></b>AAM</span><span><b class="sq-state-map-marker sq-state-map-marker--hexagon" style="--marker-color:#64748b"></b>Other</span>`;
            const scoreLegend = activeMapMode === "area_of_concern" ? `<strong>${esc(activeArea || "Area of Concern")} — latest assessment score (markers and districts)</strong><span><i style="background:#dc2626"></i>Critical (0-25%)</span><span><i style="background:#f59e0b"></i>Needs improvement (26-60%)</span><span><i style="background:#16a34a"></i>Satisfactory (&gt;60%)</span>` : "";
            legend.innerHTML = typeLegend + scoreLegend;
        }
        if (listCard) listCard.hidden = false;
    }

    function populateAreas(areas) {
        const select = document.getElementById("stateMapArea");
        if (!select || !Array.isArray(areas)) return;
        const previous = select.value || activeArea;
        select.innerHTML = `<option value="">Select Area of Concern</option>${areas.map(name => `<option value="${esc(name)}">${esc(name)}</option>`).join("")}`;
        if (areas.includes(previous)) select.value = previous;
    }

    function populateSubtypes(subtypes) {
        const select = document.getElementById("stateMapSubtype");
        if (!select || !subtypes || typeof subtypes !== "object") return;
        const previous = select.value || activeSubtype;
        select.innerHTML = `<option value="">All subtypes</option>${Object.entries(subtypes).map(([value, label]) => `<option value="${esc(value)}">${esc(label)}</option>`).join("")}`;
        if (Object.prototype.hasOwnProperty.call(subtypes, previous)) select.value = previous;
    }

    async function load() {
        try {
            activeMapMode = document.getElementById("stateMapMode")?.value || "presence";
            activeArea = document.getElementById("stateMapArea")?.value || "";
            activeSubtype = document.getElementById("stateMapSubtype")?.value || "";
            const response = await SQ.api.get("/state/v1/map.php", {
                _: Date.now(),
                search: document.getElementById("stateMapSearch")?.value || "",
                map_mode: activeMapMode,
                area_of_concern: activeArea,
                area_subtype: activeSubtype
            }, {
                loader: false,
                showError: false
            });
            const data = response.data || {};
            const points = data.map_points || [];
            const config = data.map_config || {};
            populateAreas(data.area_options || []);
            populateSubtypes(data.subtype_options || {});
            districtScoreMap = {};
            (data.district_area_scores || []).forEach(item => { districtScoreMap[districtKey(item.district)] = item; });

            ensureMap(config);
            await renderBoundary(config, true);
            const visiblePoints = configuredAreaPoints(points);
            renderMarkers(visiblePoints);
            renderList(visiblePoints);
            if (activeMapMode === "area_of_concern" && !activeArea) {
                setHtml("stateMapSummary", "Choose an Area of Concern to show facility scores on the map.");
            } else if (activeMapMode === "area_of_concern") {
                setHtml("stateMapSummary", `${esc(visiblePoints.length)} mapped facilities are coloured by their ${esc(activeArea)} score.`);
            }
            renderMapLegend();
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
        const areaSelect = document.getElementById("stateMapArea");
        const subtypeSelect = document.getElementById("stateMapSubtype");
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
            const areaMode = this.value === "area_of_concern";
            if (areaSelect) {
                areaSelect.hidden = !areaMode;
                areaSelect.disabled = !areaMode;
            }
            if (subtypeSelect) { subtypeSelect.hidden = !areaMode; subtypeSelect.disabled = !areaMode; }
            mapListPage = 1;
            load();
        });
        areaSelect?.addEventListener("change", function () { if (subtypeSelect) subtypeSelect.value = ""; load(); });
        subtypeSelect?.addEventListener("change", function () { load(); });
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
