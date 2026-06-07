@echo off
setlocal EnableExtensions EnableDelayedExpansion

cd /d "%~dp0"

set "PHP_EXE="
set "PHP_DIR="
set "PHP_INI="
set "COMPOSER_CMD="
set "HAS_WINGET=0"

echo.
echo ================================================================
echo   Syndicati Web - PHP + OpenSSL + Composer Setup
echo ================================================================
echo.

where winget >nul 2>&1
if %ERRORLEVEL% EQU 0 set "HAS_WINGET=1"

call :find_php
if not defined PHP_EXE (
    echo [INFO] PHP was not found. Installing PHP 8.4 with winget...
    if not "%HAS_WINGET%"=="1" (
        echo [ERROR] winget is not available, so PHP cannot be installed automatically.
        echo [HINT] Install PHP manually, add php.exe to PATH, then run this script again.
        pause
        exit /b 1
    )

    winget install -e --id PHP.PHP.8.4 --accept-package-agreements --accept-source-agreements
    call :find_php
)

if not defined PHP_EXE (
    echo [ERROR] PHP installation finished, but php.exe was still not found.
    echo [HINT] Reopen the terminal and run this script again.
    pause
    exit /b 1
)

for %%d in ("%PHP_EXE%") do set "PHP_DIR=%%~dpd"
if "!PHP_DIR:~-1!"=="\" set "PHP_DIR=!PHP_DIR:~0,-1!"

echo [OK] PHP found:
echo      %PHP_EXE%
echo.

call :put_php_on_user_path
call :ensure_php_ini
call :enable_openssl
call :verify_openssl
call :find_composer

if not defined COMPOSER_CMD (
    echo [INFO] Composer was not found. Installing Composer with winget...
    if not "%HAS_WINGET%"=="1" (
        echo [ERROR] winget is not available, so Composer cannot be installed automatically.
        echo [HINT] Install Composer manually, reopen terminal, then run this script again.
        pause
        exit /b 1
    )

    winget install -e --id Composer.Composer --accept-package-agreements --accept-source-agreements
    call :find_composer
)

if not defined COMPOSER_CMD (
    echo [ERROR] Composer was installed or expected, but composer was not found.
    echo [HINT] Reopen the terminal and run this script again.
    pause
    exit /b 1
)

echo.
echo [OK] Composer found:
echo      %COMPOSER_CMD%
echo.
echo [INFO] Running composer install...
echo.

composer install --no-interaction --no-progress
if errorlevel 1 (
    echo.
    echo [ERROR] composer install failed.
    echo [HINT] Reopen the terminal and run this script again. If it still fails, send the full output.
    pause
    exit /b 1
)

echo.
echo ================================================================
echo   Done
echo ================================================================
echo [OK] PHP is installed.
echo [OK] PHP is on your user PATH.
echo [OK] OpenSSL is enabled.
echo [OK] Composer dependencies are installed.
echo.
echo You can now run:
echo   symfony serve
echo or:
echo   php -S localhost:8000 -t public/
echo.
pause
exit /b 0

:find_php
set "PHP_EXE="

for /f "delims=" %%p in ('where php 2^>nul') do (
    if not defined PHP_EXE set "PHP_EXE=%%p"
)

if defined PHP_EXE exit /b 0

for /f "delims=" %%p in ('dir /b /s "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.*\php.exe" 2^>nul') do (
    if not defined PHP_EXE set "PHP_EXE=%%p"
)

if defined PHP_EXE exit /b 0

for %%p in (
    "C:\php\php.exe"
    "%ProgramFiles%\PHP\php.exe"
    "%ProgramFiles%\php\php.exe"
    "%ProgramFiles(x86)%\PHP\php.exe"
    "%ProgramFiles(x86)%\php\php.exe"
) do (
    if exist "%%~p" if not defined PHP_EXE set "PHP_EXE=%%~p"
)

exit /b 0

:put_php_on_user_path
echo [INFO] Adding PHP to user PATH...

set "CURRENT_USER_PATH="
for /f "tokens=2,*" %%a in ('reg query HKCU\Environment /v Path 2^>nul ^| findstr /i "Path"') do set "CURRENT_USER_PATH=%%b"

echo ;%CURRENT_USER_PATH%; | findstr /i /c:";%PHP_DIR%;" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] PHP directory already exists in user PATH.
) else (
    if defined CURRENT_USER_PATH (
        setx Path "%PHP_DIR%;%CURRENT_USER_PATH%" >nul
    ) else (
        setx Path "%PHP_DIR%" >nul
    )
    echo [OK] PHP directory added to user PATH.
)

