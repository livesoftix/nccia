import fs from 'fs';
import path from 'path';
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.mjs';
import { createWorker } from 'tesseract.js';

async function extractPdf(pdfPath) {
  const data = new Uint8Array(fs.readFileSync(pdfPath));
  const pdf = await pdfjsLib.getDocument({ data, useSystemFonts: true }).promise;
  const numPages = Math.min(pdf.numPages, 3);
  let allText = '';

  for (let i = 1; i <= numPages; i++) {
    const page = await pdf.getPage(i);
    const content = await page.getTextContent();
    const pageText = (content.items || []).map(item => item.str || '').join(' ').trim();
    if (pageText.length > 40) {
      allText += '\n' + pageText;
    }
  }

  console.log('Embedded text length:', allText.trim().length);
}

const testPdf = process.argv[2];
if (testPdf && fs.existsSync(testPdf)) {
  extractPdf(testPdf);
}
