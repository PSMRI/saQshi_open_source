#!/usr/bin/env node
/*
 * Lightweight contrast check for key SaQshi light/dark theme color pairs.
 */

const pairs = [
  ["Light body text", "#0f172a", "#f4f7fb"],
  ["Light surface text", "#0f172a", "#ffffff"],
  ["Light secondary text", "#334155", "#ffffff"],
  ["Light primary button", "#ffffff", "#2563eb"],
  ["Light input text", "#0f172a", "#ffffff"],
  ["Dark body text", "#f8fafc", "#0b1220"],
  ["Dark surface text", "#f8fafc", "#172033"],
  ["Dark secondary text", "#dbe4f0", "#172033"],
  ["Dark primary button", "#0b1220", "#60a5fa"],
  ["Dark input text", "#f8fafc", "#0f172a"],
  ["Dark muted text", "#a9b6c8", "#172033"]
];

function hexToRgb(hex) {
  const clean = hex.replace("#", "");
  return [0, 2, 4].map(i => parseInt(clean.slice(i, i + 2), 16) / 255);
}

function linear(value) {
  return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
}

function luminance(hex) {
  const [r, g, b] = hexToRgb(hex).map(linear);
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function ratio(fg, bg) {
  const a = luminance(fg);
  const b = luminance(bg);
  const lighter = Math.max(a, b);
  const darker = Math.min(a, b);
  return (lighter + 0.05) / (darker + 0.05);
}

let failed = 0;
pairs.forEach(([name, fg, bg]) => {
  const value = ratio(fg, bg);
  const pass = value >= 4.5;
  console.log(`${pass ? "PASS" : "FAIL"} ${name}: ${value.toFixed(2)}:1`);
  if (!pass) failed += 1;
});

process.exit(failed ? 1 : 0);
