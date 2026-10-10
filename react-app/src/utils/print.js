import api from '../api';

export async function printApiDocument(path) {
  const win = preparePrintWindow();
  if (!win) return;
  try {
    const response = await api.get(path, { headers: { Accept: 'text/html, application/json' } });
    if (typeof response.data !== 'string' || !response.headers['content-type']?.includes('text/html')
        || /<div[^>]+id=["']root["']/.test(response.data)) {
      throw new Error('The server did not return a printable document. Please sign in again and retry.');
    }
    writePrintWindow(win, response.data);
  } catch (error) {
    closePrintWindow(win);
    alert(error.response?.data?.message || error.message || 'The document could not be loaded. Please retry.');
  }
}

export function preparePrintWindow() {
  const win = window.open('', '_blank', 'width=850,height=900,scrollbars=yes,resizable=yes');
  if (!win) {
    alert('Please allow pop-ups to print.');
    return null;
  }
  try {
    win.document.open();
    win.document.write(
      '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Preparing print…</title></head>' +
      '<body style="font-family:Arial,Helvetica,sans-serif;padding:28px;color:#334155;">Preparing document…</body></html>'
    );
    win.document.close();
  } catch (e) {
    console.error(e);
  }
  return win;
}

export function writePrintWindow(win, html) {
  if (!win) return;
  try {
    win.document.open();
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => {
      try {
        win.print();
      } catch (e) {
        console.error(e);
      }
    }, 400);
  } catch (e) {
    console.error(e);
    alert('Could not open print preview.');
  }
}

export function closePrintWindow(win) {
  if (!win) return;
  try {
    win.close();
  } catch (e) {
    /* ignore */
  }
}

export function openPrintWindow(html) {
  const win = preparePrintWindow();
  if (!win) return;
  writePrintWindow(win, html);
}
