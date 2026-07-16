/*!
 * ==========================================================
 * SQ-UI Auth Client v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Authentication Helper
 * Depends   : SQ, SQ.api
 * License   : GPL-3.0
 * ==========================================================
 */

(function (window) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    if (!SQ.api) {
        throw new Error("SQ.api is required before loading auth.js");
    }

    const AUTH = {
        userKey: "sq_user",
        csrfKey: "sq_csrf_token",
        loginPage: "/ui/login.html",
        dashboardPage: "/ui/dashboard.html"
    };

    function saveUser(user) {
        localStorage.setItem(AUTH.userKey, JSON.stringify(user || {}));
    }

    function getUser() {
        try {
            const user = localStorage.getItem(AUTH.userKey);
            return user ? JSON.parse(user) : null;
        } catch (e) {
            return null;
        }
    }

    function clearUser() {
        localStorage.removeItem(AUTH.userKey);
        localStorage.removeItem(AUTH.csrfKey);
        localStorage.removeItem("sq_active_assessment");
    }

    function isLoggedIn() {
        const user = getUser();
        return !!(user && user.u_id);
    }

    async function loadCsrf() {
        const response = await SQ.api.get(
            "/auth/v1/csrf.php",
            {},
            {
                loader: false,
                showError: false,
                redirectOnUnauthorized: false
            }
        );

        const token =
            response.csrf_token ||
            response.data?.csrf_token ||
            response.data?.token ||
            "";

        if (token) {
            localStorage.setItem(AUTH.csrfKey, token);
            SQ.api.setCsrfToken(token);
        }

        return token;
    }

    async function login(username, password, captcha = "") {
        throw {
            status: "error",
            message: "Raw password login is disabled. Use encrypted login."
        };
    }

    async function loginEncrypted(username, passwordEnc, captcha = "") {
        if (!username || !passwordEnc) {
            throw {
                status: "error",
                message: "Username and password are required"
            };
        }

        await loadCsrf();

        const response = await SQ.api.post(
            "/auth/v1/login.php",
            {
                username: username,
                password_enc: passwordEnc,
                captcha: captcha
            },
            {
                loaderText: "Signing in..."
            }
        );

        const user =
            response.data?.user ||
            response.user ||
            null;

        if (user) {
            saveUser(user);
        }

        return response;
    }

    async function logout() {
        try {
            await SQ.api.post(
                "/auth/v1/logout.php",
                {},
                {
                    loaderText: "Signing out...",
                    showError: false
                }
            );
        } catch (e) {
            /* ignore logout API failure */
        }

        clearUser();

        window.location.href = AUTH.loginPage;
    }

    async function me() {
        const response = await SQ.api.get(
            "/auth/v1/me.php",
            {},
            {
                loader: false,
                redirectOnUnauthorized: false
            }
        );

        const user =
            response.data?.user ||
            response.user ||
            null;

        if (user) {
            saveUser(user);
        }

        return user;
    }

    async function requireAuth() {
        try {
            const user = await me();

            if (!user || !user.u_id) {
                clearUser();
                window.location.href = AUTH.loginPage;
                return null;
            }

            return user;

        } catch (e) {
            clearUser();
            window.location.href = AUTH.loginPage;
            return null;
        }
    }

    function redirectIfLoggedIn() {
        if (isLoggedIn()) {
            window.location.href = AUTH.dashboardPage;
        }
    }

    function bindLoginForm(formSelector) {
        const form = document.querySelector(formSelector);

        if (!form) {
            return;
        }

        form.addEventListener("submit", async function (event) {
            event.preventDefault();

            if (SQ.form) {
                SQ.form.clearErrors(form);
            }

            const username = form.querySelector("[name='username']")?.value.trim();
            const password = form.querySelector("[name='password']")?.value.trim();
            const captcha = form.querySelector("[name='captcha']")?.value.trim() || "";

            try {
                throw {
                    status: "error",
                    message: "This login form must use encrypted password transport."
                };

                if (SQ.toast) {
                    SQ.toast(response.message || "Login successful", "success");
                }

                setTimeout(function () {
                    window.location.href = AUTH.dashboardPage;
                }, 500);

            } catch (error) {
                if (SQ.toast) {
                    SQ.toast(error.message || "Login failed", "danger");
                }

                if (error.errors && SQ.form) {
                    SQ.form.setErrors(error.errors, form);
                }
            }
        });
    }

    function bindLogoutButtons() {
        document.querySelectorAll("[data-sq-logout]").forEach(function (btn) {
            btn.addEventListener("click", function (event) {
                event.preventDefault();

                if (window.confirm("Are you sure you want to logout?")) {
                    logout();
                }
            });
        });
    }

    function renderUser() {
        const user = getUser();

        if (!user) {
            return;
        }

        document.querySelectorAll("[data-sq-user-name]").forEach(function (el) {
            el.textContent =
                user.full_name ||
                user.u_name ||
                "User";
        });

        document.querySelectorAll("[data-sq-user-role]").forEach(function (el) {
            el.textContent =
                user.role_name ||
                user.role_id ||
                "";
        });

        document.querySelectorAll("[data-sq-user-facility]").forEach(function (el) {
            el.textContent =
                user.fac_name ||
                user.fac_id ||
                "";
        });
    }

    SQ.auth = {
        config: function (settings = {}) {
            Object.assign(AUTH, settings);
        },

        loadCsrf: loadCsrf,
        login: login,
        loginEncrypted: loginEncrypted,
        logout: logout,
        me: me,
        requireAuth: requireAuth,
        redirectIfLoggedIn: redirectIfLoggedIn,
        isLoggedIn: isLoggedIn,
        getUser: getUser,
        saveUser: saveUser,
        clearUser: clearUser,
        bindLoginForm: bindLoginForm,
        bindLogoutButtons: bindLogoutButtons,
        renderUser: renderUser
    };

    document.addEventListener("DOMContentLoaded", function () {
        bindLogoutButtons();
        renderUser();
    });

})(window);
