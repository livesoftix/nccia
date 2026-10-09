// Code 128-B SVG Barcode Generator (Zero-dependency, crisp vector output)
const CODE128_PATTERNS = [
  '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
  '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
  '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
  '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
  '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
  '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
  '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
  '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
  '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
  '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
  '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
];

const START_B = 104;
const STOP = 106;

export function generateBarcodeSvg(text, options = {}) {
  if (!text) return '';
  const safeText = String(text).trim();
  const height = options.height || 42;
  const showText = options.showText !== false;
  const barWidth = options.barWidth || 1.35;
  const fontSize = options.fontSize || 10;

  const codes = [START_B];
  let checksum = START_B;

  for (let i = 0; i < safeText.length; i++) {
    const charCode = safeText.charCodeAt(i);
    const code128Val = charCode - 32; // ASCII 32 is Code 128 value 0
    if (code128Val >= 0 && code128Val <= 95) {
      codes.push(code128Val);
      checksum += code128Val * (i + 1);
    }
  }

  codes.push(checksum % 103);
  codes.push(STOP);

  let currentX = 6;
  let rects = '';

  codes.forEach((c) => {
    const pattern = CODE128_PATTERNS[c];
    if (!pattern) return;
    let isBar = true;
    for (let j = 0; j < pattern.length; j++) {
      const width = parseInt(pattern[j], 10) * barWidth;
      if (isBar) {
        rects += '<rect x="' + currentX.toFixed(1) + '" y="2" width="' + width.toFixed(1) + '" height="' + height + '" fill="#000" />';
      }
      currentX += width;
      isBar = !isBar;
    }
  });

  const totalWidth = currentX + 6;
  const totalHeight = showText ? height + fontSize + 6 : height + 4;
  const textSvg = showText 
    ? '<text x="' + (totalWidth / 2).toFixed(1) + '" y="' + (height + fontSize + 2).toFixed(1) + '" font-family="Arial, monospace" font-size="' + fontSize + '" font-weight="bold" text-anchor="middle" fill="#000" letter-spacing="1">' + safeText + '</text>' 
    : '';

  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + totalWidth.toFixed(0) + ' ' + totalHeight.toFixed(0) + '" width="' + totalWidth.toFixed(0) + '" height="' + totalHeight.toFixed(0) + '" style="display:inline-block;vertical-align:middle;max-width:100%;height:auto;">' +
    '<rect width="100%" height="100%" fill="#fff" />' +
    rects +
    textSvg +
  '</svg>';
}
