// Builds the Bošnjački Insajder logo set with text converted to outlines.
//   node tools/build-logo.mjs
// Output: logo/*.svg + PNG exports, and the SVGs/OG image the plugin renders (plugin/binsider-core/assets/logo).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import opentype from 'opentype.js';
import sharp from 'sharp';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');
const out = path.join(root, 'logo');
const pluginOut = path.join(root, 'plugin', 'binsider-core', 'assets', 'logo');
fs.mkdirSync(out, { recursive: true });
fs.mkdirSync(pluginOut, { recursive: true });

// Brand tokens (keep in sync with theme.json)
const FOREST = '#0E3B2C'; // tamnozelena
const GREEN = '#1D7A4E';
const BRASS = '#C49A3A'; // mesing
const PAPER = '#F5F6F1';
const INK = '#15201B';
const RED = '#C8102E';

const fsrc = (pkg, file) => path.join(here, 'node_modules/@fontsource', pkg, 'files', file);
const load = (p) => opentype.parse(fs.readFileSync(p).buffer.slice(0));

// Fontsource splits glyphs by subset; combine basic Latin + Latin Extended (Š, Č, Ž, Ć, Đ).
const serif = [load(fsrc('newsreader', 'newsreader-latin-800-normal.woff')), load(fsrc('newsreader', 'newsreader-latin-ext-800-normal.woff'))];
const sans = [load(fsrc('schibsted-grotesk', 'schibsted-grotesk-latin-800-normal.woff')), load(fsrc('schibsted-grotesk', 'schibsted-grotesk-latin-ext-800-normal.woff'))];

// Own serializer: opentype.js toPathData() occasionally emits "NaN" for valid coordinates.
const f2 = (n) => (+n).toFixed(2);
function pathData(p) {
  return p.commands.map((c) => {
    if (c.type === 'M' || c.type === 'L') return `${c.type}${f2(c.x)} ${f2(c.y)}`;
    if (c.type === 'Q') return `Q${f2(c.x1)} ${f2(c.y1)} ${f2(c.x)} ${f2(c.y)}`;
    if (c.type === 'C') return `C${f2(c.x1)} ${f2(c.y1)} ${f2(c.x2)} ${f2(c.y2)} ${f2(c.x)} ${f2(c.y)}`;
    return 'Z';
  }).join('');
}

function pick(fonts, ch) {
  for (const f of fonts) {
    const g = f.charToGlyph(ch);
    if (g && g.index !== 0) return { font: f, glyph: g };
  }
  throw new Error(`Glyph missing: ${ch}`);
}

// { d, width } for a string set at (x, baseline y) with size and tracking (em fraction).
function textPath(fonts, str, x, y, size, tracking = 0) {
  let cx = x;
  const parts = [];
  let prev = null;
  for (const ch of str) {
    const { font, glyph } = pick(fonts, ch);
    if (prev && prev.font === font) cx += (font.getKerningValue(prev.glyph, glyph) / font.unitsPerEm) * size;
    if (ch !== ' ') parts.push(pathData(glyph.getPath(cx, y, size)));
    cx += (glyph.advanceWidth / font.unitsPerEm) * size + tracking * size;
    prev = ch === ' ' ? null : { font, glyph };
  }
  return { d: parts.join(''), width: cx - x - tracking * size };
}

const capHeight = (fonts, size) => {
  const bb = pick(fonts, 'H').glyph.getBoundingBox();
  return (bb.y2 / pick(fonts, 'H').font.unitsPerEm) * size;
};

// Kilim rhombus centred at (cx, cy) with half-diagonal r.
const rhombus = (cx, cy, r, fill) =>
  `<path d="M${cx} ${cy - r}L${cx + r} ${cy}L${cx} ${cy + r}L${cx - r} ${cy}Z" fill="${fill}"/>`;

