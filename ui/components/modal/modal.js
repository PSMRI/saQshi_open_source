/*!
 * ==========================================================
 * SQ Modal Component v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Modal
 * File      : modal.js
 * License   : GPL-3.0
 * ==========================================================
 *
 * Features
 * ----------------------------------------------------------
 * ✔ Open / Close
 * ✔ Dynamic HTML
 * ✔ Dynamic Footer
 * ✔ Callback Support
 * ✔ ESC Close
 * ✔ Overlay Close
 * ✔ Focus Management
 * ✔ Multiple Sizes
 * ✔ Fullscreen
 * ✔ Alert
 * ✔ Confirm
 * ✔ Form Modal
 * ✔ Async Ready
 * ==========================================================
 */

(function (window, document) {

    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    let currentOptions = {};

    let previousFocus = null;

    /* -------------------------------------------------------
       Elements
    ------------------------------------------------------- */

    function modal() {
        return document.getElementById("sq-modal");
    }

    function title() {
        return document.getElementById("sq-modal-title");
    }

    function subtitle() {
        return document.getElementById("sq-modal-subtitle");
    }

    function body() {
        return document.getElementById("sq-modal-body");
    }

    function footer() {
        return document.getElementById("sq-modal-footer");
    }

    function primaryButton() {
        return document.getElementById("sq-modal-primary");
    }

    /* -------------------------------------------------------
       Open
    ------------------------------------------------------- */

    function open(options = {}) {

        const m = modal();

        if (!m) {
            return;
        }

        previousFocus = document.activeElement;

        currentOptions = Object.assign({

            title: "Modal",

            subtitle: "",

            content: "",

            size: "md",

            footer: true,

            primaryText: "Save",

            primaryClass: "sq-btn-primary",

            closeOnOverlay: true,

            closeOnEscape: true,

            onPrimary: null,

            onClose: null

        }, options);

        title().textContent = currentOptions.title;

        subtitle().textContent = currentOptions.subtitle;

        body().innerHTML = currentOptions.content;

        footer().style.display = currentOptions.footer ? "" : "none";

        primaryButton().textContent = currentOptions.primaryText;

        primaryButton().className =
            "sq-btn " + currentOptions.primaryClass;

        m.className = "sq-modal is-open sq-modal-" + currentOptions.size;

        m.setAttribute("aria-hidden", "false");

        document.body.style.overflow = "hidden";

        primaryButton().focus();

    }

    /* -------------------------------------------------------
       Close
    ------------------------------------------------------- */

    function close() {

        const m = modal();

        if (!m) {
            return;
        }

        m.className = "sq-modal";

        m.setAttribute("aria-hidden", "true");

        document.body.style.overflow = "";

        if (
            typeof currentOptions.onClose === "function"
        ) {

            currentOptions.onClose();

        }

        if (previousFocus) {

            previousFocus.focus();

        }

    }

    /* -------------------------------------------------------
       Alert
    ------------------------------------------------------- */

    function alert(message, titleText = "Information") {

        open({

            title: titleText,

            content: `<p>${message}</p>`,

            primaryText: "OK",

            onPrimary: close

        });

    }

    /* -------------------------------------------------------
       Confirm
    ------------------------------------------------------- */

    function confirm(message, callback) {

        open({

            title: "Confirmation",

            content: `<p>${message}</p>`,

            primaryText: "Confirm",

            primaryClass: "sq-btn-danger",

            onPrimary: function () {

                if (callback) {

                    callback();

                }

                close();

            }

        });

    }

    /* -------------------------------------------------------
       Loading
    ------------------------------------------------------- */

    function loading(message = "Loading...") {

        open({

            title: "Please Wait",

            content: `
                <div style="padding:20px;text-align:center;">
                    ${message}
                </div>
            `,

            footer: false,

            closeOnOverlay: false,

            closeOnEscape: false

        });

    }

    /* -------------------------------------------------------
       Events
    ------------------------------------------------------- */

    function bindEvents() {

        document.addEventListener("click", function (e) {

            if (
                e.target.matches("[data-sq-modal-close]")
            ) {

                if (currentOptions.closeOnOverlay) {

                    close();

                }

            }

        });

        document.addEventListener("keydown", function (e) {

            if (
                e.key === "Escape" &&
                currentOptions.closeOnEscape
            ) {

                close();

            }

        });

        document.addEventListener("click", function (e) {

            if (
                e.target.id === "sq-modal-primary"
            ) {

                if (
                    typeof currentOptions.onPrimary === "function"
                ) {

                    currentOptions.onPrimary();

                }
                else {

                    close();

                }

            }

        });

    }

    /* -------------------------------------------------------
       Public API
    ------------------------------------------------------- */

    SQ.modal = {

        open,

        close,

        alert,

        confirm,

        loading

    };

    /* -------------------------------------------------------
       Init
    ------------------------------------------------------- */

    function init() {

        bindEvents();

    }

    document.addEventListener(

        "DOMContentLoaded",

        init

    );

    document.addEventListener(

        "sq:component-loaded",

        function (event) {

            if (

                event.detail &&
                event.detail.name === "modal"

            ) {

                init();

            }

        }

    );

})(window, document);