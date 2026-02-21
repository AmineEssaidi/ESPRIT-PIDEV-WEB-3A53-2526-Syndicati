@echo off
echo Starting Ollama Server...
echo.

REM Common Ollama paths on Windows
set OLLAMA_PATH=

if exist "C:\Program Files\Ollama\ollama.exe" (
    set OLLAMA_PATH="C:\Program Files\Ollama\ollama.exe"
) else if exist "C:\Program Files (x86)\Ollama\ollama.exe" (
    set OLLAMA_PATH="C:\Program Files (x86)\Ollama\ollama.exe"
) else if exist "%LOCALAPPDATA%\Programs\Ollama\ollama.exe" (
    set OLLAMA_PATH="%LOCALAPPDATA%\Programs\Ollama\ollama.exe"
) else (
    REM Try PATH
    where ollama >nul 2>&1
    if %errorlevel%==0 (
        set OLLAMA_PATH=ollama
    ) else (
        echo ERROR: Could not find Ollama.exe
        echo Please install Ollama from https://ollama.com/download
        pause
        exit /b 1
    )
)

echo Found Ollama at: %OLLAMA_PATH%
echo.
echo Starting Ollama serve... (Keep this window open!)
echo.
echo If you see errors below, try:
echo   1. Run this as Administrator
echo   2. Check if port 11434 is already in use
echo   3. Check Windows Defender isn't blocking Ollama
echo.

%OLLAMA_PATH% serve

echo.
echo Ollama server stopped.
pause
