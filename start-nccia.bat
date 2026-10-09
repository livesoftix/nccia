@echo off
REM ── NCCIA CMS — local run (auto-picks a free port) ─────────────────
cd /d D:\NCCIA-main

echo Clearing caches (needed after the security fixes)...
php artisan config:clear
php artisan route:clear
php artisan cache:clear

echo.
echo Looking for a usable port and starting the server...
echo When you see "Server running on http://127.0.0.1:PORT",
echo open THAT address in your browser. Leave this window open.
echo.

for %%p in (8080 8888 9000 9999 13131 7777 5173) do (
  echo --- trying http://127.0.0.1:%%p ---
  php artisan serve --host=127.0.0.1 --port=%%p
)

echo.
echo ***** Could not bind ANY port. ****
echo If every port failed, run this once in an ADMIN PowerShell to see
echo the reserved ranges:  netsh int ipv4 show excludedportrange tcp
echo This window stays open. Press any key to close.
pause >nul
