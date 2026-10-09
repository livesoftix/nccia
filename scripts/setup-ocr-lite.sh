#!/bin/bash
# Small OCR setup for jailshell (no sudo, low disk). Uses Node tesseract.js (~40MB)
# instead of conda tesseract (~350MB).
set -euo pipefail

echo "=== Disk before ==="
du -sh "$HOME" 2>/dev/null || true
quota -s 2>/dev/null || df -h "$HOME" || true

if [ -d "$HOME/miniconda3" ]; then
  echo "Removing failed Miniconda to free quota…"
  rm -rf "$HOME/miniconda3"
  echo "Miniconda removed."
fi

echo "=== Disk after cleanup ==="
du -sh "$HOME" 2>/dev/null || true

NODE=""
for p in \
  /opt/cpanel/ea-nodejs20/bin/node \
  /opt/cpanel/ea-nodejs18/bin/node \
  /opt/cpanel/ea-nodejs16/bin/node \
  /usr/bin/node \
  "$(command -v node 2>/dev/null || true)"
do
  if [ -n "$p" ] && [ -x "$p" ]; then
    NODE="$p"
    break
  fi
done

if [ -z "$NODE" ]; then
  echo "ERROR: Node.js nahi mila."
  echo "cPanel → Software → Setup Node.js.js enable karein, ya host se Node.js mangwao."
  echo "Phir yeh script dubara chalao."
  exit 1
fi

echo "Using $($NODE -v) at $NODE"

NPM="$(dirname "$NODE")/npm"
if [ ! -x "$NPM" ]; then
  NPM="$(command -v npm 2>/dev/null || true)"
fi
if [ -z "$NPM" ] || [ ! -x "$NPM" ]; then
  echo "ERROR: npm nahi mila (Node ke saath hona chahiye)."
  exit 1
fi

mkdir -p "$HOME/nccia-ocr"
cd "$HOME/nccia-ocr"
if [ ! -f package.json ]; then
  echo '{"name":"nccia-ocr","private":true}' > package.json
fi

echo "Installing tesseract.js@4.1.4 (small, no conda)…"
"$NPM" install tesseract.js@4.1.4 --omit=dev --no-audit --no-fund

echo
echo "OK."
echo "Add to .env:"
echo "PDF_OCR_NODE=${NODE}"
echo "PDF_OCR_NODE_MODULES=${HOME}/nccia-ocr/node_modules"
echo
echo "Then:"
echo "  php artisan config:clear"
echo "  php artisan complaints:pdf-import-doctor /home/realerp/pdf/261-26.PDF"
