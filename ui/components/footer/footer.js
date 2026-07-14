/*!
 * ==========================================================
 * SQ Footer Component v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Footer
 * File      : footer.js
 * License   : GPL-3.0
 * ==========================================================
 *
 * Responsibilities
 * ----------------------------------------------------------
 * ✔ Display Current Year
 * ✔ Load Application Information
 * ✔ Load Version
 * ✔ Load Build
 * ✔ Load Environment
 * ✔ Load Support Email
 * ✔ Load License
 * ✔ Future Update Checker
 * ✔ Future Release Notification
 * ==========================================================
 */

(function (window, document) {

    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    const DEFAULTS = {

        appName: "SaQshi",

        version: "1.0.0",

        build: "DEV",

        environment: "Development",

        license: "GPL-3.0",

        supportEmail: "support@saqshi.org"

    };

    function setText(id, value) {

        const element = document.getElementById(id);

        if (element) {

            element.textContent = value;

        }

    }

    function setLink(id, href, text) {

        const element = document.getElementById(id);

        if (!element) {

            return;

        }

        element.href = href;

        if (text) {

            element.textContent = text;

        }

    }

    function loadDefaults() {

        setText("sq-footer-year", new Date().getFullYear());

        setText("sq-footer-app-name", DEFAULTS.appName);

        setText("sq-footer-version", DEFAULTS.version);

        setText("sq-footer-build", DEFAULTS.build);

        setText("sq-footer-environment", DEFAULTS.environment);

        setLink(
            "sq-footer-license",
            "#",
            DEFAULTS.license
        );

        setLink(
            "sq-footer-support",
            "mailto:" + DEFAULTS.supportEmail,
            DEFAULTS.supportEmail
        );

    }

    function loadConfiguration() {

        if (!SQ.config) {

            loadDefaults();

            return;

        }

        const app = SQ.config.application || {};

        setText(

            "sq-footer-year",

            new Date().getFullYear()

        );

        setText(

            "sq-footer-app-name",

            app.name || DEFAULTS.appName

        );

        setText(

            "sq-footer-version",

            app.version || DEFAULTS.version

        );

        setText(

            "sq-footer-build",

            app.build || DEFAULTS.build

        );

        setText(

            "sq-footer-environment",

            app.environment || DEFAULTS.environment

        );

        setLink(

            "sq-footer-license",

            app.license_url || "#",

            app.license || DEFAULTS.license

        );

        setLink(

            "sq-footer-support",

            "mailto:" + (app.support_email || DEFAULTS.supportEmail),

            app.support_email || DEFAULTS.supportEmail

        );

    }

    function bindEvents() {

        document
            .querySelectorAll("#sq-footer-license")
            .forEach(function (element) {

                element.addEventListener("click", function () {

                    console.log("[SQ Footer] License Clicked");

                });

            });

        document
            .querySelectorAll("#sq-footer-support")
            .forEach(function (element) {

                element.addEventListener("click", function () {

                    console.log("[SQ Footer] Support");

                });

            });

    }

    function checkUpdate() {

        /*
        Future

        GET

        /api/system/version.php

        Compare latest version

        Notify user

        */

    }

    function init() {

        loadConfiguration();

        bindEvents();

        checkUpdate();

    }

    SQ.footer = {

        init: init,

        refresh: loadConfiguration

    };

    document.addEventListener(

        "DOMContentLoaded",

        init

    );

    document.addEventListener(

        "sq:component-loaded",

        function (event) {

            if (

                event.detail &&
                event.detail.name === "footer"

            ) {

                init();

            }

        }

    );

})(window, document);