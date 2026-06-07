@echo off
setlocal EnableExtensions

cd /d "%~dp0"

set "PHP_EXE=C:\wamp64\bin\php\php8.5.1\php.exe"
set "COMPOSER_PHAR=C:\ProgramData\ComposerSetup\bin\composer.phar"

echo.
echo ================================================================
echo   Syndicati Web - Composer dependency installer
echo ================================================================
echo.

if not exist "%PHP_EXE%" (
    echo [ERROR] Expected WAMP PHP was not found:
    echo         %PHP_EXE%
    echo.
    echo Update PHP_EXE in this file if your WAMP PHP version changed.
    pause
    exit /b 1
)

if not exist "%COMPOSER_PHAR%" (
    echo [ERROR] Composer PHAR was not found:
    echo         %COMPOSER_PHAR%
    echo.
    echo Reinstall Composer or update COMPOSER_PHAR in this file.
    pause
    exit /b 1
)

echo [INFO] PHP:
"%PHP_EXE%" -v
echo.

"%PHP_EXE%" -m | findstr /i /x "openssl" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] OpenSSL is not enabled for:
    echo         %PHP_EXE%
    echo.
    echo Enable this line in C:\wamp64\bin\php\php8.5.1\php.ini:
    echo         extension=openssl
    pause
    exit /b 1
)

echo [OK] PHP OpenSSL extension is enabled.
echo [INFO] Running Composer install with the verified PHP executable...
echo.

"%PHP_EXE%" "%COMPOSER_PHAR%" install --no-interaction --no-progress
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
