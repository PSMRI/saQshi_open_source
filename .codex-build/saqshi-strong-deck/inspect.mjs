import fs from 'node:fs/promises';
import path from 'node:path';
import { FileBlob, PresentationFile } from '@oai/artifact-tool';
const base=path.join(process.cwd(),'.codex-build','saqshi-strong-deck');
const pres=await PresentationFile.importPptx(await FileBlob.load(path.join(base,'source.pptx')));
const snap=await pres.inspect({kind:'deck,slide,textbox,shape,image,table,chart,notes,layout',maxChars:40000});
await fs.writeFile(path.join(base,'inspect.ndjson'),snap.ndjson);
await fs.mkdir(path.join(base,'source-renders'),{recursive:true});
for(let i=0;i<pres.slides.items.length;i++){const im=await pres.slides.items[i].export({format:'png',scale:1});await fs.writeFile(path.join(base,'source-renders',`slide-${i+1}.png`),new Uint8Array(await im.arrayBuffer()));}
console.log(`slides=${pres.slides.items.length}`);
