#!/bin/bash
# Read-only check of what the OCR pipeline can use on this server (cPanel / CloudLinux).
# Prints versions, paths and yes/no answers only — never .env values or secrets.
#   cd ~/path/to/nccia && bash scripts/ocr-server-check.sh
cd "$(dirname "$0")/.." || exit 1

echo "== Host"
uname -srm
echo "user: $(whoami)   shell: ${SHELL:-?}"

echo "== Disk quota / free space"
(quota -s 2>/dev/null | tail -n +3) || true
df -h "$HOME" 2>/dev/null | tail -1
du -sh "$HOME" 2>/dev/null

echo "== PHP (CLI)"
PHP_BIN="$(command -v php)"
echo "php: ${PHP_BIN:-missing} $($PHP_BIN -r 'echo PHP_VERSION;' 2>/dev/null)"
$PHP_BIN -r '
$d = array_map("trim", explode(",", (string) ini_get("disable_functions")));
foreach (["proc_open", "proc_get_status", "exec", "shell_exec", "popen"] as $f) {
    printf("  %-16s %s\n", $f, (function_exists($f) && !in_array($f, $d, true)) ? "allowed" : "DISABLED");
}
printf("  max_execution_time %s, memory_limit %s\n", ini_get("max_execution_time"), ini_get("memory_limit"));
foreach (["fileinfo", "gd", "imagick", "pdo_mysql"] as $e) printf("  ext %-10s %s\n", $e, extension_loaded($e) ? "yes" : "no");
' 2>/dev/null

echo "== Python interpreters"
for p in python3 python3.12 python3.11 python3.10 python3.9 \
         /opt/alt/python312/bin/python3 /opt/alt/python311/bin/python3 /opt/alt/python310/bin/python3 \
         /opt/alt/python39/bin/python3 "$HOME/miniconda3/bin/python" "$HOME"/virtualenv/*/*/bin/python; do
  bin="$(command -v "$p" 2>/dev/null || { [ -x "$p" ] && echo "$p"; })"
  [ -n "$bin" ] || continue
  printf "  %-55s %s" "$bin" "$("$bin" --version 2>&1)"
  "$bin" -c "import venv, ensurepip" >/dev/null 2>&1 && printf "  (venv ok)" || printf "  (no venv/ensurepip)"
  echo
done

echo "== OCR binaries"
for b in tesseract "$HOME/miniconda3/bin/tesseract" "$HOME/.local/bin/tesseract" pdftoppm mutool gs; do
  bin="$(command -v "$b" 2>/dev/null || { [ -x "$b" ] && echo "$b"; })"
  if [ -n "$bin" ]; then echo "  $b: $bin ($("$bin" --version 2>&1 | head -1))"; else echo "  $b: missing"; fi
done
command -v tesseract >/dev/null 2>&1 && echo "  tesseract languages: $(tesseract --list-langs 2>/dev/null | tail -n +2 | tr '\n' ' ')"

echo "== Node (tesseract.js fallback)"
for n in node /opt/cpanel/ea-nodejs22/bin/node /opt/cpanel/ea-nodejs20/bin/node /opt/cpanel/ea-nodejs18/bin/node; do
  bin="$(command -v "$n" 2>/dev/null || { [ -x "$n" ] && echo "$n"; })"
  [ -n "$bin" ] && echo "  $bin $("$bin" --version 2>&1)"
done
[ -d "$HOME/nccia-ocr/node_modules/tesseract.js" ] && echo "  tesseract.js installed in ~/nccia-ocr" || echo "  tesseract.js: not installed in ~/nccia-ocr"

echo "== Laravel queue"
grep -E "^QUEUE_CONNECTION=" .env 2>/dev/null | sed 's/=.*/=<set>/' || echo "  QUEUE_CONNECTION not set (defaults to database)"
$PHP_BIN artisan queue:monitor database:ocr,database:default 2>/dev/null | tail -3 || true
echo "  cron available: $(command -v crontab >/dev/null 2>&1 && echo yes || echo 'no (use cPanel > Cron Jobs)')"

echo "== Outbound network (needed only for one-time installs)"
curl -sS -o /dev/null -w "  pypi.org: %{http_code}\n" --max-time 10 https://pypi.org/simple/pymupdf/ 2>/dev/null || echo "  pypi.org: unreachable"

echo "== Done. Copy everything above into the chat."
