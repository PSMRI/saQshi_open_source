/*!
 * ==========================================================
 * SaQshi Login Page v1.0
 * ----------------------------------------------------------
 * Project : SaQshi Open Source
 * Module  : Login
 * File    : login.js
 * License : GPL-3.0
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;
    let loginPublicKeyPromise = null;

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element && value) element.textContent = value;
    }

    function setLoginLogos(branding) {
        const container = document.getElementById("loginBrandLogos");
        const defaultLogo = document.getElementById("loginDefaultLogo");
        const logos = [
            { element: document.getElementById("loginBrandLogo1"), url: branding.login_logo_1, alt: branding.login_logo_1_alt || "Organisation logo" },
            { element: document.getElementById("loginBrandLogo2"), url: branding.login_logo_2, alt: branding.login_logo_2_alt || "Partner organisation logo" }
        ];

        if (!container || !defaultLogo) return;

        let configured = 0;
        logos.forEach(function (logo) {
            if (!logo.element || !String(logo.url || "").trim()) return;

            logo.element.src = String(logo.url).trim();
            logo.element.alt = logo.alt;
            logo.element.hidden = false;
            logo.element.addEventListener("error", function () {
                logo.element.hidden = true;
                if (!container.querySelector("img:not([hidden])")) {
                    container.hidden = true;
                    defaultLogo.hidden = false;
                }
            }, { once: true });
            configured += 1;
        });

        if (configured) {
            container.hidden = false;
            defaultLogo.hidden = true;
        }
    }

    async function loadLoginBranding() {
        try {
            const config = (await SQ.api.get("/config/v1/public_deployment.php", {}, {
                loader: false,
                showError: false,
                redirectOnUnauthorized: false
            })).data;
            const branding = config?.domain?.branding || {};

            setText("loginBrandAppName", branding.login_app_name);
            setText("loginBrandAppTagline", branding.login_app_tagline);
            setText("loginBrandKicker", branding.login_kicker);
            setText("loginBrandTitle", branding.login_title);
            setText("loginBrandDescription", branding.login_description);
            setLoginLogos(branding);
        } catch (error) {
            // Keep the HTML defaults if public deployment configuration is unavailable.
            console.warn("Login branding could not be loaded.", error);
        }
    }

    function form() {
        return document.getElementById("loginForm");
    }

    function username() {
        return document.getElementById("username");
    }

    function password() {
        return document.getElementById("password");
    }

    function button() {
        return document.getElementById("loginButton");
    }

    function captcha() {
        return document.getElementById("captcha");
    }

    function captchaQuestion() {
        return document.getElementById("captchaQuestion");
    }

    function pemToArrayBuffer(pem) {
        const base64 = String(pem || "")
            .replace(/-----BEGIN PUBLIC KEY-----/g, "")
            .replace(/-----END PUBLIC KEY-----/g, "")
            .replace(/\s+/g, "");
        const binary = window.atob(base64);
        const bytes = new Uint8Array(binary.length);

        for (let i = 0; i < binary.length; i += 1) {
            bytes[i] = binary.charCodeAt(i);
        }

        return bytes.buffer;
    }

    function arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = "";

        bytes.forEach(function (byte) {
            binary += String.fromCharCode(byte);
        });

        return window.btoa(binary);
    }

    function loadLoginPublicKey() {
        if (!loginPublicKeyPromise) {
            loginPublicKeyPromise = SQ.api.get("/auth/v1/login_key.php", {}, {
                loader: false,
                showError: false,
                redirectOnUnauthorized: false,
                timeout: 60000
            }).then(function (keyResponse) {
                const publicKeyPem = keyResponse.data?.public_key || "";
                if (!publicKeyPem) throw new Error("Login security key could not be loaded. Please try again.");
                return window.crypto.subtle.importKey("spki", pemToArrayBuffer(publicKeyPem), { name: "RSA-OAEP", hash: "SHA-1" }, false, ["encrypt"]);
            }).catch(function (error) {
                loginPublicKeyPromise = null;
                throw error;
            });
        }
        return loginPublicKeyPromise;
    }

    async function encryptPassword(plainPassword) {
        if (!window.crypto || !window.crypto.subtle) {
            throw new Error("Secure password encryption is not available in this browser. Please use HTTPS or localhost.");
        }

<<<<<<< Updated upstream
        const keyResponse = await SQ.api.get("/auth/v1/login_key.php", {}, {
            loader: false,
            showError: false,
            redirectOnUnauthorized: false
        });

        const publicKeyPem = keyResponse.data?.public_key || "";

        if (!publicKeyPem) {
            throw new Error("Login security key could not be loaded. Please try again.");
        }

        const publicKey = await window.crypto.subtle.importKey(
            "spki",
            pemToArrayBuffer(publicKeyPem),
            { name: "RSA-OAEP", hash: "SHA-1" },
            false,
            ["encrypt"]
        );
=======
        const publicKey = await loadLoginPublicKey();
>>>>>>> Stashed changes

        const encrypted = await window.crypto.subtle.encrypt(
            { name: "RSA-OAEP" },
            publicKey,
            new TextEncoder().encode(plainPassword)
        );

        return arrayBufferToBase64(encrypted);
    }

    function setButtonLoading(isLoading) {
        const btn = button();

        if (!btn) {
            return;
        }

        btn.disabled = isLoading;

        btn.innerHTML = isLoading
            ? `<span class="sq-btn-spinner"></span> Signing in...`
            : `<i class="bi bi-box-arrow-in-right"></i> Login`;
    }

    function togglePassword() {
        const input = password();
        const icon = document.querySelector("#togglePassword i");

        if (!input) {
            return;
        }

        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";

        if (icon) {
            icon.className = isPassword ? "bi bi-eye-slash" : "bi bi-eye";
        }
    }

    async function loadCaptcha() {
        const question = captchaQuestion();
        const input = captcha();

        if (question) {
            question.textContent = "Loading...";
        }

        if (input) {
            input.value = "";
        }

        try {
            const response = await SQ.api.get(
                "/auth/v1/captcha.php",
                {},
                {
                    loader: false,
                    showError: false,
                    redirectOnUnauthorized: false
                }
            );

            const text =
                response.data?.question ||
                response.question ||
                "";

            if (question) {
                question.textContent = text || "Unable to load";
                question.setAttribute("aria-label", text ? `Captcha question: ${text}` : "Captcha question unavailable");
            }

        } catch (error) {
            console.error(error);

            if (question) {
                question.textContent = "Refresh captcha";
                question.setAttribute("aria-label", "Captcha could not be loaded. Use refresh captcha or contact administrator for assisted login.");
            }
        }
    }

    async function handleLogin(event) {
        event.preventDefault();

        const frm = form();

        if (!frm) {
            return;
        }

        if (SQ.validator) {
            const result = SQ.validator.validateAndShow(frm, {
                username: ["required"],
                password: ["required"],
                captcha: ["required"]
            });

            if (!result.valid) {
                return;
            }
        }

        const plainPassword = password().value;
        const payload = {
            username: username().value.trim(),
            password_enc: "",
            captcha: captcha().value.trim()
        };

        try {
            setButtonLoading(true);

            if (SQ.loader) {
                SQ.loader.show("Signing in...");
            }

            let response;
            payload.password_enc = await encryptPassword(plainPassword);

            if (SQ.auth && SQ.auth.loginEncrypted) {
                response = await SQ.auth.loginEncrypted(payload.username, payload.password_enc, payload.captcha);
            } else {
                response = await SQ.api.post(
                    "/auth/v1/login.php",
                    payload,
                    {
                        loader: false
                    }
                );
            }

            const user =
                response.data?.user ||
                response.user ||
                null;

            if (user && SQ.auth && SQ.auth.saveUser) {
                SQ.auth.saveUser(user);
            }

            if (SQ.notification) {
                SQ.notification.success(response.message || "Login successful");
            }

            window.location.href = user && user.password_must_change
                ? "/ui/dashboard.html?route=facilityusers/users&force_password=1"
                : Number(user && user.role_id) === 11
                ? "/ui/dashboard.html?route=state/users"
                : Number(user && user.role_id) === 9
                ? "/ui/dashboard.html?route=state/dashboard"
                : (Number(user && user.role_id) === 10 || /assessor|mentor/i.test(String(user && user.role_name || "")))
                ? "/ui/dashboard.html?route=assessor/facilities"
                : "/ui/dashboard.html";

        } catch (error) {
            console.error(error);
            loadCaptcha();

            if (SQ.notification) {
                SQ.notification.error(error.message || "Invalid username or password");
            }

            if (error.errors && SQ.validator) {
                SQ.validator.showErrors(frm, error.errors);
            }

        } finally {
            setButtonLoading(false);

            if (SQ.loader) {
                SQ.loader.hide();
            }
        }
    }

    function bindEvents() {
        const frm = form();

        if (frm) {
            frm.addEventListener("submit", handleLogin);
        }

        const toggle = document.getElementById("togglePassword");

        if (toggle) {
            toggle.addEventListener("click", togglePassword);
        }

        const refresh = document.getElementById("refreshCaptcha");

        if (refresh) {
            refresh.addEventListener("click", loadCaptcha);
        }

        const forgot = document.getElementById("forgotPasswordLink");

        if (forgot) {
            forgot.addEventListener("click", function (event) {
                event.preventDefault();

                if (SQ.notification) {
                    SQ.notification.info("Forgot password feature will be available soon.");
                }
            });
        }
    }

    function focusUsername() {
        const input = username();

        if (input) {
            input.focus();
        }
    }

    async function init() {
        bindEvents();
        focusUsername();

        // These public requests each establish a PHP session on a first visit.
        // Load the CAPTCHA last so its answer is stored in the session cookie
        // the browser will use for the login request.
        await Promise.all([
            loadLoginBranding(),
            loadLoginPublicKey().catch(function () {})
        ]);
        await loadCaptcha();
    }

    SQ.login = {
        init,
        loadCaptcha
    };

})(window, document);
