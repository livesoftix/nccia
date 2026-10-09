import QRCode from 'qrcode';
import api from '../api';

/**
 * Generate SVG QR Code string
 * @param {string} text
 * @param {object} options
 * @returns {Promise<string>}
 */
export async function generateQrSvg(text, options = {}) {
  if (!text) return '';
  const size = options.size || 66;
  const margin = options.margin ?? 1;
  try {
    const svg = await QRCode.toString(String(text).trim(), {
      type: 'svg',
      width: size,
      margin,
      color: {
        dark: '#000000',
        light: '#ffffff',
      },
    });
    return svg.replace('<svg ', '<svg style="display:inline-block;vertical-align:middle;max-width:' + size + 'px;height:auto;" ');
  } catch (e) {
    console.error('QR generation error:', e);
    return '';
  }
}

/**
 * Generate Base64 Data URL for QR Code
 * @param {string} text
 * @param {object} options
 * @returns {Promise<string>}
 */
export async function generateQrDataUrl(text, options = {}) {
  if (!text) return '';
  const size = options.size || 100;
  const margin = options.margin ?? 1;
  try {
    return await QRCode.toDataURL(String(text).trim(), {
      width: size,
      margin,
      color: {
        dark: '#000000',
        light: '#ffffff',
      },
    });
  } catch (e) {
    console.error('QR DataURL generation error:', e);
    return '';
  }
}

/**
 * Official document QR: signed verify URL + identifiers so a scan shows authentic NCCIA details.
 */
export function officialDocumentPayload({ type, number, verifyUrl }) {
  if (verifyUrl) return String(verifyUrl).trim();
  const lines = ['NCCIA Official Document'];
  if (type) lines.push('Type: ' + type);
  if (number) lines.push('No: ' + number);
  return lines.filter(Boolean).join('\n');
}

/**
 * Fetch signed payload from API, then render QR. Falls back to local official payload.
 */
export async function generateOfficialDocumentQr({ type, id, fallback, size = 64 }) {
  const caption = fallback?.number || fallback?.type || '';
  const localPayload = fallback?.verifyUrl
    ? String(fallback.verifyUrl).trim()
    : officialDocumentPayload({
        type: fallback?.type,
        number: fallback?.number,
      });

  try {
    const r = await Promise.race([
      api.get(`/documents/${type}/${id}/qr`),
      new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 2500)),
    ]);
    const payload = r.data?.url || r.data?.payload || localPayload;
    const svg = await generateQrSvg(payload, { size, margin: 1 });
    return { svg, caption: r.data?.caption || caption, url: r.data?.url || '' };
  } catch (e) {
    const svg = await generateQrSvg(localPayload || caption, { size, margin: 1 });
    return { svg, caption, url: fallback?.verifyUrl || '' };
  }
}

