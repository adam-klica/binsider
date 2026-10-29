// Generates abstract placeholder photos for local demo content (never shipped).
//   node tools/build-demo-images.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const out = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'demo', 'images');
fs.mkdirSync(out, { recursive: true });

let seed = 7;
const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
const pick = (a) => a[Math.floor(rnd() * a.length)];

const palettes = [
  ['#1d2b24', '#3c5a48', '#8aa894', '#d9e4dc', '#00843D'],
  ['#2a2a2a', '#555', '#9a9a96', '#e2e2de', '#00843D'],
  ['#203040', '#40607a', '#8fb0c8', '#e6eef4', '#c9a227'],
  ['#3b2a20', '#7a5a40', '#c8a888', '#f0e6da', '#00843D'],
  ['#1a1f2b', '#394b6a', '#7d93b8', '#dfe6f2', '#e10600'],
];

for (let i = 1; i <= 24; i++) {
  const W = 1600, H = 1000;
  const p = palettes[i % palettes.length];
  let shapes = `<rect width="${W}" height="${H}" fill="${p[3]}"/>`;
  // horizon / landscape bands
  const horizon = 380 + rnd() * 300;
  shapes += `<rect y="${horizon}" width="${W}" height="${H - horizon}" fill="${p[2]}"/>`;
  // "buildings"
  let x = -40;
  while (x < W) {
    const w = 80 + rnd() * 220;
    const h = 120 + rnd() * 420;
    shapes += `<rect x="${x}" y="${horizon - h}" width="${w}" height="${h + 4}" fill="${pick(p.slice(0, 2))}"/>`;
    const rows = Math.floor(h / 46);
    for (let r = 0; r < rows; r++) for (let c = 0; c < Math.floor(w / 40); c++) {
      if (rnd() > 0.55) shapes += `<rect x="${x + 12 + c * 40}" y="${horizon - h + 16 + r * 46}" width="18" height="24" fill="${p[3]}" opacity="0.55"/>`;
    }
    x += w + rnd() * 30;
  }
  // foreground and accent
  shapes += `<rect y="${H - 140 - rnd() * 80}" width="${W}" height="400" fill="${p[0]}"/>`;
  shapes += `<circle cx="${200 + rnd() * 1200}" cy="${120 + rnd() * 160}" r="${50 + rnd() * 60}" fill="${p[4]}" opacity="0.9"/>`;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">${shapes}</svg>`;
  await sharp(Buffer.from(svg)).blur(1.2).jpeg({ quality: 78 }).toFile(path.join(out, `demo-${String(i).padStart(2, '0')}.jpg`));
}
console.log('demo images:', fs.readdirSync(out).length);
