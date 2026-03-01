@echo off
chcp 65001 >nul
echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║       LangGraph Server Manager for Horizon                 ║
echo ║       (Auto-starts with PHP backend)                       ║
echo ╚════════════════════════════════════════════════════════════╝
echo.

set PROJECT_ROOT=%~dp0..

if "%1"=="" goto :menu
if "%1"=="start" goto :start
if "%1"=="stop" goto :stop
if "%1"=="restart" goto :restart
if "%1"=="status" goto :status
if "%1"=="install" goto :install

:menu
echo Usage: langgraph.bat [command]
echo.
echo Commands:
echo   start    - Start the LangGraph server
echo   stop     - Stop the LangGraph server  
echo   restart  - Restart the LangGraph server
echo   status   - Check server status
echo   install  - Install Node.js dependencies
echo.
echo Or run without arguments for interactive menu.
echo.

:prompt
set /p choice="Enter command (start/stop/restart/status/install/quit): "
if "%choice%"=="start" goto :start
if "%choice%"=="stop" goto :stop
if "%choice%"=="restart" goto :restart
if "%choice%"=="status" goto :status
if "%choice%"=="install" goto :install
if "%choice%"=="quit" goto :end
goto :prompt

:start
echo.
echo 🚀 Starting LangGraph server...
cd /d "%PROJECT_ROOT%"
node assets\langgraph-server.js
goto :end

:stop
echo.
echo 🛑 Stopping LangGraph server...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3001') do (
    taskkill /PID %%a /F 2>nul
    echo ✅ Server stopped (PID: %%a)
    goto :end
)
echo ℹ️  No server running on port 3001
goto :end

:restart
echo.
echo 🔄 Restarting LangGraph server...
call :stop
ping -n 3 127.0.0.1 >nul
call :start
goto :end

:status
echo.
echo 🔍 Checking LangGraph server status...
curl -s http://localhost:3001/health >nul 2>&1
if %errorlevel%==0 (
    echo ✅ Server is running on http://localhost:3001
    curl -s http://localhost:3001/status
) else (
    echo ❌ Server is not running
)
goto :end

:install
echo.
echo 📦 Installing Node.js dependencies...
cd /d "%PROJECT_ROOT%"
npm install
echo ✅ Installation complete!
goto :end

:end
echo.
