@echo off
REM Ollama Auto-Manager Launcher for Windows
REM This script starts the Python Ollama manager automatically

echo Starting Ollama Auto-Manager...

REM Check if Python is installed
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Python is not installed or not in PATH
    echo Please install Python 3.7+ from https://python.org
    pause
    exit /b 1
)

REM Check if we're in the right directory
if not exist "tools\ollama_manager.py" (
    echo Error: ollama_manager.py not found in tools directory
    echo Please run this script from the Horizon project root
    pause
    exit /b 1
)

REM Create logs directory if it doesn't exist
if not exist "logs" mkdir logs

REM Install required Python packages if not already installed
echo Checking Python dependencies...
python -c "import requests" 2>nul
if %errorlevel% neq 0 (
    echo Installing requests package...
    pip install requests
)

REM Start the Ollama manager
echo Starting Ollama manager in background...
python tools\ollama_manager.py

echo Ollama manager stopped
pause
