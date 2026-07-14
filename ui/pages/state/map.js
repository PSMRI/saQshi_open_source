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

    function setHtml(id, value) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = value;
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function popup(point) {
        return `
            <div class="sq-state-map-popup">
                <strong>${esc(point.fac_name || "Facility")}</strong>
                <span>${esc(point.facility_type || "-")} | NIN ${esc(point.fac_nin || "-")}</span>
                <span>${esc(point.district || "-")} ${point.block ? " / " + esc(point.block) : ""}</span>
                <span>Status: <b>${esc(point.status || "-")}</b></span>
                <span>Score: <b>${point.score !== null && point.score !== undefined ? esc(point.score) : "-"}</b></span>
                <span>Valid To: <b>${esc(point.valid_to || "-")}</b></span>
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

    async function renderBoundary(config) {
        if (!map || !config.boundary_url) return boundaryBounds;
        if (boundaryLayer) return boundaryBounds;

        try {
            const response = await fetch(config.boundary_url, { cache: "no-store" });
            const payload = await response.json();
            const data = payload && payload.status === "success" && payload.data ? payload.data : payload;

            if (data.type !== "FeatureCollection") return;

            renderBoundaryMask(data);
            boundaryLayer = L.geoJSON(data, {
                style: {
                    color: "#334155",
                    weight: 1,
                    fillColor: "#dbeafe",
                    fillOpacity: 0.08
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
        if (tileLayer) {
            tileLayer.options.bounds = paddedBounds;
            tileLayer.redraw();
        }
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
        setHtml("stateMapList", points.length
            ? `<table class="sq-state-table">
                <thead><tr><th>Facility</th><th>Status</th><th>District</th><th>Coordinates</th></tr></thead>
                <tbody>${points.slice(0, 80).map(point => `
                    <tr>
                        <td><strong>${esc(point.fac_name)}</strong><br><small>NIN ${esc(point.fac_nin || "-")}</small></td>
                        <td><span class="sq-state-badge">${esc(point.status)}</span></td>
                        <td>${esc(point.district || "-")}<br><small>${esc(point.block || "")}</small></td>
                        <td>${esc(point.lat)}, ${esc(point.longit)}</td>
                    </tr>
                `).join("")}</tbody>
            </table>`
            : `<div class="sq-state-empty">No certified facilities found inside configured map boundary.</div>`);
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
                radius: 8,
                color: "#ffffff",
                weight: 2,
                fillColor: color(point.status),
                fillOpacity: 0.92
            }).bindPopup(popup(point));

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

    async function load() {
        try {
            const response = await SQ.api.get("/state/v1/map.php", {
                _: Date.now(),
                search: document.getElementById("stateMapSearch")?.value || ""
            }, {
                loader: false,
                showError: false
            });
            const data = response.data || {};
            const points = data.map_points || [];
            const config = data.map_config || {};

            ensureMap(config);
            await renderBoundary(config);
            const visiblePoints = configuredAreaPoints(points);
            renderMarkers(visiblePoints);
            renderStats(data);
            renderList(visiblePoints);
        } catch (error) {
            console.error("[State Map]", error);
            setHtml("stateMapList", `<div class="sq-state-empty">${esc(error.message || "Unable to load certification map.")}</div>`);
        }
    }

    async function init() {
        resetMap();
        document.getElementById("stateMapRefresh")?.addEventListener("click", load);
        let searchTimer = null;
        document.getElementById("stateMapSearch")?.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(load, 300);
        });
        await new Promise(resolve => window.requestAnimationFrame(resolve));
        await load();
    }

    SQ.stateMap = { init };
})(window, document);
