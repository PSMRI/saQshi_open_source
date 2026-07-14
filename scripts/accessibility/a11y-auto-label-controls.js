#!/usr/bin/env node
/*
 * ==========================================================
 * SaQshi Open Source
 * Accessibility Control Label Fixer
 * a11y-auto-label-controls.js
 * ==========================================================
 */

const fs = require("fs");
const path = require("path");

const ROOTS = [
    path.join("ui", "pages"),
    path.join("ui", "components"),
    path.join("ui", "layouts"),
    path.join("ui", "help")
];

function walk(dir, out = []) {
    if (!fs.existsSync(dir)) return out;
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            walk(full, out);
        } else if (entry.isFile() && full.endsWith(".html")) {
            out.push(full);
        }
    }
    return out;
}

function attrs(tag) {
    const result = {};
    const re = /([a-zA-Z_:.-]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
    let match;
    while ((match = re.exec(tag))) {
        result[match[1].toLowerCase()] = match[2] ?? match[3] ?? match[4] ?? "";
    }
    return result;
}

function humanize(value) {
    return String(value || "")
        .replace(/^sq[-_]/i, "")
        .replace(/[-_]+/g, " ")
        .replace(/([a-z])([A-Z])/g, "$1 $2")
        .replace(/\s+/g, " ")
        .trim()
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function labelFromAttrs(a, tagName) {
    const base = a.placeholder || a.name || a.id || a.class || tagName;
    const label = humanize(base);
    const type = String(a.type || "").toLowerCase();

    if ((type === "radio" || type === "checkbox") && typeof a.value !== "undefined") {
        return `${label} ${a.value}`.trim();
    }

    return label || humanize(tagName);
}

function hasName(a) {
    return Boolean(a["aria-label"] || a["aria-labelledby"] || a.title);
}

function processFile(file) {
    let content = fs.readFileSync(file, "utf8");
    let changed = false;

    content = content.replace(/<(input|select|textarea)\b([^>]*)>/gi, (match, tagName, attrText) => {
        const a = attrs(attrText);
        const type = String(a.type || "").toLowerCase();

        if (type === "hidden" || hasName(a)) {
            return match;
        }

        const label = labelFromAttrs(a, tagName);
        changed = true;

        return `<${tagName}${attrText} aria-label="${label.replace(/"/g, "&quot;")}">`;
    });

    if (changed) {
        fs.writeFileSync(file, content);
    }

    return changed;
}

const changedFiles = ROOTS.flatMap((root) => walk(root)).filter(processFile);

console.log(`Updated accessible labels in ${changedFiles.length} file(s).`);
changedFiles.forEach((file) => console.log(file));
