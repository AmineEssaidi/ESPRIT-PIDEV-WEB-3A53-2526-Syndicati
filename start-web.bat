@echo off
setlocal EnableExtensions

cd /d "%~dp0"

set "PHP_EXE="
set "PS_EXE=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
set "REQUIRED_PHP_EXTENSIONS=openssl curl pdo_mysql intl mbstring fileinfo gd sodium"
set "EXTENSION_FIXER=%~dp0tools\enable-php-extensions.ps1"
set "HOST=127.0.0.1"
set "PORT=8000"

if defined PHP_EXE_OVERRIDE set "PHP_EXE=%PHP_EXE_OVERRIDE%"

if "%PHP_EXE%"=="" (
    if exist "%~dp0tools\php\php.exe" set "PHP_EXE=%~dp0tools\php\php.exe"
)

if "%PHP_EXE%"=="" (
    for /f "delims=" %%p in ('where php 2^>nul') do (
        if "%PHP_EXE%"=="" set "PHP_EXE=%%p"
    )
)

if "%PHP_EXE%"=="" (
    echo [ERROR] PHP was not found.
    echo         Run install-web-deps.bat first.
    pause
    exit /b 1
)

echo.
echo ================================================================
echo   Syndicati Web - verified local server
echo ================================================================
echo.
echo [INFO] Using PHP:
echo        %PHP_EXE%
"%PHP_EXE%" -v
echo.

if exist "%EXTENSION_FIXER%" if exist "%PS_EXE%" (
    echo [INFO] Verifying required PHP extensions...
    "%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%EXTENSION_FIXER%" -PhpExe "%PHP_EXE%" -Extensions "%REQUIRED_PHP_EXTENSIONS%"
    if errorlevel 2 (
        echo [ERROR] Required PHP extensions are still missing.
        echo         Run install-web-deps.bat, then try again.
        pause
        exit /b 1
    )
    if errorlevel 1 (
        echo [ERROR] Could not verify PHP extensions.
        pause
        exit /b 1
    )
)

if not exist vendor\autoload.php (
    echo [WARN] vendor\autoload.php is missing. Running dependency installer...
    call install-web-deps.bat --no-pause
    if errorlevel 1 (
        echo [ERROR] Dependency installer failed.
        pause
        exit /b 1
    )
)

echo [INFO] Clearing prod cache...
"%PHP_EXE%" bin\console cache:clear --env=prod --no-warmup
if errorlevel 1 (
    echo [ERROR] Cache clear failed. Run diagnose-web.bat for details.
    pause
    exit /b 1
)

echo.
echo [INFO] Checking database connection...
"%PHP_EXE%" bin\console doctrine:query:sql "SELECT 1" --env=prod >nul
if errorlevel 1 (
    echo [ERROR] Database check failed. Run diagnose-web.bat for details.
    pause
    exit /b 1
)

echo.
echo [OK] Starting Syndicati with the verified PHP runtime.
echo [OK] Open this URL:
echo      http://%HOST%:%PORT%
echo.
echo [NOTE] If another server is already using port %PORT%, close it first.
echo.
"%PHP_EXE%" -S %HOST%:%PORT% -t public
echo.
echo [INFO] PHP local server exited with code %ERRORLEVEL%.
pause
