@echo off
chcp 65001 >nul
echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║       Ollama Diagnostic Tool for Horizon                 ║
echo ╚════════════════════════════════════════════════════════════╝
echo.

set LOGFILE=%TEMP%\ollama_diag.log
echo Diagnostic started at %date% %time% > %LOGFILE%

echo [1/6] Checking if Ollama is installed...
echo.

set OLLAMA_PATH=
set FOUND=0

for %%p in (
    "C:\Program Files\Ollama\ollama.exe"
    "C:\Program Files (x86)\Ollama\ollama.exe"
    "%LOCALAPPDATA%\Programs\Ollama\ollama.exe"
) do (
    if exist %%p (
        echo   ✓ Found: %%p
        set OLLAMA_PATH=%%p
        set FOUND=1
        echo Found Ollama at: %%p >> %LOGFILE%
    )
)

if %FOUND%==0 (
    echo   ✗ Ollama executable not found in common locations
    echo.
    echo   Checking PATH...
    where ollama >nul 2>&1
    if %errorlevel%==0 (
        echo   ✓ Found ollama in PATH
        for /f "tokens=*" %%a in ('where ollama') do (
            set OLLAMA_PATH=%%a
            echo Found in PATH: %%a >> %LOGFILE%
        )
        set FOUND=1
    ) else (
        echo   ✗ Ollama not found in PATH either
        echo.
        echo ╔════════════════════════════════════════════════════════════╗
        echo ║  ⚠️  OLLAMA NOT FOUND                                      ║
        echo ║                                                            ║
        echo ║  Please install Ollama from:                               ║
        echo ║  https://ollama.com/download                               ║
        echo ║                                                            ║
        echo ║  Or if already installed, add it to your PATH:             ║
        echo ║  set PATH=%%PATH%%;C:\Program Files\Ollama              ║
        echo ╚════════════════════════════════════════════════════════════╝
        echo.
        pause
        exit /b 1
    )
)

echo.
echo [2/6] Checking Ollama version...
echo.
"%OLLAMA_PATH%" --version 2>&1
echo. 

echo [3/6] Checking if Ollama server is running...
echo.
curl -s http://localhost:11434/api/tags >nul 2>&1
if %errorlevel%==0 (
    echo   ✓ Ollama server is already running
    echo.
    echo [4/6] Testing API...
    curl -s http://localhost:11434/api/tags | findstr "models" >nul
    if %errorlevel%==0 (
        echo   ✓ API is responding
    ) else (
        echo   ⚠ API responded but returned unexpected data
    )
) else (
    echo   ✗ Ollama server is NOT running
    echo.
    echo [4/6] Trying to start Ollama server...
    echo.
    
    echo   Starting ollama serve in background...
    start /B "Ollama Server" "%OLLAMA_PATH%" serve > %TEMP%\ollama_output.log 2>&1
    
    echo   Waiting for server to start (10 seconds)...
    timeout /t 10 /nobreak >nul
    
    curl -s http://localhost:11434/api/tags >nul 2>&1
    if %errorlevel%==0 (
        echo   ✓ Ollama server started successfully!
    ) else (
        echo   ✗ Failed to start Ollama server
        echo.
        echo   Output from startup attempt:
        type %TEMP%\ollama_output.log 2>nul
        echo.
        echo ╔════════════════════════════════════════════════════════════╗
        echo ║  ⚠️  STARTUP FAILED                                        ║
        echo ║                                                            ║
        echo ║  Common issues:                                            ║
        echo ║  1. Port 11434 is already in use by another application    ║
        echo ║  2. Ollama requires admin privileges to run                ║
        echo ║  3. Windows Defender or antivirus is blocking Ollama       ║
        echo ║  4. Missing Visual C++ Redistributables                    ║
        echo ║                                                            ║
        echo ║  Try running this script as Administrator.               ║
        echo ║  Or manually run: ollama serve                             ║
        echo ╚════════════════════════════════════════════════════════════╝
    )
)

echo.
echo [5/6] Checking Python manager...
echo.

if exist "%~dp0..\tools\ollama_manager.py" (
    echo   ✓ ollama_manager.py found
    
    python --version >nul 2>&1
    if %errorlevel%==0 (
        echo   ✓ Python is available
        
        echo   Testing Python manager start command...
        cd /d "%~dp0..\tools"
        python ollama_manager.py start > %TEMP%\manager_test.log 2>&1
        timeout /t 5 /nobreak >nul
        
        curl -s http://localhost:11434/api/tags >nul 2>&1
        if %errorlevel%==0 (
            echo   ✓ Python manager started Ollama successfully
        ) else (
            echo   ⚠ Python manager did not start Ollama
            echo   Log output:
            type %TEMP%\manager_test.log 2>nul | head -20
        )
    ) else (
        echo   ✗ Python not found in PATH
        echo   Please install Python from https://python.org
    )
) else (
    echo   ✗ ollama_manager.py not found
)

echo.
echo [6/6] Checking logs...
echo.

if exist "%~dp0..\logs\ollama_manager.log" (
    echo   Recent log entries:
    echo   ------------------
    type "%~dp0..\logs\ollama_manager.log" 2>nul | findstr /v "^$" | tail -10
) else (
    echo   No log file found
)

echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║                     DIAGNOSTIC COMPLETE                    ║
echo ╚════════════════════════════════════════════════════════════╝
echo.
echo Log saved to: %LOGFILE%
echo.
pause
