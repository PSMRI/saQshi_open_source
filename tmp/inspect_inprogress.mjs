import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";
import fs from "node:fs/promises";

const source = "C:/Users/manish_k/Downloads/inprogress.xlsx";
const input = await FileBlob.load(source);
const workbook = await SpreadsheetFile.importXlsx(input);

for (const query of [
  { kind: "sheet", include: "id,name", maxChars: 2000 },
  { kind: "workbook,sheet,table", maxChars: 12000, tableMaxRows: 200, tableMaxCols: 80, tableMaxCellChars: 180 },
]) {
  const result = await workbook.inspect(query);
  console.log(result.ndjson);
}

const sheet = workbook.worksheets.getItem("Sheet1");
const rows = sheet.getRange("A1:Q185").values;
console.log(JSON.stringify({ headers: rows[0], rows: rows.slice(1) }, null, 2));
await fs.writeFile("tmp/inprogress_assessment_ids.json", JSON.stringify(rows.slice(1).map(row => Number(row[4]))));
