@echo off
setlocal EnableExtensions EnableDelayedExpansion

cd /d "%~dp0"

set "PHP_EXE="
set "PHP_DIR="
set "COMPOSER_CMD="
set "COMPOSER_DIR="
set "COMPOSER_PHAR="
set "RUN_COMPOSER=1"

if /I "%~1"=="--skip-composer" set "RUN_COMPOSER=0"
if /I "%~1"=="--no-composer" set "RUN_COMPOSER=0"
if not "%~1"=="" if /I not "%~1"=="--skip-composer" if /I not "%~1"=="--no-composer" set "PHP_EXE=%~1"
if defined PHP_EXE_OVERRIDE set "PHP_EXE=%PHP_EXE_OVERRIDE%"

echo.
echo ================================================================
echo   Syndicati Web - PHP, OpenSSL and Composer installer
echo ================================================================
echo.

call :find_php
if not defined PHP_EXE (
    call :install_php
    if errorlevel 1 goto :fail
    call :find_php
)

if not defined PHP_EXE (
    echo [ERROR] PHP was not found after installation.
    echo         Install PHP manually, or run:
    echo         install-web-deps.bat C:\path\to\php.exe
    goto :fail
)

for %%I in ("%PHP_EXE%") do set "PHP_DIR=%%~dpI"
if "%PHP_DIR:~-1%"=="\" set "PHP_DIR=%PHP_DIR:~0,-1%"

echo [INFO] Using PHP:
echo        %PHP_EXE%
"%PHP_EXE%" -v
echo.

call :add_user_path "%PHP_DIR%"
set "PATH=%PHP_DIR%;%PATH%"

call :normalize_openssl_config
call :ensure_openssl
if errorlevel 1 goto :fail

call :find_composer
if not defined COMPOSER_CMD (
    call :install_composer
    if errorlevel 1 goto :fail
    call :find_composer
)

if not defined COMPOSER_CMD (
    echo [ERROR] Composer was not found after installation.
    echo         Install Composer manually from https://getcomposer.org/download/
    goto :fail
)

echo [INFO] Using Composer:
echo        %COMPOSER_CMD%
echo.

if "%RUN_COMPOSER%"=="1" (
    echo [INFO] Installing PHP dependencies...
    if defined COMPOSER_PHAR (
        "%PHP_EXE%" "%COMPOSER_PHAR%" install --no-interaction --no-progress
    ) else (
        call "%COMPOSER_CMD%" install --no-interaction --no-progress
    )
    if errorlevel 1 goto :composer_fail
) else (
    echo [INFO] Skipping composer install because --skip-composer was passed.
)

echo.
echo [OK] PHP is installed, OpenSSL is enabled, Composer is available.
echo [OK] If this is a new terminal, reopen IntelliJ/VS Code/terminal once so PATH refreshes everywhere.
echo.
pause
exit /b 0

:find_php
if defined PHP_EXE (
    if exist "%PHP_EXE%" exit /b 0
    echo [WARN] Explicit PHP path does not exist:
    echo        %PHP_EXE%
    set "PHP_EXE="
)

for /f "delims=" %%p in ('where php 2^>nul') do (
    if not defined PHP_EXE set "PHP_EXE=%%p"
)
if defined PHP_EXE exit /b 0

for /f "delims=" %%p in ('dir /b /s "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.*\php.exe" 2^>nul') do (
    if not defined PHP_EXE set "PHP_EXE=%%p"
)
if defined PHP_EXE exit /b 0

if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe"
if defined PHP_EXE exit /b 0

if exist "C:\tools\php\php.exe" set "PHP_EXE=C:\tools\php\php.exe"
exit /b 0

:install_php
where winget >nul 2>&1
if errorlevel 1 (
    echo [ERROR] WinGet was not found, so this script cannot auto-install PHP.
    echo         Install "App Installer" from Microsoft Store, then run this again.
    exit /b 1
)

echo [INFO] PHP was not found. Installing PHP with WinGet...
winget install -e --id PHP.PHP.8.4 --accept-package-agreements --accept-source-agreements
if errorlevel 1 (
    echo [WARN] PHP.PHP.8.4 failed. Trying generic PHP package...
    winget install -e --id PHP.PHP --accept-package-agreements --accept-source-agreements
)
if errorlevel 1 (
    echo [ERROR] WinGet could not install PHP.
    exit /b 1
)
exit /b 0

