import fs from "node:fs/promises";
import { execFileSync } from "node:child_process";
import { Workbook, SpreadsheetFile } from "@oai/artifact-tool";

const outputDir = ".";
console.log("Loading subtype mismatch records...");
const sql = `SELECT
  csc.csqa_id,
  csc.csqa_reference_id,
  csc.area_of_con_id_fk AS concern_id,
  aoc.concern_name,
  csc.c_subtype_id_fk AS checklist_subtype_id,
  aocs.Reference_No AS subtype_reference_no,
  aocs.area_of_con_subtypedeatils AS subtype_details,
  csc.fac_type_id_fk AS checklist_facility_type_id,
  ft.facilities_type AS checklist_facility_type,
  aocs.fac_type_id AS available_facility_type_id,
  aft.facilities_type AS available_facility_type,
  csc.fac_dept_id_fk AS facility_department_id,
  fd.dept_name,
  fd.program_tag,
  csc.Measurable_Element,
  csc.Checkpoint,
  csc.Assessment_Method,
  csc.action_plan
FROM sarbsoft_nqa.concern_subtype_chklist csc
INNER JOIN sarbsoft_nqa.area_of_concern_subtype aocs
  ON aocs.area_of_con_id = csc.area_of_con_id_fk
 AND aocs.c_subtype_id = csc.c_subtype_id_fk
LEFT JOIN sarbsoft_nqa.area_of_concern_subtype exact_match
  ON exact_match.area_of_con_id = csc.area_of_con_id_fk
 AND exact_match.c_subtype_id = csc.c_subtype_id_fk
 AND exact_match.fac_type_id = csc.fac_type_id_fk
LEFT JOIN sarbsoft_nqa.area_of_concern aoc ON aoc.concern_id = csc.area_of_con_id_fk
LEFT JOIN sarbsoft_nqa.facilities_type ft ON ft.fac_type_id = csc.fac_type_id_fk
LEFT JOIN sarbsoft_nqa.facilities_type aft ON aft.fac_type_id = aocs.fac_type_id
LEFT JOIN sarbsoft_nqa.fac_department fd ON fd.fac_dept_id = csc.fac_dept_id_fk
WHERE exact_match.c_subtype_id IS NULL
ORDER BY csc.fac_type_id_fk, csc.area_of_con_id_fk, csc.c_subtype_id_fk, csc.csqa_id`;

const php = `require "api/assets/conn/db.php"; $q=$con->query(${JSON.stringify(sql)}); $rows=[]; while($r=$q->fetch_assoc()) $rows[]=$r; echo json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);`;
const rows = JSON.parse(execFileSync("php", ["-r", php], { cwd: "../..", encoding: "utf8", maxBuffer: 32 * 1024 * 1024 }));
console.log(`Loaded ${rows.length} records; building workbook...`);

const headers = [
  "CSQA ID", "CSQA Reference ID", "Concern ID", "Concern Name", "Checklist Subtype ID",
  "Subtype Reference No.", "Subtype Details", "Checklist Facility Type ID", "Checklist Facility Type",
  "Available Subtype Facility Type ID", "Available Subtype Facility Type", "Department ID", "Department Name",
  "Program Tag", "Measurable Element", "Checkpoint", "Assessment Method", "Action Plan"
];
const keys = [
  "csqa_id", "csqa_reference_id", "concern_id", "concern_name", "checklist_subtype_id",
  "subtype_reference_no", "subtype_details", "checklist_facility_type_id", "checklist_facility_type",
  "available_facility_type_id", "available_facility_type", "facility_department_id", "dept_name",
  "program_tag", "Measurable_Element", "Checkpoint", "Assessment_Method", "action_plan"
];

const summaryMap = new Map();
for (const row of rows) {
  const key = `${row.checklist_facility_type_id}|${row.checklist_facility_type}|${row.available_facility_type_id}|${row.available_facility_type}`;
  summaryMap.set(key, (summaryMap.get(key) || 0) + 1);
}
const summaryRows = [...summaryMap.entries()].map(([key, count]) => [...key.split("|"), count]);

const wb = Workbook.create();
const summary = wb.worksheets.add("Summary");
const data = wb.worksheets.add("Mismatch Records");
console.log("Writing summary...");

summary.showGridLines = false;
summary.mergeCells("A1:E1");
summary.getRange("A1").values = [["Subtype Facility-Type Mismatch Report"]];
summary.getRange("A1:E1").format = { fill: "#1F4E78", font: { bold: true, color: "#FFFFFF", size: 16 }, horizontalAlignment: "center", verticalAlignment: "center" };
summary.getRange("A1:E1").format.rowHeight = 28;
summary.getRange("A3:B4").values = [["Source database", "sarbsoft_nqa"], ["Total mismatch records", rows.length]];
summary.getRange("A3:A4").format = { fill: "#D9EAF7", font: { bold: true } };
summary.getRange("A3:B4").format.borders = { preset: "all", style: "thin", color: "#A6A6A6" };
summary.getRange("A6:E6").values = [["Checklist Facility Type ID", "Checklist Facility Type", "Available Subtype Facility Type ID", "Available Subtype Facility Type", "Mismatch Rows"]];
summary.getRange("A7:E" + (6 + summaryRows.length)).values = summaryRows;
summary.getRange("A6:E6").format = { fill: "#5B9BD5", font: { bold: true, color: "#FFFFFF" }, wrapText: true };
summary.getRange("A6:E" + (6 + summaryRows.length)).format.borders = { preset: "all", style: "thin", color: "#D9E2F3" };
summary.getRange("E7:E" + (6 + summaryRows.length)).format.numberFormat = "#,##0";
summary.getRange("A1:E" + (6 + summaryRows.length)).format.autofitColumns();
summary.getRange("B1:B" + (6 + summaryRows.length)).format.columnWidth = 24;
summary.getRange("D1:D" + (6 + summaryRows.length)).format.columnWidth = 26;

console.log("Writing mismatch records...");
data.showGridLines = false;
data.getRange("A1:R1").values = [headers];
data.getRange("A2:R" + (rows.length + 1)).values = rows.map((row) => keys.map((key) => row[key] ?? ""));
console.log("Formatting mismatch records...");
data.getRange("A1:R1").format = { fill: "#1F4E78", font: { bold: true, color: "#FFFFFF" }, horizontalAlignment: "center", verticalAlignment: "center", wrapText: true };
for (const column of ["D", "G", "N", "O", "P", "Q", "R"]) data.getRange(`${column}1:${column}${rows.length + 1}`).format.columnWidth = column === "R" ? 45 : 28;
data.getRange("A:A").format.columnWidth = 12;
data.getRange("B:B").format.columnWidth = 18;
data.getRange("E:E").format.columnWidth = 16;
data.freezePanes.freezeRows(1);
data.freezePanes.freezeColumns(2);

console.log("Exporting workbook...");
const output = await SpreadsheetFile.exportXlsx(wb);
await output.save(`${outputDir}/subtype_facility_type_mismatches_20260824.xlsx`);
console.log(`Exported ${rows.length} mismatch rows.`);
