// Run from any directory: node docs/design/erabook/extract-assets.mjs [--check]
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';

const root = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.resolve(root, '../../../frontend/package.json'));
const { chromium } = require('@playwright/test');
const check = process.argv.includes('--check');
const sources = [
  { category: 'icons', file: 'Content.svg', nodeId: '430:2962', expected: 1950 },
  { category: 'components', file: 'Content (2).svg', nodeId: '432:3910', expected: 189 },
  { category: 'elements', file: 'Content (3).svg', nodeId: '560:15291', expected: 119 },
];
const slug = value => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'unnamed';
const decodeXml = value => value.replace(/&(amp|lt|gt|quot|apos);/g, (_,name)=>({amp:'&',lt:'<',gt:'>',quot:'"',apos:"'"})[name]);
const sha256 = data => createHash('sha256').update(data).digest('hex');
const xml = await fs.readFile(path.join(root, 'structure.xml'), 'utf8');
const catalog = JSON.parse(await fs.readFile(path.join(root, 'components.json'), 'utf8'));
const browser = await chromium.launch({ headless: true });
const index = [];
try {
  const page = await browser.newPage();
  // No SVG script or remote resource is allowed to execute/load.
  await page.route('**/*', route => route.abort());
  await page.setContent('<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data: blob:; style-src \'unsafe-inline\'">');
  for (const source of sources) {
    const svg = await fs.readFile(path.join(root, 'svg', source.file), 'utf8');
    const result = await page.evaluate(({ xml, svg, source }) => {
      const parse = text => {
        const doc = new DOMParser().parseFromString(text, 'application/xml');
        if (doc.querySelector('parsererror')) throw Error(doc.querySelector('parsererror').textContent);
        return doc;
      };
      const metadata = parse(xml);
      const origin = metadata.getElementById(source.nodeId);
      if (!origin) throw Error('Missing metadata origin ' + source.nodeId);
      const doc = parse(svg);
      const sourceSvg = doc.documentElement;
      if (sourceSvg.querySelector('script, animate, set')) throw Error('Active SVG is unsupported');
      for (const n of sourceSvg.querySelectorAll('foreignObject')) {
        if (n.children.length !== 1 || n.firstElementChild.localName !== 'div' || n.firstElementChild.children.length || n.textContent.trim() || !n.firstElementChild.getAttribute('style')?.startsWith('backdrop-filter:blur(')) throw Error('Unsupported foreignObject');
      }
      for (const n of sourceSvg.querySelectorAll('*')) for (const a of n.attributes) {
        if (/^on/i.test(a.name)) throw Error('SVG event handler');
        if (a.localName === 'href' && !a.value.startsWith('#') && !a.value.startsWith('data:image/')) throw Error('External SVG reference');
      }
      document.body.replaceChildren(document.importNode(sourceSvg, true));
      const live = document.body.firstElementChild;
      const vb = live.viewBox.baseVal;
      if (+origin.getAttribute('width') !== vb.width || +origin.getAttribute('height') !== vb.height) throw Error('Mismatched source dimensions');
      const round = n => +n.toFixed(6);
      const targets = [...origin.querySelectorAll('symbol')].map(n => {
        const ancestors = []; let x = 0; let y = 0;
        for (let p = n; p !== origin; p = p.parentElement) {
          x += +(p.getAttribute('x') || 0); y += +(p.getAttribute('y') || 0);
          ancestors.unshift({ id: p.id, name: p.getAttribute('name') });
        }
        return { id: n.id, name: n.getAttribute('name'), parentId: n.parentElement.id,
          hierarchy: ancestors, bounds: { x: round(x), y: round(y), width: +n.getAttribute('width'), height: +n.getAttribute('height') } };
      });
      const definitions = new Map([...live.querySelectorAll('[id]')].map(n => [n.id, n]));
      const nonRendering = new Set(['defs', 'mask', 'clipPath', 'filter', 'linearGradient', 'radialGradient', 'pattern', 'metadata', 'title', 'desc']);
      const boxes = new Map();
      for (const n of live.querySelectorAll('*')) {
        if (nonRendering.has(n.localName) || n.closest('defs,mask,clipPath,filter,pattern')) continue;
        if (!(n instanceof SVGGraphicsElement)) continue;
        // Figma's backdrop blur wrapper extends outside its clipped keyboard.
        const geometry = n.localName === 'foreignObject' && n.nextElementSibling?.hasAttribute('data-figma-bg-blur-radius') ? n.nextElementSibling : n;
        const b = geometry.getBBox(); const matrix = live.getCTM().inverse().multiply(geometry.getCTM());
        const points = [[b.x,b.y],[b.x+b.width,b.y],[b.x,b.y+b.height],[b.x+b.width,b.y+b.height]].map(([x,y]) => new DOMPoint(x,y).matrixTransform(matrix));
        const xs = points.map(p => p.x), ys = points.map(p => p.y);
        boxes.set(n, { x: Math.min(...xs), y: Math.min(...ys), width: Math.max(...xs)-Math.min(...xs), height: Math.max(...ys)-Math.min(...ys) });
      }
      const contains = (outer, inner, epsilon = 0.05) => inner.x >= outer.x-epsilon && inner.y >= outer.y-epsilon && inner.x+inner.width <= outer.x+outer.width+epsilon && inner.y+inner.height <= outer.y+outer.height+epsilon;
      const intersects = (a,b) => a.x <= b.x+b.width+0.05 && a.x+a.width+0.05 >= b.x && a.y <= b.y+b.height+0.05 && a.y+a.height+0.05 >= b.y;
      // ponytail: geometry-based ownership for these flattened exports only;
      // overlapping/changed layouts require fresh per-node Figma exports.
      function select(node, bounds, warnings) {
        if (nonRendering.has(node.localName)) return null;
        const box = boxes.get(node);
        if (!box || !intersects(bounds, box)) return null;
        if (node.localName === 'g' || node.localName === 'svg') {
          const clone = node.cloneNode(false);
          for (const child of node.children) { const selected = select(child, bounds, warnings); if (selected) clone.append(selected); }
          return clone.children.length ? clone : null;
        }
        if (contains(bounds, box, bounds.width===0 || bounds.height===0 ? 0.6 : 0.05)) return node.cloneNode(true);
        // Large enclosing backgrounds belong to the board, not the asset.
        if (!contains(box, bounds) && contains(bounds, {x: box.x+box.width/2, y: box.y+box.height/2, width: 0, height: 0})) {
          warnings.push({tag:node.localName,reason:'geometry crosses nominal component bounds',bounds:box});
          return node.cloneNode(true);
        }
        return null;
      }
      const serializer = new XMLSerializer();
      return targets.map(target => {
        const warnings = [], selected = [];
        const selectionBounds = {...target.bounds, x:target.bounds.x-(target.bounds.width===0?1:0), y:target.bounds.y-(target.bounds.height===0?1:0), height:target.bounds.height || 1, width:target.bounds.width || 1};
        // Verified source exception: Live badge extends below the 48px avatar;
        // include its 41x24 badge and 5px outer ring, not just nominal bounds.
        if(target.id==='1132:36982') Object.assign(selectionBounds,{x:target.bounds.x-3,y:target.bounds.y-3,width:54,height:66});
        for (const node of live.children) { const n = select(node,selectionBounds,warnings); if(n) selected.push(n); }
        if (!selected.length) throw Error('Empty asset ' + target.id);
        const used = new Set();
        const refs = text => [...text.matchAll(/url\(#([^)]*)\)|(?:xlink:)?href="#([^"]+)"/g)].map(m=>m[1]||m[2]);
        const pending = selected.flatMap(n=>refs(serializer.serializeToString(n)));
        const defs = [];
        while(pending.length) {
          const id = pending.pop(); if(used.has(id)) continue; used.add(id);
          const n = definitions.get(id); if(!n) throw Error('Missing SVG definition '+id);
          const text = serializer.serializeToString(n); defs.push(text); pending.push(...refs(text));
        }
        const {width,height} = selectionBounds;
        const tx = -selectionBounds.x, ty = -selectionBounds.y;
        const title = target.name.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const body = selected.map(n=>serializer.serializeToString(n)).join('\n');
        return {...target,warnings,renderBounds:selectionBounds,renderSize:{width,height},selectedElements:selected.length,svg:`<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" fill="none" overflow="hidden" role="img" aria-labelledby="asset-title"><title id="asset-title">${title}</title><defs>${defs.join('\n')}</defs><g transform="translate(${tx} ${ty})">${body}</g></svg>\n`};
      });
    }, { xml, svg, source });
    assert.equal(result.length, source.expected, source.category);
    for (const { svg: asset, ...item } of result) {
      const original = catalog.find(n=>n.id===item.id);
      assert(original && decodeXml(original.name)===item.name, 'Catalog mismatch '+item.id);
      const parts = source.category==='icons' ? item.name.split('/') : [item.hierarchy.at(-2).name, item.name];
      const filename = `${slug(parts.pop()).slice(0,170)}--${item.id.replace(':','-')}`;
      const relative = path.posix.join(source.category,...parts.map(slug),filename);
      const record = {fileKey:'LwmUj9WEuCOARHRu2xnu0X',nodeId:item.id,type:'COMPONENT',...item,
        source:{svg:`svg/${source.file}`,contentNodeId:source.nodeId,sha256:sha256(svg),mapping:'MCP hierarchy coordinates + SVG geometry'},
        variantsFromName:Object.fromEntries(item.name.split(', ').filter(s=>s.includes('=')).map(s=>[s.slice(0,s.indexOf('=')),s.slice(s.indexOf('=')+1)])),
        svg:`${relative}.svg`,sha256:sha256(asset),limitations:['Outlined text; no editable typography','Not native Figma component properties or Auto Layout','Viewport uses nominal bounds; effects outside the frame are clipped']};
      if(check) {
        assert.equal(await fs.readFile(path.join(root,record.svg),'utf8'),asset,record.svg);
        assert.deepEqual(JSON.parse(await fs.readFile(path.join(root,relative+'.json'),'utf8')),record);
      } else {
        await fs.mkdir(path.dirname(path.join(root,relative)),{recursive:true});
        await fs.writeFile(path.join(root,record.svg),asset);
        await fs.writeFile(path.join(root,relative+'.json'),JSON.stringify(record,null,2)+'\n');
      }
      index.push({nodeId:item.id,name:item.name,category:source.category,svg:record.svg,metadata:relative+'.json',width:item.renderSize.width,height:item.renderSize.height,warnings:item.warnings.length});
    }
    console.log(`${source.category}: ${result.length} assets, ${result.filter(n=>n.warnings.length).length} with bounds warnings`);
  }
  assert.equal(index.length,2258); assert.equal(new Set(index.map(n=>n.nodeId)).size,2258);
  const serialized = JSON.stringify(index,null,2)+'\n';
  if(check) assert.equal(await fs.readFile(path.join(root,'assets-index.json'),'utf8'),serialized);
  else await fs.writeFile(path.join(root,'assets-index.json'),serialized);
  console.log(check?'PASS: regenerated SVG, metadata and catalog match all saved assets':'Exported 2258 SVG + JSON pairs');
} finally { await browser.close(); }
