import { execFileSync } from "node:child_process";
import { Workbook, SpreadsheetFile } from "@oai/artifact-tool";

const sql = `SELECT
  csc.csqa_id, csc.csqa_reference_id,
  csc.area_of_con_id_fk AS concern_id, aoc.concern_name, aoc.concern_des,
  csc.c_subtype_id_fk AS missing_subtype_id,
  csc.c_subtype_Reference_No_fk AS checklist_subtype_reference_no,
  csc.fac_type_id_fk AS checklist_facility_type_id, ft.facilities_type AS checklist_facility_type,
  csc.fac_dept_id_fk AS department_id, fd.dept_name, fd.program_tag,
  csc.Measurable_Element, csc.Checkpoint, csc.Assessment_Method, csc.action_plan
FROM sarbsoft_nqa.concern_subtype_chklist csc
LEFT JOIN sarbsoft_nqa.area_of_concern_subtype aocs
  ON aocs.area_of_con_id=csc.area_of_con_id_fk
 AND aocs.c_subtype_id=csc.c_subtype_id_fk
LEFT JOIN sarbsoft_nqa.area_of_concern aoc ON aoc.concern_id=csc.area_of_con_id_fk
LEFT JOIN sarbsoft_nqa.facilities_type ft ON ft.fac_type_id=csc.fac_type_id_fk
LEFT JOIN sarbsoft_nqa.fac_department fd ON fd.fac_dept_id=csc.fac_dept_id_fk
WHERE aocs.c_subtype_id IS NULL
ORDER BY csc.fac_type_id_fk, csc.area_of_con_id_fk, csc.c_subtype_id_fk, csc.csqa_id`;
const php = `require "api/assets/conn/db.php"; $q=$con->query(${JSON.stringify(sql)}); $rows=[]; while($r=$q->fetch_assoc()) $rows[]=$r; echo json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);`;
const rows = JSON.parse(execFileSync("php", ["-r", php], { cwd: "../..", encoding: "utf8", maxBuffer: 16 * 1024 * 1024 }));

const headers = ["CSQA ID", "CSQA Reference ID", "Concern ID", "Concern Name", "Concern Description", "Missing Subtype ID", "Checklist Subtype Reference No.", "Facility Type ID", "Facility Type", "Department ID", "Department Name", "Program Tag", "Measurable Element", "Checkpoint", "Assessment Method", "Action Plan"];
const keys = ["csqa_id", "csqa_reference_id", "concern_id", "concern_name", "concern_des", "missing_subtype_id", "checklist_subtype_reference_no", "checklist_facility_type_id", "checklist_facility_type", "department_id", "dept_name", "program_tag", "Measurable_Element", "Checkpoint", "Assessment_Method", "action_plan"];

const wb = Workbook.create();
const sheet = wb.worksheets.add("Missing Concern-Subtype");
sheet.showGridLines = false;
sheet.mergeCells("A1:P1");
sheet.getRange("A1").values = [["Checklist Rows Missing a Concern–Subtype Mapping"]];
sheet.getRange("A1:P1").format = { fill: "#9C0006", font: { bold: true, color: "#FFFFFF", size: 15 }, horizontalAlignment: "center" };
sheet.getRange("A1:P1").format.rowHeight = 28;
sheet.getRange("A3:B4").values = [["Source database", "sarbsoft_nqa"], ["Missing mapping rows", rows.length]];
sheet.getRange("A3:A4").format = { fill: "#FCE4D6", font: { bold: true } };
sheet.getRange("A3:B4").format.borders = { preset: "all", style: "thin", color: "#A6A6A6" };
sheet.getRange("A6:P6").values = [headers];
sheet.getRange(`A7:P${rows.length + 6}`).values = rows.map((row) => keys.map((key) => row[key] ?? ""));
sheet.getRange("A6:P6").format = { fill: "#C00000", font: { bold: true, color: "#FFFFFF" }, horizontalAlignment: "center", wrapText: true };
for (const col of ["D", "E", "M", "N", "O", "P"]) sheet.getRange(`${col}1:${col}${rows.length + 6}`).format.columnWidth = col === "P" ? 45 : 30;
for (const col of ["A", "B", "C", "F", "H", "J"]) sheet.getRange(`${col}1:${col}${rows.length + 6}`).format.columnWidth = 15;
sheet.getRange(`A6:P${rows.length + 6}`).format.borders = { preset: "insideHorizontal", style: "thin", color: "#F2F2F2" };
sheet.freezePanes.freezeRows(6);
sheet.freezePanes.freezeColumns(2);

const output = await SpreadsheetFile.exportXlsx(wb);
await output.save("missing_concern_subtype_mappings.xlsx");
console.log(`Exported ${rows.length} rows.`);
