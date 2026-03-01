@echo off
echo Starting Ollama with phi4-mini:3.8b model...
echo.

REM Check if Ollama is already running
curl -s http://127.0.0.1:11434/api/tags >nul 2>&1
if %errorlevel% == 0 (
    echo Ollama is already running!
    echo.
    echo Pulling/ensuring phi4-mini:3.8b model is available...
    ollama pull phi4-mini:3.8b
    echo.
    echo Ready! You can now use the Syndicati Agent.
    pause
    exit /b 0
)

REM Try to start Ollama server
echo Starting Ollama server...
start /B "" ollama serve

REM Wait a bit for server to start
timeout /t 3 /nobreak >nul

REM Check if it started
curl -s http://127.0.0.1:11434/api/tags >nul 2>&1
if %errorlevel% == 0 (
    echo Ollama server started successfully!
    echo.
    echo Pulling/ensuring phi4-mini:3.8b model is available...
    ollama pull phi4-mini:3.8b
    echo.
    echo Ready! You can now use the Syndicati Agent.
) else (
    echo.
    echo ERROR: Could not start Ollama server.
    echo Please make sure Ollama is installed and in your PATH.
    echo.
    echo You can download Ollama from: https://ollama.ai
    echo.
    echo Alternatively, start Ollama manually by running:
    echo   ollama serve
    echo.
    echo Then in another terminal, pull the model:
    echo   ollama pull phi4-mini:3.8b
)

pause
