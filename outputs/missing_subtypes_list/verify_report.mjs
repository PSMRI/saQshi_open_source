import fs from "node:fs/promises";
import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";
const file = await FileBlob.load("missing_concern_subtype_mappings.xlsx");
const workbook = await SpreadsheetFile.importXlsx(file);
const check = await workbook.inspect({ kind: "table", range: "Missing Concern-Subtype!A1:P9", include: "values", tableMaxRows: 9, tableMaxCols: 16 });
console.log(check.ndjson);
const preview = await workbook.render({ sheetName: "Missing Concern-Subtype", range: "A1:P9", scale: 1, format: "png" });
await fs.writeFile("missing_mapping_preview.png", new Uint8Array(await preview.arrayBuffer()));