// ---- Mark: forest-green tile, serif "B" + brass "i" whose dot is a kilim rhombus ----
function mark(x, y, s, { ring = false } = {}) {
  const r = s * 0.22;
  const { glyph } = pick(serif, 'B');
  const unit = glyph.getPath(0, 0, 100).getBoundingBox();
  const bSize = ((s * 0.56) / (unit.y2 - unit.y1)) * 100;
  const bb = glyph.getPath(0, 0, bSize).getBoundingBox();
  const bW = bb.x2 - bb.x1;
  const stemW = s * 0.105;
  const gap = s * 0.07;
  const total = bW + gap + stemW;
  const left = x + (s - total) / 2;
  const base = y + s * 0.78; // shared baseline
  const gx = left - bb.x1;
  const gy = base - bb.y2;
  const d = pathData(glyph.getPath(gx, gy, bSize));
  const stemX = left + bW + gap;
  const stemTop = base - s * 0.4;
  const dotR = s * 0.085;
  const dotCy = stemTop - s * 0.14;
  return (
    `<rect x="${x}" y="${y}" width="${s}" height="${s}" rx="${r}" fill="${FOREST}"/>` +
    (ring ? `<rect x="${x + 2}" y="${y + 2}" width="${s - 4}" height="${s - 4}" rx="${r - 2}" fill="none" stroke="${BRASS}" stroke-width="3"/>` : '') +
    `<path d="${d}" fill="${PAPER}"/>` +
    `<rect x="${stemX.toFixed(2)}" y="${stemTop.toFixed(2)}" width="${stemW.toFixed(2)}" height="${(base - stemTop).toFixed(2)}" fill="${BRASS}"/>` +
    rhombus(+(stemX + stemW / 2).toFixed(2), +dotCy.toFixed(2), +dotR.toFixed(2), BRASS)
  );
}

const svg = (w, h, body, title = 'Bošnjački Insajder') =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w.toFixed(0)} ${h.toFixed(0)}" width="${w.toFixed(0)}" height="${h.toFixed(0)}" role="img" aria-label="${title}"><title>${title}</title>${body}</svg>\n`;

const files = {};
files['logo-mark.svg'] = svg(100, 100, mark(0, 0, 100));

// Horizontal: mark + two-line wordmark ("BOŠNJAČKI" small tracked sans over "Insajder" serif).
// Vertical extent of a string at baseline 0: { above, below } in px (ascenders and descenders such as "j").
function extent(fonts, str, size) {
  let above = 0, below = 0;
  for (const ch of str) {
    if (ch === ' ') continue;
    const bb = pick(fonts, ch).glyph.getPath(0, 0, size).getBoundingBox();
    above = Math.max(above, -bb.y1);
    below = Math.max(below, bb.y2);
  }
  return { above, below };
}

// Horizontal: mark + two-line wordmark fitted inside the mark's height, descender of "j" included.
function horizontal(light) {
  const s = 100;
  const x0 = s + 20;
  const smallSize = 19;
  const smallBase = 4 + extent(sans, 'BOŠNJAČKI', smallSize).above;
  const top = smallBase + 9; // gap between the two lines
  const unit = extent(serif, 'Insajder', 100);
  const bigSize = ((s - 2 - top) / (unit.above + unit.below)) * 100;
  const bigBase = top + (unit.above * bigSize) / 100;
  const small = textPath(sans, 'BOŠNJAČKI', x0 + 2, smallBase, smallSize, 0.34);
  const big = textPath(serif, 'Insajder', x0, bigBase, bigSize, -0.02);
  const w = x0 + Math.max(small.width, big.width) + 4;
  return svg(w, s, mark(0, 0, s, { ring: light }) + `<path d="${small.d}" fill="${light ? BRASS : GREEN}"/><path d="${big.d}" fill="${light ? PAPER : INK}"/>`);
}
files['logo-horizontal.svg'] = horizontal(false);
files['logo-horizontal-white.svg'] = horizontal(true);

