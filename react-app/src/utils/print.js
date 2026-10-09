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
