'use strict';

const fs = require('fs');
const path = require('path');

function getTesseractModule() {
  const candidates = [
    'tesseract.js',
    path.join(__dirname, '../react-app/node_modules/tesseract.js'),
    path.join(__dirname, '../node_modules/tesseract.js'),
  ];
  for (const c of candidates) {
    try {
      return require(c);
    } catch {
      // ignore
    }
  }
  throw new Error('tesseract.js not found in node_modules or react-app/node_modules');
}

(async () => {
  const img = process.argv[2];
  if (!img || !fs.existsSync(img)) {
    console.error('Image file not found:', img);
    process.exit(1);
  }

  const { createWorker } = getTesseractModule();

  const tessDir = path.join(__dirname, '../public/react/tesseract');
  const worker = await createWorker('eng', 1, {
    cachePath: tessDir,
    gzip: true,
    errorHandler: () => {},
  });

  const { data } = await worker.recognize(img);
  await worker.terminate();

  process.stdout.write((data && data.text) ? data.text : '');
})().catch((err) => {
  console.error('Node OCR failed:', err && err.message ? err.message : String(err));
  process.exit(1);
});