:ensure_openssl
"%PHP_EXE%" -r "exit(extension_loaded('openssl') ? 0 : 1);" >nul 2>&1
if not errorlevel 1 (
    echo [OK] PHP OpenSSL extension is enabled.
    exit /b 0
)

echo [WARN] OpenSSL is not enabled for this PHP.
echo [INFO] Fixing php.ini automatically...

set "OPENSSL_FIXER=%~dp0tools\enable-php-openssl.ps1"
if not exist "%OPENSSL_FIXER%" (
    echo [ERROR] Missing helper script:
    echo        %OPENSSL_FIXER%
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%OPENSSL_FIXER%" -PhpExe "%PHP_EXE%"
if errorlevel 1 exit /b 1

echo [INFO] Re-checking OpenSSL...
"%PHP_EXE%" -r "exit(extension_loaded('openssl') ? 0 : 1);" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] OpenSSL still did not load.
    echo.
    echo PHP configuration:
    "%PHP_EXE%" --ini
    echo.
    echo This PHP build may not include php_openssl.dll, or Windows needs a fresh terminal.
    echo If it still fails after reopening the terminal, install another PHP build with OpenSSL.
    exit /b 1
)

echo [OK] PHP OpenSSL extension is enabled.
exit /b 0

:normalize_openssl_config
set "OPENSSL_FIXER=%~dp0tools\enable-php-openssl.ps1"
if exist "%OPENSSL_FIXER%" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%OPENSSL_FIXER%" -PhpExe "%PHP_EXE%" -NormalizeOnly >nul 2>&1
)
exit /b 0

:find_composer
for /f "delims=" %%c in ('where composer 2^>nul') do (
    if not defined COMPOSER_CMD set "COMPOSER_CMD=%%c"
)
if defined COMPOSER_CMD goto :composer_found

if exist "%ProgramData%\ComposerSetup\bin\composer.bat" set "COMPOSER_CMD=%ProgramData%\ComposerSetup\bin\composer.bat"
if defined COMPOSER_CMD goto :composer_found

if exist "%ProgramData%\ComposerSetup\bin\composer.phar" (
    set "COMPOSER_PHAR=%ProgramData%\ComposerSetup\bin\composer.phar"
    set "COMPOSER_CMD=%PHP_EXE% %ProgramData%\ComposerSetup\bin\composer.phar"
)

:composer_found
if defined COMPOSER_CMD (
    for %%I in ("%ProgramData%\ComposerSetup\bin") do if exist "%%~fI" set "COMPOSER_DIR=%%~fI"
    if defined COMPOSER_DIR call :add_user_path "%COMPOSER_DIR%"
)
exit /b 0

:install_composer
where winget >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Composer was not found and WinGet is unavailable.
    exit /b 1
)

echo [INFO] Composer was not found. Installing Composer with WinGet...
winget install -e --id Composer.Composer --accept-package-agreements --accept-source-agreements
if errorlevel 1 (
    echo [ERROR] WinGet could not install Composer.
    exit /b 1
)
exit /b 0

:add_user_path
set "DIR_TO_ADD=%~1"
if not exist "%DIR_TO_ADD%" exit /b 0

powershell -NoProfile -ExecutionPolicy Bypass -Command "$dir=$env:DIR_TO_ADD; $old=[Environment]::GetEnvironmentVariable('Path','User'); $parts=@(); if($old){$parts=$old -split ';' | Where-Object { $_ -and ($_.TrimEnd('\') -ine $dir.TrimEnd('\')) }}; $new=($dir+$parts)-join ';'; [Environment]::SetEnvironmentVariable('Path',$new,'User')" >nul 2>&1
exit /b 0

:composer_fail
echo.
echo [ERROR] Composer install failed.
echo [INFO] PHP OpenSSL status:
"%PHP_EXE%" -r "echo extension_loaded('openssl') ? 'openssl loaded' : 'openssl missing';"
echo.
goto :fail

:fail
echo.
echo [FAILED] Setup did not complete.
pause
exit /b 1
