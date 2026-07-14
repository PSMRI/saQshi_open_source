/*!
 * ==========================================================
 * SQ Confirm Dialog Component v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Confirm Dialog
 * File      : confirm-dialog.js
 * License   : GPL-3.0
 * ==========================================================
 *
 * Features
 * ----------------------------------------------------------
 * ✔ Confirmation Dialog
 * ✔ Alert Dialog
 * ✔ Multiple Types
 * ✔ Callback Support
 * ✔ Promise Support
 * ✔ ESC Close
 * ✔ Overlay Close
 * ✔ Keyboard Accessible
 * ✔ Focus Restore
 * ✔ WCAG 2.2 AA
 * ==========================================================
 */

(function (window, document) {

    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    let options = {};

    let previousFocus = null;

    /* ======================================================
       Elements
    ====================================================== */

    function dialog() {

        return document.getElementById("sq-confirm-dialog");

    }

    function title() {

        return document.getElementById("sq-confirm-title");

    }

    function message() {

        return document.getElementById("sq-confirm-message");

    }

    function icon() {

        return document.getElementById("sq-confirm-icon");

    }

    function confirmButton() {

        return document.getElementById("sq-confirm-ok");

    }

    function cancelButton() {

        return document.getElementById("sq-confirm-cancel");

    }

    /* ======================================================
       Open
    ====================================================== */

    function open(config = {}) {

        options = Object.assign({

            title: "Confirmation",

            message: "Are you sure?",

            confirmText: "Confirm",

            cancelText: "Cancel",

            type: "warning",

            closeOnOverlay: true,

            closeOnEscape: true,

            onConfirm: null,

            onCancel: null

        }, config);

        previousFocus = document.activeElement;

        title().textContent = options.title;

        message().textContent = options.message;

        confirmButton().textContent = options.confirmText;

        cancelButton().textContent = options.cancelText;

        updateType(options.type);

        dialog().classList.add("is-open");

        dialog().setAttribute(

            "aria-hidden",

            "false"

        );

        document.body.style.overflow = "hidden";

        confirmButton().focus();

    }

    /* ======================================================
       Close
    ====================================================== */

    function close() {

        dialog().classList.remove(

            "is-open"

        );

        dialog().setAttribute(

            "aria-hidden",

            "true"

        );

        document.body.style.overflow = "";

        if (previousFocus) {

            previousFocus.focus();

        }

    }

    /* ======================================================
       Update Type
    ====================================================== */

    function updateType(type) {

        const icons = {

            success: "bi-check-circle",

            warning: "bi-exclamation-triangle",

            danger: "bi-trash",

            info: "bi-info-circle"

        };

        const button = {

            success: "sq-btn-success",

            warning: "sq-btn-warning",

            danger: "sq-btn-danger",

            info: "sq-btn-primary"

        };

        icon().className =

            "sq-confirm-icon sq-confirm-icon-" +

            type;

        icon().innerHTML =

            `<i class="bi ${icons[type] || icons.warning}"></i>`;

        confirmButton().className =

            "sq-btn " +

            (button[type] ||

            "sq-btn-primary");

    }

    /* ======================================================
       Promise
    ====================================================== */

    function confirm(config) {

        return new Promise(function (

            resolve

        ) {

            open(Object.assign({}, config, {

                onConfirm: function () {

                    close();

                    resolve(true);

                },

                onCancel: function () {

                    close();

                    resolve(false);

                }

            }));

        });

    }

    /* ======================================================
       Events
    ====================================================== */

    function bindEvents() {

        confirmButton()

            .addEventListener(

                "click",

                function () {

                    if (

                        typeof options.onConfirm ===

                        "function"

                    ) {

                        options.onConfirm();

                    }

                    else {

                        close();

                    }

                }

            );

        cancelButton()

            .addEventListener(

                "click",

                function () {

                    if (

                        typeof options.onCancel ===

                        "function"

                    ) {

                        options.onCancel();

                    }

                    else {

                        close();

                    }

                }

            );

        document

            .querySelectorAll(

                "[data-sq-confirm-cancel]"

            )

            .forEach(function (

                element

            ) {

                element.addEventListener(

                    "click",

                    function () {

                        if (

                            options.closeOnOverlay

                        ) {

                            if (

                                typeof options.onCancel ===

                                "function"

                            ) {

                                options.onCancel();

                            }

                            else {

                                close();

                            }

                        }

                    }

                );

            });

        document.addEventListener(

            "keydown",

            function (event) {

                if (

                    event.key === "Escape" &&

                    options.closeOnEscape &&

                    dialog().classList.contains(

                        "is-open"

                    )

                ) {

                    if (

                        typeof options.onCancel ===

                        "function"

                    ) {

                        options.onCancel();

                    }

                    else {

                        close();

                    }

                }

            }

        );

    }

    /* ======================================================
       Helpers
    ====================================================== */

    function alert(messageText) {

        open({

            title: "Information",

            message: messageText,

            type: "info",

            cancelText: "",

            confirmText: "OK"

        });

        cancelButton().style.display = "none";

    }

    function success(messageText) {

        open({

            title: "Success",

            message: messageText,

            type: "success",

            cancelText: "",

            confirmText: "OK"

        });

        cancelButton().style.display = "none";

    }

    function error(messageText) {

        open({

            title: "Error",

            message: messageText,

            type: "danger",

            cancelText: "",

            confirmText: "OK"

        });

        cancelButton().style.display = "none";

    }

    /* ======================================================
       Init
    ====================================================== */

    function init() {

        bindEvents();

    }

    SQ.confirmDialog = {

        init,

        open,

        close,

        confirm,

        alert,

        success,

        error

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

                event.detail.name ===

                "confirm-dialog"

            ) {

                init();

            }

        }

    );

})(window, document);