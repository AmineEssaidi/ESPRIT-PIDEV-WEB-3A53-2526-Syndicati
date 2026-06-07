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
powershell -NoProfile -ExecutionPolicy Bypass -Command "$php=$env:PHP_EXE; if (-not (Test-Path -LiteralPath $php)) { Write-Host '[ERROR] PHP executable not found.'; exit 1 }; $phpDir=Split-Path -Parent $php; $ini=(& $php -r 'echo php_ini_loaded_file() ?: \"\";' 2>$null); if ([string]::IsNullOrWhiteSpace($ini)) { $ini=Join-Path $phpDir 'php.ini'; Write-Host '[WARN] PHP is not loading a php.ini file.'; Write-Host ('[INFO] Creating/using: ' + $ini); if (-not (Test-Path -LiteralPath $ini)) { $prod=Join-Path $phpDir 'php.ini-production'; $dev=Join-Path $phpDir 'php.ini-development'; if (Test-Path -LiteralPath $prod) { Copy-Item -LiteralPath $prod -Destination $ini -Force; Write-Host '[OK] Created php.ini from php.ini-production.' } elseif (Test-Path -LiteralPath $dev) { Copy-Item -LiteralPath $dev -Destination $ini -Force; Write-Host '[OK] Created php.ini from php.ini-development.' } else { New-Item -ItemType File -Path $ini -Force | Out-Null; Write-Host '[OK] Created minimal php.ini.' } } } else { Write-Host ('[INFO] Loaded php.ini: ' + $ini) }; if (-not (Test-Path -LiteralPath $ini)) { Write-Host '[ERROR] php.ini path does not exist and could not be created.'; exit 1 }; $backup=$ini + '.bak-syndicati'; if (-not (Test-Path -LiteralPath $backup)) { Copy-Item -LiteralPath $ini -Destination $backup -Force }; $text=Get-Content -LiteralPath $ini -Raw; $extDir=Join-Path $phpDir 'ext'; if ((Test-Path -LiteralPath $extDir) -and ($text -notmatch '(?im)^\s*extension_dir\s*=')) { Add-Content -LiteralPath $ini -Value ('extension_dir=\"' + $extDir.Replace('\','/') + '\"') -Encoding ASCII; Write-Host '[OK] Added extension_dir.'; $text=Get-Content -LiteralPath $ini -Raw }; $opensslDll=Join-Path $extDir 'php_openssl.dll'; if ((Test-Path -LiteralPath $extDir) -and (-not (Test-Path -LiteralPath $opensslDll))) { Write-Host ('[WARN] Could not find php_openssl.dll in ' + $extDir); Write-Host '[WARN] Your PHP build may not include OpenSSL. Install a full PHP build if retry fails.' }; if ($text -match '(?im)^\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$') { Write-Host '[OK] OpenSSL extension line is already enabled.'; exit 0 }; if ($text -match '(?im)^\s*;\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$') { $text=[regex]::Replace($text, '(?im)^\s*;\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$', 'extension=openssl', 1); Set-Content -LiteralPath $ini -Value $text -Encoding ASCII; Write-Host '[OK] Uncommented extension=openssl.'; exit 0 }; Add-Content -LiteralPath $ini -Value '' -Encoding ASCII; Add-Content -LiteralPath $ini -Value 'extension=openssl' -Encoding ASCII; Write-Host '[OK] Added extension=openssl.'; exit 0"
if errorlevel 1 exit /b 1

exit /b 0
