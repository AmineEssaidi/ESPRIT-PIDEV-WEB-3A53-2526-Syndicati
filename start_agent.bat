@echo off
echo Starting Syndicati Agent Server...
echo.

REM Check if Node.js is installed
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Node.js is not installed!
    echo Please install Node.js from https://nodejs.org/
    pause
    exit /b 1
)

echo Node.js version:
node --version

echo.
echo Installing dependencies if needed...
cd /d "%~dp0\playwright"
call npm install express playwright

echo.
echo Starting Agent Server on port 3002...
node agent-server.js

echo.
echo Server stopped.
pause
