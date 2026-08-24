import fs from "node:fs/promises";
import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const file = await FileBlob.load("subtype_facility_type_mismatches_20260824.xlsx");
const workbook = await SpreadsheetFile.importXlsx(file);
const check = await workbook.inspect({
  kind: "table",
  range: "Summary!A1:E10",
  include: "values,formulas",
  tableMaxRows: 10,
  tableMaxCols: 5,
});
console.log(check.ndjson);
const preview = await workbook.render({ sheetName: "Summary", range: "A1:E10", scale: 2, format: "png" });
await fs.writeFile("subtype_mismatch_summary_preview.png", new Uint8Array(await preview.arrayBuffer()));
