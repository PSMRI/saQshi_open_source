/*!
 * ==========================================================
 * SQ Loader Component v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Global Loader
 * File      : loader.js
 * License   : MIT
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    function getLoader() {
        return document.getElementById("sq-loader");
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    function show(message = "Loading...") {
        const loader = getLoader();

        if (!loader) {
            return;
        }

        loader.classList.remove("success", "error");
        loader.style.display = "";
        loader.classList.add("active");
        loader.setAttribute("aria-hidden", "false");

        setText("sq-loader-message", message);
        progress(0);
    }

    function hide() {
        const loader = getLoader();

        if (!loader) {
            return;
        }

        loader.classList.remove("active", "success", "error");
        loader.style.display = "none";
        loader.setAttribute("aria-hidden", "true");
        reset();
    }

    function message(text) {
        setText("sq-loader-message", text);
    }

    function details(text) {
        setText("sq-loader-details", text || "");
    }

    function progress(value) {
        const percent = Math.max(0, Math.min(100, Number(value) || 0));
        const bar = document.getElementById("sq-loader-progress-bar");

        if (bar) {
            bar.style.width = percent + "%";
        }

        setText("sq-loader-percentage", percent + "%");
    }

    function success(text = "Completed successfully") {
        const loader = getLoader();

        if (!loader) {
            return;
        }

        loader.classList.remove("error");
        loader.style.display = "";
        loader.classList.add("active", "success");

        setText("sq-loader-message", text);
        progress(100);

        setTimeout(hide, 900);
    }

    function error(text = "Something went wrong") {
        const loader = getLoader();

        if (!loader) {
            return;
        }

        loader.classList.remove("success");
        loader.style.display = "";
        loader.classList.add("active", "error");

        setText("sq-loader-title", "Error");
        setText("sq-loader-message", text);
    }

    function reset() {
        setText("sq-loader-title", "Please Wait");
        setText("sq-loader-message", "Loading...");
        setText("sq-loader-details", "");
        progress(0);
    }

    function init() {
        reset();
    }

    SQ.loader = {
        init,
        show,
        hide,
        message,
        details,
        progress,
        success,
        error,
        reset
    };

    document.addEventListener("DOMContentLoaded", init);

    document.addEventListener("sq:component-loaded", function (event) {
        if (event.detail && event.detail.name === "loader") {
            init();
        }
    });

})(window, document);
