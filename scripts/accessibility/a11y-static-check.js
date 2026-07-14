#!/usr/bin/env node
/*
 * ==========================================================
 * SaQshi Open Source
 * Static Accessibility Checker
 * a11y-static-check.js
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

function stripTags(value) {
    return String(value || "")
        .replace(/<script[\s\S]*?<\/script>/gi, "")
        .replace(/<style[\s\S]*?<\/style>/gi, "")
        .replace(/<[^>]+>/g, "")
        .replace(/\s+/g, " ")
        .trim();
}

function hasAccessibleName(tag, inner = "") {
    const a = attrs(tag);
    return Boolean(
        a["aria-label"] ||
        a["aria-labelledby"] ||
        a.title ||
        stripTags(inner)
    );
}

function labelFor(content, id) {
    if (!id) return false;
    const escaped = id.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    return new RegExp(`<label[^>]+for=["']${escaped}["']`, "i").test(content);
}

function duplicateIds(content) {
    const ids = {};
    const re = /\sid=["']([^"']+)["']/gi;
    let match;
    while ((match = re.exec(content))) {
        ids[match[1]] = (ids[match[1]] || 0) + 1;
    }
    return Object.keys(ids).filter((id) => ids[id] > 1);
}

function auditFile(file) {
    const rawContent = fs.readFileSync(file, "utf8");
    const content = rawContent.replace(/<!--[\s\S]*?-->/g, "");
    const issues = [];

    const isFragment = !/<!doctype html|<html/i.test(content);
    const requiresHeading = file.includes(`${path.sep}pages${path.sep}`) || file.includes(`${path.sep}help${path.sep}`);

    if (!isFragment && !/<html[^>]+lang=["'][^"']+["']/i.test(content)) {
        issues.push({ level: "error", code: "HTML_LANG", message: "Full HTML page is missing lang attribute." });
    }

    if (requiresHeading && !/<h[1-6]\b/i.test(content)) {
        issues.push({ level: "warning", code: "HEADING", message: "No heading found in page/component." });
    }

    const buttonRe = /<button\b([^>]*)>([\s\S]*?)<\/button>/gi;
    let button;
    while ((button = buttonRe.exec(content))) {
        if (!hasAccessibleName(button[1], button[2])) {
            issues.push({ level: "error", code: "BUTTON_NAME", message: "Button has no accessible name." });
        }
    }

    const imgRe = /<img\b([^>]*)>/gi;
    let img;
    while ((img = imgRe.exec(content))) {
        const a = attrs(img[1]);
        if (typeof a.alt === "undefined") {
            issues.push({ level: "error", code: "IMG_ALT", message: "Image is missing alt attribute." });
        }
    }

    const controlRe = /<(input|select|textarea)\b([^>]*)>/gi;
    let control;
    while ((control = controlRe.exec(content))) {
        const tagName = control[1].toLowerCase();
        const a = attrs(control[2]);
        const type = String(a.type || "").toLowerCase();

        if (type === "hidden") continue;

        const named = Boolean(
            a["aria-label"] ||
            a["aria-labelledby"] ||
            a.title ||
            labelFor(content, a.id)
        );

        if (!named) {
            issues.push({
                level: "error",
                code: "CONTROL_NAME",
                message: `${tagName} is missing label, aria-label, aria-labelledby or title.`
            });
        }
    }

    const tableRe = /<table\b[\s\S]*?<\/table>/gi;
    let table;
    while ((table = tableRe.exec(content))) {
        if (!/<th\b/i.test(table[0]) && !/role=["']table["']/i.test(table[0])) {
            issues.push({ level: "warning", code: "TABLE_HEADER", message: "Table has no th header cells." });
        }
    }

    duplicateIds(content).forEach((id) => {
        issues.push({ level: "error", code: "DUPLICATE_ID", message: `Duplicate id: ${id}` });
    });

    return {
        file,
        status: issues.some((issue) => issue.level === "error") ? "needs_fix" : (issues.length ? "review" : "pass"),
        issues
    };
}

function main() {
    const files = ROOTS.flatMap((root) => walk(root)).sort();
    const results = files.map(auditFile);
    const summary = {
        checked_at: new Date().toISOString(),
        files_checked: results.length,
        passed: results.filter((item) => item.status === "pass").length,
        review: results.filter((item) => item.status === "review").length,
        needs_fix: results.filter((item) => item.status === "needs_fix").length,
        errors: results.reduce((sum, item) => sum + item.issues.filter((issue) => issue.level === "error").length, 0),
        warnings: results.reduce((sum, item) => sum + item.issues.filter((issue) => issue.level === "warning").length, 0)
    };

    const output = path.join("docs", "testing", "wcag_static_audit_results.json");
    fs.mkdirSync(path.dirname(output), { recursive: true });
    fs.writeFileSync(output, JSON.stringify({ summary, results }, null, 2));

    console.log(JSON.stringify(summary, null, 2));
    console.log(`Result saved: ${output}`);

    if (summary.errors > 0) {
        process.exitCode = 1;
    }
}

main();