set "PATH=%PHP_DIR%;%PATH%"
exit /b 0

:ensure_php_ini
set "PHP_INI="

for /f "delims=" %%l in ('"%PHP_EXE%" --ini 2^>nul ^| findstr /i "Loaded Configuration File"') do (
    set "INI_LINE=%%l"
)

if defined INI_LINE (
    set "PHP_INI=!INI_LINE:*Loaded Configuration File:=!"
    for /f "tokens=* delims= " %%t in ("!PHP_INI!") do set "PHP_INI=%%t"
)

if /i "%PHP_INI%"=="(none)" set "PHP_INI="

if not defined PHP_INI (
    set "PHP_INI=%PHP_DIR%\php.ini"
    echo [WARN] PHP is not loading a php.ini file.
    echo [INFO] Creating/using:
    echo      %PHP_INI%

    if not exist "%PHP_INI%" (
        if exist "%PHP_DIR%\php.ini-production" (
            copy /y "%PHP_DIR%\php.ini-production" "%PHP_INI%" >nul
            echo [OK] Created php.ini from php.ini-production.
        ) else if exist "%PHP_DIR%\php.ini-development" (
            copy /y "%PHP_DIR%\php.ini-development" "%PHP_INI%" >nul
            echo [OK] Created php.ini from php.ini-development.
        ) else (
            type nul > "%PHP_INI%"
            echo [OK] Created minimal php.ini.
        )
    )
) else (
    echo [OK] Loaded php.ini:
    echo      %PHP_INI%
)

if not exist "%PHP_INI%" (
    echo [ERROR] Could not create or find php.ini:
    echo      %PHP_INI%
    pause
    exit /b 1
)

if not exist "%PHP_INI%.bak-syndicati" copy /y "%PHP_INI%" "%PHP_INI%.bak-syndicati" >nul
exit /b 0

:enable_openssl
echo [INFO] Enabling OpenSSL in php.ini...

findstr /r /i /c:"^[ ]*extension[ ]*=[ ]*\(php_\)*openssl\(.dll\)*[ ]*$" "%PHP_INI%" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] extension=openssl is already enabled.
    exit /b 0
)

powershell -NoProfile -ExecutionPolicy Bypass -Command "$path=$env:PHP_INI; $text=Get-Content -LiteralPath $path -Raw; $phpDir=$env:PHP_DIR; $extDir=Join-Path $phpDir 'ext'; if ((Test-Path -LiteralPath $extDir) -and ($text -notmatch '(?im)^\s*extension_dir\s*=')) { Add-Content -LiteralPath $path -Value ('extension_dir=\"' + $extDir.Replace('\','/') + '\"') -Encoding ASCII; Write-Host '[OK] Added extension_dir.'; $text=Get-Content -LiteralPath $path -Raw }; if ($text -match '(?im)^\s*;\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$') { $text=[regex]::Replace($text, '(?im)^\s*;\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$', 'extension=openssl', 1); Set-Content -LiteralPath $path -Value $text -Encoding ASCII; Write-Host '[OK] Uncommented extension=openssl.' } else { Add-Content -LiteralPath $path -Value '' -Encoding ASCII; Add-Content -LiteralPath $path -Value 'extension=openssl' -Encoding ASCII; Write-Host '[OK] Added extension=openssl.' }; $opensslDll=Join-Path $extDir 'php_openssl.dll'; if ((Test-Path -LiteralPath $extDir) -and (-not (Test-Path -LiteralPath $opensslDll))) { Write-Host '[WARN] php_openssl.dll not found in ext folder. Your PHP build may be incomplete.' }"
if errorlevel 1 (
    echo [ERROR] Failed to update php.ini.
    echo [HINT] If PHP is installed in Program Files, run this script as Administrator.
    pause
    exit /b 1
)

exit /b 0

:verify_openssl
echo [INFO] Verifying OpenSSL...
"%PHP_EXE%" -m | findstr /i /x "openssl" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] OpenSSL is loaded.
    exit /b 0
)

echo [ERROR] OpenSSL is enabled in php.ini, but PHP still did not load it.
echo.
echo PHP:
echo   %PHP_EXE%
echo php.ini:
echo   %PHP_INI%
echo.
echo This usually means:
echo   - you need to reopen the terminal, or
echo   - your PHP build does not include ext\php_openssl.dll.
echo.
pause
exit /b 1

:find_composer
set "COMPOSER_CMD="
for /f "delims=" %%c in ('where composer 2^>nul') do (
    if not defined COMPOSER_CMD set "COMPOSER_CMD=%%c"
)
exit /b 0
