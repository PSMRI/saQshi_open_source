/*!
 * ==========================================================
 * SaQshi Open Source
 * Facility User Profile
 * users_profile.js
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    const API = {
        profile: "/admin/v1/users.php"
    };

    const state = {
        user: null,
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

    function fullName(user) {
        return [user.f_name, user.m_name, user.l_name]
            .filter(Boolean)
            .join(" ")
            .trim() || user.u_name || "-";
    }

    function setText(id, value) {
        const el = $(id);

        if (el) {
            el.textContent = value;
        }
    }

    function passwordRules(password, confirmPassword) {
        const hasPassword = password.length > 0 || confirmPassword.length > 0;

        return {
            length: !hasPassword || password.length >= 8,
            upper: !hasPassword || /[A-Z]/.test(password),
            lower: !hasPassword || /[a-z]/.test(password),
            digit: !hasPassword || /[0-9]/.test(password),
            special: !hasPassword || /[^A-Za-z0-9]/.test(password),
            match: !hasPassword || (password !== "" && password === confirmPassword),
            hasPassword
        };
    }

    function passwordScore(rules) {
        return ["length", "upper", "lower", "digit", "special", "match"]
            .reduce(function (score, rule) {
                return score + (rules[rule] ? 1 : 0);
            }, 0);
    }

    function renderPasswordRules() {
        const password = $("adminPassword")?.value || "";
        const confirmPassword = $("adminConfirmPassword")?.value || "";
        const rules = passwordRules(password, confirmPassword);
        const score = rules.hasPassword ? passwordScore(rules) : 0;
        const meter = $("adminPasswordMeter");

        Object.keys(rules).forEach(function (rule) {
            if (rule === "hasPassword") {
                return;
            }

            const el = document.querySelector(`[data-rule="${rule}"]`);

            if (el) {
                el.classList.toggle("is-ok", Boolean(rules[rule]));
                el.classList.toggle("is-bad", rules.hasPassword && !rules[rule]);
            }
        });

        if (meter) {
            meter.style.width = rules.hasPassword ? Math.round((score / 6) * 100) + "%" : "0%";
            meter.className = score >= 6 ? "is-strong" : (score >= 4 ? "is-medium" : "is-weak");
        }

        return rules;
    }

    function passwordIsValidIfEntered() {
        const rules = renderPasswordRules();

        if (!rules.hasPassword) {
            return true;
        }

        return ["length", "upper", "lower", "digit", "special", "match"]
            .every(function (rule) {
                return rules[rule];
            });
    }

    function setFormEnabled(enabled) {
        [
            "adminFirstName",
            "adminMiddleName",
            "adminLastName",
            "adminEmail",
            "adminMobile",
            "adminPassword",
            "adminConfirmPassword",
            "btnClearUserForm",
            "btnSaveUser"
        ].forEach(function (id) {
            const el = $(id);

            if (el) {
                el.disabled = !enabled;
            }
        });
    }

    function clearPasswordFields() {
        if ($("adminPassword")) {
            $("adminPassword").value = "";
        }

        if ($("adminConfirmPassword")) {
            $("adminConfirmPassword").value = "";
        }

        renderPasswordRules();
    }

    function fillForm(user) {
        state.user = user;

        $("adminUserId").value = user.u_id || "";
        $("adminFirstName").value = user.f_name || "";
        $("adminMiddleName").value = user.m_name || "";
        $("adminLastName").value = user.l_name || "";
        $("adminEmail").value = user.mail_id || "";
        $("adminMobile").value = user.mob_no || "";
        $("adminUserType").value = user.user_type || "";

        setText("adminUserFormTitle", fullName(user));
        setText("adminUserFormHint", user.u_name || "");
        setText("adminUserIdBadge", "User ID " + Number(user.u_id || 0));
        setText("adminUsernameText", user.u_name || "-");
        setText("adminRoleText", user.role_name || user.user_type || "-");
        setText("adminFacilityText", user.fac_id_fk || "-");
        setText("adminStatusText", Number(user.is_active || 0) === 1 ? "Active" : "Inactive");

        clearPasswordFields();
        setFormEnabled(true);
    }

    async function loadProfile() {
        setFormEnabled(false);
        setText("adminUserFormTitle", "Loading profile");
        setText("adminUserFormHint", "Please wait while your profile is loaded.");

        const response = await SQ.api.get(API.profile, {}, {
            loader: false,
            showError: false
        });

        fillForm(response.data?.user || {});
    }

    async function saveProfile(event) {
        event.preventDefault();

        if (!state.user) {
            notify("warning", "Profile is still loading.");
            return;
        }

        if (!passwordIsValidIfEntered()) {
            notify("warning", "Password does not meet the strength rules.");
            return;
        }

        const payload = {
            f_name: $("adminFirstName").value.trim(),
            m_name: $("adminMiddleName").value.trim(),
            l_name: $("adminLastName").value.trim(),
            mail_id: $("adminEmail").value.trim(),
            mob_no: $("adminMobile").value.trim(),
            password: $("adminPassword").value,
            confirm_password: $("adminConfirmPassword").value
        };

        try {
            const response = await SQ.api.post(API.profile, payload, {
                loader: true,
                loaderText: "Saving profile..."
            });

            notify("success", response.message || "Profile updated successfully.");
            fillForm(response.data?.user || state.user);
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to update profile.");
        }
    }

    function bindEvents() {
        $("btnRefreshUsers")?.addEventListener("click", loadProfile);
        $("adminUserForm")?.addEventListener("submit", saveProfile);
        $("btnClearUserForm")?.addEventListener("click", clearPasswordFields);
        $("adminPassword")?.addEventListener("input", renderPasswordRules);
        $("adminConfirmPassword")?.addEventListener("input", renderPasswordRules);
    }

    async function init() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        bindEvents();
        renderPasswordRules();

        try {
            await loadProfile();
        } catch (error) {
            console.error(error);
            notify("error", error.message || "Unable to load profile.");
            setText("adminUserFormTitle", "Profile unavailable");
            setText("adminUserFormHint", "Unable to load your user details.");
        } finally {
            state.isLoading = false;
        }
    }

    SQ.adminUsers = {
        init,
        state
    };

})(window, document);
