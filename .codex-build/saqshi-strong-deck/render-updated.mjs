import fs from 'node:fs/promises';
import path from 'node:path';
import { FileBlob, PresentationFile } from '@oai/artifact-tool';
const root=process.cwd(),out=path.join(root,'.codex-build','saqshi-strong-deck','updated-renders');
await fs.mkdir(out,{recursive:true});
const pres=await PresentationFile.importPptx(await FileBlob.load(path.join(root,'output','presentations','Saqshi_Open_Platform_Client_Deck_Updated.pptx')));
for(let i=0;i<pres.slides.items.length;i++){const im=await pres.slides.items[i].export({format:'png',scale:1});await fs.writeFile(path.join(out,`slide-${i+1}.png`),new Uint8Array(await im.arrayBuffer()));}
console.log(`slides=${pres.slides.items.length}`);
