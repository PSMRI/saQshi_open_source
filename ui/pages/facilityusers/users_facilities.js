/*!
 * ==========================================================
 * SaQshi Open Source
 * Facility Details
 * users_facilities.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        facility: "/admin/v1/facilities.php"
    };

    const state = {
        facility: null,
        editing: false,
        isLoading: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    function notify(type, message) {
        if (SQ.notification && typeof SQ.notification[type] === "function") {
            SQ.notification[type](message);
            return;
        }

        if (SQ.toast) {
            SQ.toast(message, type);
        }
    }

    function setText(id, value) {
        const el = $(id);

        if (el) {
            el.textContent = value || "-";
        }
    }

    function numberValue(id) {
        const value = $(id)?.value;
        return value === "" || value === null || value === undefined ? 0 : Number(value);
    }

    function textValue(id) {
        return String($(id)?.value || "").trim();
    }

    function setEditing(editing) {
        state.editing = editing;

        [
            "facilityName",
            "facilityNin",
            "facilityStatus",
            "facilityLatitude",
            "facilityLongitude",
            "btnUseBrowserLocation"
        ].forEach(function (id) {
            const el = $(id);

            if (el) {
                el.disabled = !editing;
            }
        });

        if ($("btnEditFacility")) {
            $("btnEditFacility").disabled = editing || !state.facility;
        }

        if ($("btnSaveFacility")) {
            $("btnSaveFacility").disabled = !editing;
        }

        if ($("btnCancelFacility")) {
            $("btnCancelFacility").disabled = !editing;
        }

        setText("facilitySaveHint", editing ? "Review and save facility changes." : "Select Edit to update facility details.");
    }

    function fillForm(facility) {
        state.facility = facility || {};

        $("facilityId").value = facility.fac_id || "";
        $("facilityName").value = facility.fac_name || "";
        $("facilityNin").value = facility.nin_no || "";
        $("facilityType").value = facility.Health_facilty_type || facility.fac_type_id || "";
        $("facilityTypeName").value = facility.facilities_type || facility.Health_facilty_type || facility.fac_type_id || "";
        $("facilityStatus").value = String(Number(facility.is_active ?? 1));
        $("facilityState").value = facility.state_id || "";
        $("facilityDivision").value = facility.division_id || "";
        $("facilityDistrict").value = facility.dist_id || facility.district_id || "";
        $("facilityBlock").value = facility.block_id || "";
        $("facilityStateName").value = facility.state_name || "";
        $("facilityDivisionName").value = facility.division || facility.division_name || "";
        $("facilityDistrictName").value = facility.Dist_Name || facility.dist_name || "";
        $("facilityBlockName").value = facility.Block_Name || facility.block_name || "";
        $("facilityLatitude").value = facility.latitude || facility.lat || "";
        $("facilityLongitude").value = facility.longitude || facility.longit || "";

        setText("facilityTypeText", facility.facilities_type || facility.Health_facilty_type || facility.fac_type_id || "-");
        setText("facilityNinText", facility.nin_no || "-");
        setText("facilityStatusText", Number(facility.is_active ?? 1) === 1 ? "Active" : "Inactive");
        setEditing(false);
    }

    function validatePayload(payload) {
        const errors = [];

        if (!payload.fac_name) {
            errors.push("Facility name is required.");
        }

        if (!payload.facility_type || payload.facility_type <= 0) {
            errors.push("Facility type is required.");
        }

        if (payload.latitude === "" || payload.longitude === "") {
            errors.push("Please get current location before saving.");
            return errors;
        }

        if (Number.isNaN(Number(payload.latitude)) || Number(payload.latitude) < -90 || Number(payload.latitude) > 90) {
            errors.push("Latitude must be between -90 and 90.");
        }

        if (Number.isNaN(Number(payload.longitude)) || Number(payload.longitude) < -180 || Number(payload.longitude) > 180) {
            errors.push("Longitude must be between -180 and 180.");
        }

        return errors;
    }

    async function loadFacility() {
        const response = await SQ.api.get(API.facility, {}, {
            loader: false,
            showError: false
        });

        fillForm(response.data?.facility || {});
    }

    async function saveFacility(event) {
        event.preventDefault();

        const payload = {
            fac_name: textValue("facilityName"),
            nin_no: textValue("facilityNin"),
            facility_type: numberValue("facilityType"),
            is_active: numberValue("facilityStatus"),
            state_id: numberValue("facilityState"),
            division_id: numberValue("facilityDivision"),
            dist_id: numberValue("facilityDistrict"),
            block_id: numberValue("facilityBlock"),
            latitude: textValue("facilityLatitude"),
            longitude: textValue("facilityLongitude")
        };

        const errors = validatePayload(payload);

        if (errors.length) {
            notify("warning", errors[0]);
            return;
        }

        try {
            const response = await SQ.api.post(API.facility, payload, {
                loader: true,
                loaderText: "Saving facility..."
            });

            notify("success", response.message || "Facility updated successfully.");
            fillForm(response.data?.facility || state.facility);
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to update facility.");
        }
    }

    function useBrowserLocation() {
        if (!navigator.geolocation) {
            notify("warning", "Browser location is not available.");
            return;
        }

        const button = $("btnUseBrowserLocation");

        if (button) {
            button.disabled = true;
            button.textContent = "Getting Location...";
        }

        navigator.geolocation.getCurrentPosition(
            function (position) {
                $("facilityLatitude").value = position.coords.latitude.toFixed(6);
                $("facilityLongitude").value = position.coords.longitude.toFixed(6);
                notify("success", "Current location captured.");

                if (button) {
                    button.disabled = !state.editing;
                    button.textContent = "Get Current Location";
                }
            },
            function () {
                notify("warning", "Unable to read current location.");

                if (button) {
                    button.disabled = !state.editing;
                    button.textContent = "Get Current Location";
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    function bindEvents() {
        $("adminFacilityForm")?.addEventListener("submit", saveFacility);
        $("btnRefreshFacility")?.addEventListener("click", loadFacility);
        $("btnEditFacility")?.addEventListener("click", function () {
            setEditing(true);
        });
        $("btnCancelFacility")?.addEventListener("click", function () {
            fillForm(state.facility || {});
        });
        $("btnUseBrowserLocation")?.addEventListener("click", useBrowserLocation);
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        bindEvents();
        setEditing(false);

        try {
            await loadFacility();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load facility details.");
        } finally {
            state.isLoading = false;
        }
    }

    SQ.adminFacilities = {
        init,
        state
    };

})(window, document);
