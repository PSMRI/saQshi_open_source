import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";
import fs from "node:fs/promises";

const workbook = await SpreadsheetFile.importXlsx(await FileBlob.load("C:/Users/manish_k/Downloads/pending.xlsx"));
const sheetInfo = await workbook.inspect({ kind: "sheet", include: "id,name", maxChars: 1000 });
console.log(sheetInfo.ndjson);
const sheet = workbook.worksheets.getItemAt(0);
const used = sheet.getUsedRange(true);
const rows = used.values;
console.log(JSON.stringify({ headers: rows[0], rowCount: rows.length - 1, rows: rows.slice(1) }, null, 2));
const assessmentIdIndex = rows[0].findIndex(value => String(value).trim().toLowerCase() === "assessment id");
if (assessmentIdIndex < 0) throw new Error("Assessment ID column was not found.");
await fs.writeFile("tmp/pending_assessment_ids.json", JSON.stringify(rows.slice(1).map(row => Number(row[assessmentIdIndex])).filter(Number.isFinite)));
