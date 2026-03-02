@echo off
setlocal enabledelayedexpansion

echo [RECOVERY] Syndicati Agent Stabilization Tool
echo [RECOVERY] ----------------------------------
echo [RECOVERY] Closing Node.js Agent Servers...
taskkill /F /IM node.exe /T >nul 2>&1
if !errorlevel! equ 0 (echo [OK] Node.js processes terminated.) else (echo [INFO] No Node.js processes found.)

echo [RECOVERY] Closing PHP-CGI Processes...
taskkill /F /IM php-cgi.exe /T >nul 2>&1
if !errorlevel! equ 0 (echo [OK] PHP-CGI processes terminated.) else (echo [INFO] No PHP-CGI processes found.)

echo [RECOVERY] Closing Stale Chromium/Playwright Browsers...
taskkill /F /IM chrome.exe /T >nul 2>&1
taskkill /F /IM msedge.exe /T >nul 2>&1
echo [OK] Browser cleanup attempted.

echo [RECOVERY] ----------------------------------
echo [RECOVERY] All systems cleared. You can now restart your WAMP/Agent services.
pause
