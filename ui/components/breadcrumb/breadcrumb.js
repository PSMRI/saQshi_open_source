/*!
 * ==========================================================
 * SQ Breadcrumb Component v1.0
 * ----------------------------------------------------------
 * Project   : SaQshi Open Source
 * Component : Breadcrumb
 * File      : breadcrumb.js
 * License   : MIT
 * ==========================================================
 *
 * Responsibilities
 * ----------------------------------------------------------
 * ✔ Render breadcrumb
 * ✔ Add breadcrumb items
 * ✔ Home shortcut
 * ✔ Active page
 * ✔ Icon support
 * ✔ Accessibility (WCAG 2.2)
 * ✔ Responsive
 * ==========================================================
 */

(function (window, document) {

    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    const DEFAULT_HOME = {

        label: "Dashboard",

        url: "/ui/dashboard.html",

        icon: "bi-house-door"

    };

    let currentItems = [];

    function container() {

        return document.querySelector("[data-sq-breadcrumb]");

    }

    function clear() {

        const element = container();

        if (!element) {

            return;

        }

        element.innerHTML = "";

    }

    function createItem(item, isLast = false) {

        const li = document.createElement("li");

        li.className = "sq-breadcrumb-item";

        if (isLast) {

            li.classList.add("active");

            li.setAttribute(

                "aria-current",

                "page"

            );

            li.innerHTML = `

                ${item.icon ? `<i class="bi ${item.icon}"></i>` : ""}

                <span>${escapeHtml(item.label)}</span>

            `;

        } else {

            li.innerHTML = `

                <a href="${item.url || "#"}">

                    ${item.icon ? `<i class="bi ${item.icon}"></i>` : ""}

                    <span>${escapeHtml(item.label)}</span>

                </a>

            `;

        }

        return li;

    }

    function render(items = []) {

        const element = container();

        if (!element) {

            return;

        }

        clear();

        currentItems = [];

        const list = [];

        list.push(DEFAULT_HOME);

        items.forEach(function (item) {

            list.push(item);

        });

        list.forEach(function (item, index) {

            const last =

                index === list.length - 1;

            element.appendChild(

                createItem(

                    item,

                    last

                )

            );

        });

        currentItems = list;

    }

    function push(item) {

        currentItems.push(item);

        render(currentItems.slice(1));

    }

    function pop() {

        if (

            currentItems.length <= 1

        ) {

            return;

        }

        currentItems.pop();

        render(currentItems.slice(1));

    }

    function reset() {

        currentItems = [];

        render([]);

    }

    function setHome(home) {

        Object.assign(

            DEFAULT_HOME,

            home

        );

        render(currentItems.slice(1));

    }

    function escapeHtml(text) {

        return String(text)

            .replace(/&/g, "&amp;")

            .replace(/</g, "&lt;")

            .replace(/>/g, "&gt;")

            .replace(/"/g, "&quot;")

            .replace(/'/g, "&#039;");

    }

    function init() {

        render([]);

    }

    SQ.breadcrumb = {

        init,

        render,

        push,

        pop,

        reset,

        setHome,

        current: function () {

            return currentItems;

        }

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
                event.detail.name === "breadcrumb"

            ) {

                init();

            }

        }

    );

})(window, document);