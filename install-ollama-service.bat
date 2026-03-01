@echo off
REM Install Ollama Manager as Windows Service
REM Run this as Administrator!

echo Installing Ollama Manager as Windows Service...
echo.

REM Check if running as Administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: This script must be run as Administrator
    echo Right-click the script and select "Run as administrator"
    pause
    exit /b 1
)

REM Check if Python is installed
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Python is not installed or not in PATH
    pause
    exit /b 1
)

REM Install required packages
echo Installing required Python packages...
pip install pywin32 requests

REM Install the service
echo Installing Ollama Manager service...
python tools\ollama_service.py install

if %errorlevel% equ 0 (
    echo.
    echo Service installed successfully!
    echo.
    echo To start the service:
    echo   net start OllamaManager
    echo.
    echo To stop the service:
    echo   net stop OllamaManager
    echo.
    echo To uninstall the service:
    echo   python tools\ollama_service.py remove
    echo.
    echo Starting the service now...
    net start OllamaManager
    
    if %errorlevel% equ 0 (
        echo Service started successfully!
        echo Ollama will now automatically start with Windows.
    ) else (
        echo Failed to start service. Check the Windows Event Viewer for details.
    )
) else (
    echo Failed to install service.
)

pause
