@echo off
setlocal EnableExtensions

cd /d "%~dp0"

set "PHP_EXE="
set "COMPOSER_PHAR="

echo.
echo ================================================================
echo   Syndicati Web - Composer dependency installer
echo ================================================================
echo.

if not "%~1"=="" (
    set "PHP_EXE=%~1"
)

if "%PHP_EXE%"=="" (
    if defined PHP_EXE_OVERRIDE set "PHP_EXE=%PHP_EXE_OVERRIDE%"
)

if "%PHP_EXE%"=="" (
    for /f "delims=" %%p in ('where php 2^>nul') do (
        if "%PHP_EXE%"=="" set "PHP_EXE=%%p"
    )
)

if "%PHP_EXE%"=="" (
    echo [ERROR] PHP was not found on PATH.
    echo.
    echo Install PHP, add it to PATH, then reopen your terminal.
    echo You can also run this file with an explicit PHP path:
    echo     install-web-deps.bat C:\path\to\php.exe
    pause
    exit /b 1
)

if not exist "%PHP_EXE%" (
    echo [ERROR] PHP executable was not found:
    echo         %PHP_EXE%
    pause
    exit /b 1
)

if exist "%ProgramData%\ComposerSetup\bin\composer.phar" (
    set "COMPOSER_PHAR=%ProgramData%\ComposerSetup\bin\composer.phar"
)

echo [INFO] PHP:
"%PHP_EXE%" -v
echo.

"%PHP_EXE%" -m | findstr /i /x "openssl" >nul 2>&1
if errorlevel 1 (
    echo [WARN] OpenSSL is not enabled for:
    echo         %PHP_EXE%
    echo.
    echo [INFO] Attempting to enable OpenSSL in the active php.ini...
    call :enable_openssl
    if errorlevel 1 (
        echo.
        echo [ERROR] Could not enable OpenSSL automatically.
        echo.
        echo Active PHP configuration:
        "%PHP_EXE%" --ini
        echo.
        echo Enable the OpenSSL extension in the loaded php.ini, usually by adding or uncommenting:
        echo     extension=openssl
        echo.
        echo If the php.ini is inside Program Files, run this script as Administrator.
        pause
        exit /b 1
    )
    echo.
    echo [INFO] Re-checking OpenSSL...
    "%PHP_EXE%" -m | findstr /i /x "openssl" >nul 2>&1
    if errorlevel 1 (
        echo [ERROR] OpenSSL was enabled in php.ini but PHP still did not load it.
        echo.
        echo This usually means the PHP OpenSSL DLL/dependency is missing or PHP needs a fresh terminal.
        echo Try reopening your terminal, or install a PHP build that includes OpenSSL.
        pause
        exit /b 1
    )
)

echo [OK] PHP OpenSSL extension is enabled.
echo [INFO] Running Composer install with the verified PHP executable...
echo.

if not "%COMPOSER_PHAR%"=="" (
    "%PHP_EXE%" "%COMPOSER_PHAR%" install --no-interaction --no-progress
) else (
    where composer >nul 2>&1
    if errorlevel 1 (
        echo [ERROR] Composer was not found.
        echo.
        echo Install Composer, add it to PATH, then reopen your terminal.
        pause
        exit /b 1
    )
    composer install --no-interaction --no-progress
)

if errorlevel 1 (
    echo.
    echo [ERROR] Composer install failed.
    pause
    exit /b 1
)

echo.
echo [OK] Composer dependencies installed successfully.
pause
exit /b 0

:enable_openssl
set "OPENSSL_FIXER=%~dp0tools\enable-php-openssl.ps1"
if not exist "%OPENSSL_FIXER%" (
    echo [ERROR] Missing helper script:
    echo         %OPENSSL_FIXER%
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%OPENSSL_FIXER%" -PhpExe "%PHP_EXE%"
if errorlevel 1 exit /b 1

exit /b 0
