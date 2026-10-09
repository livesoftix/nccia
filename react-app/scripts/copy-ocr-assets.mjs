import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const coreSrcDir = path.join(root, 'node_modules/tesseract.js-core');
const outDir = path.join(root, '../public/react/tesseract');
const langDir = path.join(outDir, 'lang');

fs.mkdirSync(langDir, { recursive: true });

try {
  fs.copyFileSync(
    path.join(root, 'node_modules/tesseract.js/dist/worker.min.js'),
    path.join(outDir, 'worker.min.js'),
  );
  console.log('Copied worker.min.js');
} catch (e) {
  console.log('Worker asset already present or in use');
}

const coreFiles = fs.readdirSync(coreSrcDir).filter((f) => /^tesseract-core.*\.wasm\.js$/.test(f));
for (const name of coreFiles) {
  try {
    fs.copyFileSync(path.join(coreSrcDir, name), path.join(outDir, name));
    console.log('Copied', name);
  } catch (e) {
    console.log('Core file already present:', name);
  }
}

async function download(url, dest) {
  if (fs.existsSync(dest)) {
    console.log('Already present:', path.basename(dest));
    return;
  }
  console.log('Downloading', path.basename(dest), '…');
  const res = await fetch(url);
  if (!res.ok) {
    throw new Error(`Download failed ${url}: ${res.status}`);
  }
  fs.writeFileSync(dest, Buffer.from(await res.arrayBuffer()));
  console.log('Saved', path.basename(dest));
}

await download(
  'https://cdn.jsdelivr.net/npm/@tesseract.js-data/eng/4.0.0/eng.traineddata.gz',
  path.join(langDir, 'eng.traineddata.gz'),
);

const htaccess = `AddType application/javascript .js .mjs .wasm.js
AddType application/wasm .wasm
<IfModule mod_headers.c>
  Header set Access-Control-Allow-Origin "*"
</IfModule>
`;
fs.writeFileSync(path.join(outDir, '.htaccess'), htaccess);

console.log(`OCR assets ready (${coreFiles.length} core variants) in public/react/tesseract/`);
