// Pixel comparison against the original board crop, plus SVG dependency checks.
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const root = path.dirname(fileURLToPath(import.meta.url));
const { chromium } = createRequire(path.resolve(root,'../../../frontend/package.json'))('@playwright/test');
const index = JSON.parse(await fs.readFile(path.join(root,'assets-index.json'),'utf8'));
const browser = await chromium.launch();
const results = [];
try {
  const page = await browser.newPage();
  await page.route('**/*',route=>route.abort());
  await page.setContent('<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data: blob:; style-src \'unsafe-inline\'">');
  for(const category of ['icons','components','elements']) {
    const entries = index.filter(n=>n.category===category);
    const assets = await Promise.all(entries.map(async n=>({meta:JSON.parse(await fs.readFile(path.join(root,n.metadata),'utf8')),svg:await fs.readFile(path.join(root,n.svg),'utf8')})));
    const source = await fs.readFile(path.join(root,assets[0].meta.source.svg),'utf8');
    const checked = await page.evaluate(async ({source,assets})=>{
      // Chromium taints canvas for foreignObject. Omit only backdrop wrappers
      // from both comparison images; originals retain them in the exported SVG.
      async function image(svg){const clean=svg.replace(/<foreignObject\b[\s\S]*?<\/foreignObject>/g,'');const url=URL.createObjectURL(new Blob([clean],{type:'image/svg+xml'}));const img=new Image();img.src=url;try{await img.decode();return img;}finally{URL.revokeObjectURL(url);}}
      const original=await image(source), canvas=document.createElement('canvas'), ctx=canvas.getContext('2d',{willReadFrequently:true});
      const out=[];
      for(const {meta,svg} of assets){
        const doc=new DOMParser().parseFromString(svg,'image/svg+xml');
        if(doc.querySelector('parsererror'))throw Error(meta.id+' invalid XML');
        const ids=new Set([...doc.querySelectorAll('[id]')].map(n=>n.id));
        for(const match of svg.matchAll(/url\(#([^)]*)\)|(?:xlink:)?href="#([^"]+)"/g))if(!ids.has(match[1]||match[2]))throw Error(meta.id+' missing reference');
        const asset=await image(svg), b=meta.bounds;
        canvas.width=Math.ceil(meta.renderSize.width);canvas.height=Math.ceil(meta.renderSize.height);
        ctx.fillStyle='white';ctx.fillRect(0,0,canvas.width,canvas.height);
        const {x,y}=meta.renderBounds;
        ctx.drawImage(original,-x,-y);
        const expected=ctx.getImageData(0,0,canvas.width,canvas.height).data;
        ctx.clearRect(0,0,canvas.width,canvas.height);ctx.fillStyle='white';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(asset,0,0);
        const actual=ctx.getImageData(0,0,canvas.width,canvas.height).data;
        let different=0,ink=0;
        for(let i=0;i<actual.length;i+=4){if(Math.max(...[0,1,2,3].map(c=>Math.abs(actual[i+c]-expected[i+c])))>24)different++;if(Math.min(actual[i],actual[i+1],actual[i+2])<245)ink++;}
        out.push({nodeId:meta.id,name:meta.name,category:meta.svg.split('/')[0],differentPixels:different,pixels:actual.length/4,ratio:different/(actual.length/4),nonWhitePixels:ink,backdropBlurExcluded:svg.includes('<foreignObject')});
      }
      return out;
    },{source,assets});
    results.push(...checked);console.log(category,checked.length,'checked; >1% difference:',checked.filter(n=>n.ratio>0.01).length);
  }
  assert.equal(results.length,2258);
  const report={method:'Chromium image rasterization, white background, per-channel tolerance 24; compares renderBounds source crop, not native Figma ownership; backdrop foreignObject wrappers omitted from both comparison images',count:results.length,withinTolerance:results.filter(n=>n.differentPixels===0).length,overOnePercent:results.filter(n=>n.ratio>0.01).length,results};
  await fs.writeFile(path.join(root,'asset-verification.json'),JSON.stringify(report,null,2)+'\n');
  console.log(JSON.stringify({...report,results:undefined}));
  assert.equal(report.overOnePercent,0,'An asset differs by more than 1% of pixels; inspect asset-verification.json');
  const sampleIds=['2128:42720','2128:45016','2128:32607','119:15','442:2934','1132:36982','442:900','136:1387'];
  const samples=await Promise.all(sampleIds.map(async id=>{const n=index.find(a=>a.nodeId===id);assert(n,id);return {...n,svg:await fs.readFile(path.join(root,n.svg),'utf8')};}));
  await page.setViewportSize({width:1100,height:900});
  await page.evaluate(samples=>{
    document.body.replaceChildren();document.body.style.cssText='margin:24px;background:#eee;font:14px system-ui;display:grid;grid-template-columns:repeat(3,1fr);gap:16px;align-items:start';
    for(const sample of samples){const card=document.createElement('section');card.style.cssText='padding:16px;background:white;border-radius:8px;overflow:hidden';const title=document.createElement('p');title.textContent=sample.nodeId+' · '+sample.name;const img=new Image();img.src='data:image/svg+xml;base64,'+btoa(unescape(encodeURIComponent(sample.svg)));img.style.cssText='max-width:100%;height:auto;'+(sample.name.includes('Theme=Dark')?'background:#181a20':'');card.append(title,img);document.body.append(card);}
  },samples);
  await page.evaluate(()=>Promise.all([...document.images].map(img=>img.decode())));
  await page.screenshot({path:path.join(root,'assets-preview.png'),fullPage:true});
}finally{await browser.close();}