// Stacked: mark above centred wordmark; height follows the real descender.
function stacked(light) {
  const s = 100;
  const big = textPath(serif, 'Insajder', 0, 0, 70, -0.02);
  const small = textPath(sans, 'BOŠNJAČKI', 0, 0, 19, 0.34);
  const w = Math.max(big.width, small.width) + 8;
  const sm = textPath(sans, 'BOŠNJAČKI', (w - small.width) / 2, s + 40, 19, 0.34);
  const bg = textPath(serif, 'Insajder', (w - big.width) / 2, s + 100, 70, -0.02);
  const h = s + 100 + extent(serif, 'Insajder', 70).below + 3;
  return svg(w, h, mark((w - s) / 2, 0, s, { ring: light }) + `<path d="${sm.d}" fill="${light ? BRASS : GREEN}"/><path d="${bg.d}" fill="${light ? PAPER : INK}"/>`);
}
files['logo-stacked.svg'] = stacked(false);
files['logo-stacked-white.svg'] = stacked(true);

// Kilim band: repeating rhombus chain (also used by the theme CSS).
function kilim(x, y, w, h) {
  let o = `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${FOREST}"/>`;
  const step = h * 2;
  for (let cx = x + h; cx < x + w + step; cx += step) {
    o += rhombus(cx, y + h / 2, h / 2, BRASS) + rhombus(cx, y + h / 2, h / 5, RED) + rhombus(cx + h, y + h / 2, h / 6, GREEN);
  }
  return o;
}

// Default social share image 1200x630
{
  const W = 1200, H = 630;
  const s = 210;
  const small = textPath(sans, 'BOŠNJAČKI', 80 + s + 48, 262, 40, 0.3);
  const big = textPath(serif, 'Insajder', 80 + s + 40, 392, 150, -0.02);
  const tag = textPath(sans, 'Vijesti iz Crne Gore, Sandžaka, BiH i dijaspore', 80, H - 92, 30, 0.01);
  const url = textPath(sans, 'binsider.me', W - 80 - textPath(sans, 'binsider.me', 0, 0, 30, 0.02).width, H - 92, 30, 0.02);
  const body =
    `<rect width="${W}" height="${H}" fill="${FOREST}"/>` +
    kilim(0, 0, W, 28) +
    mark(80, 170, s, { ring: true }) +
    `<path d="${small.d}" fill="${BRASS}"/><path d="${big.d}" fill="${PAPER}"/>` +
    `<rect x="80" y="${H - 150}" width="${W - 160}" height="2" fill="${BRASS}" opacity="0.6"/>` +
    `<path d="${tag.d}" fill="#C9D6CE"/><path d="${url.d}" fill="${BRASS}"/>`;
  files['og-default.svg'] = svg(W, H, body);
}

for (const [name, content] of Object.entries(files)) fs.writeFileSync(path.join(out, name), content);
for (const name of ['logo-mark.svg', 'logo-horizontal.svg', 'logo-horizontal-white.svg', 'logo-stacked.svg', 'logo-stacked-white.svg']) {
  fs.writeFileSync(path.join(pluginOut, name), files[name]);
}

// PNG exports
const png = (src, dest, width, height) =>
  sharp(Buffer.from(files[src]), { density: 600 }).resize(width, height, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).png().toFile(path.join(out, dest));
await png('logo-mark.svg', 'site-icon-512.png', 512, 512);
await png('logo-mark.svg', 'apple-touch-icon-180.png', 180, 180);
await png('logo-mark.svg', 'favicon-32.png', 32, 32);
await png('logo-horizontal.svg', 'logo-horizontal-1200.png', 1200);
await png('logo-stacked.svg', 'logo-stacked-800.png', 800);
await png('og-default.svg', 'og-default-1200x630.png', 1200, 630);
await sharp(path.join(out, 'og-default-1200x630.png')).jpeg({ quality: 88 }).toFile(path.join(out, 'og-default-1200x630.jpg'));
fs.copyFileSync(path.join(out, 'og-default-1200x630.jpg'), path.join(pluginOut, 'og-default-1200x630.jpg'));
fs.copyFileSync(path.join(out, 'site-icon-512.png'), path.join(pluginOut, 'site-icon-512.png'));

console.log('logo files:', fs.readdirSync(out).join(', '));
