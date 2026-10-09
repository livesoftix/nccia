import * as pdfjsLib from 'pdfjs-dist';
import { createWorker, OEM, PSM } from 'tesseract.js';

import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;

const BASE = import.meta.env.BASE_URL.endsWith('/')
  ? import.meta.env.BASE_URL
  : `${import.meta.env.BASE_URL}/`;
const TESS_BASE = `${BASE}tesseract`;
const MAX_OCR_PAGES = 12;
const RENDER_SCALES = [2, 2.5, 3.5];

let workerInstance = null;
let workerInitPromise = null;

function asError(err, fallback) {
  if (err instanceof Error && err.message) return err;
  if (typeof err === 'string' && err) return new Error(err);
  return new Error(fallback);
}

async function initWorker(onStatus) {
  onStatus?.('Loading OCR engine…');

  const logger = (m) => {
    if (!onStatus) return;
    if (m.status === 'loading tesseract core') onStatus('Loading OCR core…');
    if (m.status === 'initializing tesseract') onStatus('Initializing OCR…');
    if (m.status === 'loading language traineddata') onStatus('Loading language data…');
    if (m.status === 'recognizing text') onStatus(`OCR ${Math.round((m.progress || 0) * 100)}%…`);
  };

  try {
    // 1. Try standard worker
    const worker = await createWorker('eng', 1, {
      logger,
      errorHandler: (e) => console.warn('Tesseract warning:', e),
    });
    return worker;
  } catch (err1) {
    console.warn('Default worker failed, trying local assets:', err1);
  }

  try {
    // 2. Try local assets
    const worker = await createWorker('eng', 1, {
      workerPath: `${TESS_BASE}/worker.min.js`,
      corePath: `${TESS_BASE}`,
      langPath: `${TESS_BASE}/lang`,
      gzip: true,
      logger,
      errorHandler: (e) => console.error('Tesseract local error:', e),
    });
    return worker;
  } catch (err2) {
    console.error('All OCR worker initialization attempts failed:', err2);
    throw err2;
  }
}

async function getOcrWorker(onStatus) {
  if (workerInstance) return workerInstance;
  if (!workerInitPromise) {
    workerInitPromise = initWorker(onStatus)
      .then((worker) => {
        workerInstance = worker;
        return worker;
      })
      .catch((err) => {
        workerInitPromise = null;
        workerInstance = null;
        throw err;
      });
  }
  return workerInitPromise;
}

function looksLikeVerificationReport(text) {
  if (!text) return false;
  const t = text.toLowerCase();
  return t.includes('cnic') || t.includes('complainant') || t.includes('verification')
    || t.includes('tracking') || t.includes('ccw-') || t.includes('defrauded') || /\d{13}/.test(text);
}

async function ocrFromCanvas(worker, canvas, onStatus) {
  onStatus?.('Recognizing text…');
  try {
    const result = await worker.recognize(canvas);
    return (result?.data?.text || '').trim();
  } catch (err) {
    console.warn('Canvas OCR recognition error:', err);
    return '';
  }
}

async function ocrPageWithPdfJs(page, worker, onStatus) {
  const viewport = page.getViewport({ scale: 2.0 });
  const canvas = document.createElement('canvas');
  canvas.width = Math.floor(viewport.width);
  canvas.height = Math.floor(viewport.height);
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  await page.render({
    canvasContext: ctx,
    viewport,
    intent: 'print',
  }).promise;

  return ocrFromCanvas(worker, canvas, onStatus);
}

async function extractPageText(page) {
  try {
    const content = await page.getTextContent();
    return (content?.items || []).map((item) => item.str || '').join(' ').trim();
  } catch (err) {
    console.warn('Text content extract failed:', err);
    return '';
  }
}

async function dataUrlToCanvas(dataUrl) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const canvas = document.createElement('canvas');
      canvas.width = img.naturalWidth || img.width;
      canvas.height = img.naturalHeight || img.height;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0);
      resolve(canvas);
    };
    img.onerror = () => reject(new Error('Server page image could not be loaded'));
    img.src = dataUrl;
  });
}

/**
 * @param {File} file
 * @param {(msg: string) => void} [onStatus]
 * @param {{ serverRenderPage?: (pageIndex: number) => Promise<string|null>, maxPages?: number, stopWhenUseful?: boolean }} [options]
 */
export async function extractTextFromPdf(file, onStatus, options = {}) {
  const { serverRenderPage, maxPages = MAX_OCR_PAGES, stopWhenUseful = true } = options;

  try {
    onStatus?.('Loading PDF…');
    const worker = await getOcrWorker(onStatus);
    const parts = [];
    let renderedAny = false;

    let pageCount = maxPages;
    let pdf = null;

    try {
      const data = new Uint8Array(await file.arrayBuffer());
      try {
        pdf = await pdfjsLib.getDocument({ data, useSystemFonts: true }).promise;
      } catch (workerErr) {
        console.warn('PDF.js worker failed, retrying in main thread:', workerErr);
        pdfjsLib.GlobalWorkerOptions.workerSrc = '';
        pdf = await pdfjsLib.getDocument({ data, useSystemFonts: true, disableAutoFetch: true, disableStream: true }).promise;
      }
      pageCount = Math.min(pdf.numPages, maxPages);
    } catch (pdfErr) {
      console.warn('PDF.js getDocument failed:', pdfErr);
      pdf = null;
    }

    for (let i = 0; i < pageCount; i += 1) {
      onStatus?.(`Reading page ${i + 1}/${pageCount}…`);
      let ocrText = '';

      if (pdf) {
        try {
          const page = await pdf.getPage(i + 1);
          const embedded = await extractPageText(page);
          if (embedded && embedded.length > 40) {
            parts.push(embedded);
            continue;
          }
          ocrText = await ocrPageWithPdfJs(page, worker, onStatus);
        } catch (pageErr) {
          console.error(`Page ${i + 1} OCR error:`, pageErr);
          ocrText = '';
        }
      }

      if (!ocrText && serverRenderPage) {
        onStatus?.(`Server render page ${i + 1}…`);
        try {
          const dataUrl = await serverRenderPage(i);
          if (dataUrl) {
            renderedAny = true;
            const canvas = await dataUrlToCanvas(dataUrl);
            ocrText = await ocrFromCanvas(worker, canvas, onStatus);
          }
        } catch (err) {
          console.warn('Server page render failed:', err);
        }
      }

      if (ocrText) {
        parts.push(ocrText);
      }

      const combinedSoFar = parts.join('\n\n');
      if (stopWhenUseful && looksLikeVerificationReport(combinedSoFar) && /\d{5}[-\s]?\d{7}[-\s]?\d/.test(combinedSoFar)) {
        break;
      }
    }

    const combined = parts.join('\n\n').trim();
    if (!combined) {
      throw new Error(
        renderedAny || pdf
          ? 'OCR ne text nahi parha. Re-run OCR try karein ya PDF quality check karein.'
          : 'PDF load nahi hui. File check karein.',
      );
    }
    return combined;
  } catch (err) {
    console.error('extractTextFromPdf fatal error:', err);
    throw asError(err, 'PDF OCR failed');
  }
}

/** Prompt user to pick a PDF file (for re-process). */
export function pickPdfFile(expectedName) {
  return new Promise((resolve, reject) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,application/pdf';
    input.onchange = () => {
      const picked = input.files?.[0];
      if (!picked) {
        reject(new Error('No file selected'));
        return;
      }
      if (expectedName && picked.name !== expectedName) {
        reject(new Error(`Please select "${expectedName}" (you picked "${picked.name}")`));
        return;
      }
      resolve(picked);
    };
    input.click();
  });
}
