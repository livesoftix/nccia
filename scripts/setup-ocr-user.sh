#!/bin/bash
# Install Python 3.10 + pymupdf + tesseract in $HOME (no sudo / jailshell OK).
set -euo pipefail

PREFIX="${HOME}/miniconda3"
INSTALLER="/tmp/nccia-miniconda.sh"

echo "Installing Miniconda into ${PREFIX} (no root required)…"

if [ ! -x "${PREFIX}/bin/python" ]; then
  curl -fsSL -o "${INSTALLER}" \
    "https://repo.anaconda.com/miniconda/Miniconda3-py310_24.4.0-0-Linux-x86_64.sh"
  bash "${INSTALLER}" -b -p "${PREFIX}"
  rm -f "${INSTALLER}"
fi

"${PREFIX}/bin/conda" install -y -c conda-forge pymupdf pytesseract pillow tesseract

echo
echo "OK. Add these two lines to .env:"
echo "PDF_EXTRACT_PYTHON=${PREFIX}/bin/python"
echo "TESSERACT_CMD=${PREFIX}/bin/tesseract"
echo
echo "Then:"
echo "  php artisan config:clear"
echo "  php artisan complaints:pdf-import-doctor /home/realerp/pdf/261-26.PDF"
