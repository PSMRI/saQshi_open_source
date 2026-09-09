import fs from 'node:fs/promises';
import path from 'node:path';
import { FileBlob, PresentationFile } from '@oai/artifact-tool';
const src=path.join(process.cwd(),'output','presentations','Saqshi_Client_Presentation.pptx');
const out=path.join(process.cwd(),'.codex-build','saqshi-client-deck','renders');
await fs.mkdir(out,{recursive:true});
const pres=await PresentationFile.importPptx(await FileBlob.load(src));
for(let i=0;i<pres.slides.items.length;i++){
 const img=await pres.slides.items[i].export({format:'png',scale:1});
 await fs.writeFile(path.join(out,`slide-${i+1}.png`),new Uint8Array(await img.arrayBuffer()));
}
console.log(`rendered ${pres.slides.items.length} slides`);
