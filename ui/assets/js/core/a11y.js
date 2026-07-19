/*!
 * SaQshi runtime accessibility helpers.
 * Adds accessible names and summaries for dynamic controls after page render.
 */

(function (window, document) {
    "use strict";

    window.SQ = window.SQ || {};
    const SQ = window.SQ;

    function textFromIcon(button) {
        return String(button.textContent || "")
            .replace(/\s+/g, " ")
            .trim();
    }

    function labelDynamicControls(root) {
        const scope = root || document;

        scope.querySelectorAll("button:not([aria-label]):not([aria-labelledby])").forEach(function (button) {
            const label = textFromIcon(button) || button.getAttribute("title") || button.dataset.action || "Action";
            button.setAttribute("aria-label", label);
        });

        scope.querySelectorAll("a:not([aria-label]):not([aria-labelledby])").forEach(function (link) {
            const label = textFromIcon(link) || link.getAttribute("title");
            if (label) {
                link.setAttribute("aria-label", label);
            }
        });

        scope.querySelectorAll("input, select, textarea").forEach(function (control) {
            if (control.hasAttribute("aria-label") || control.hasAttribute("aria-labelledby")) {
                return;
            }

            const id = control.getAttribute("id");
            const label = id ? scope.querySelector(`label[for="${window.CSS && CSS.escape ? CSS.escape(id) : id}"]`) : null;
            const placeholder = control.getAttribute("placeholder");

            if (label && label.textContent.trim()) {
                control.setAttribute("aria-label", label.textContent.trim());
            } else if (placeholder) {
                control.setAttribute("aria-label", placeholder);
            }
        });

        scope.querySelectorAll("table:not([aria-label]):not([aria-labelledby])").forEach(function (table) {
            const heading = table.closest(".sq-card")?.querySelector("h2,h3,h4")?.textContent?.trim();
            table.setAttribute("aria-label", heading || "Data table");
        });
    }

    function enhance(root) {
        labelDynamicControls(root || document);
    }

    SQ.a11y = {
        enhance,
        labelDynamicControls
    };

    document.addEventListener("DOMContentLoaded", function () {
        enhance(document);
    });

    document.addEventListener("sq:page-ready", function () {
        enhance(document.getElementById("sq-page-content") || document);
    });
})(window, document);
