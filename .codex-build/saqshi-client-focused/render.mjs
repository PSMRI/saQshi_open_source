import fs from 'node:fs/promises'; import path from 'node:path'; import { FileBlob, PresentationFile } from '@oai/artifact-tool';
const root=process.cwd(),out=path.join(root,'.codex-build','saqshi-client-focused','renders');await fs.mkdir(out,{recursive:true});
const p=await PresentationFile.importPptx(await FileBlob.load(path.join(root,'output','presentations','Saqshi_Client_Proposal_Healthcare.pptx')));
for(let i=0;i<p.slides.items.length;i++){const q=await p.slides.items[i].export({format:'png',scale:1});await fs.writeFile(path.join(out,`slide-${i+1}.png`),new Uint8Array(await q.arrayBuffer()));}console.log(p.slides.items.length);
