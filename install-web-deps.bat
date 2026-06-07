@echo off
setlocal EnableExtensions EnableDelayedExpansion

cd /d "%~dp0"

set "PHP_EXE="
set "PHP_DIR="
set "COMPOSER_CMD="
set "COMPOSER_DIR="
set "COMPOSER_PHAR="
set "RUN_COMPOSER=1"
set "PS_EXE="
set "PORTABLE_PHP_URL=https://windows.php.net/downloads/releases/php-8.4.22-nts-Win32-vs17-x64.zip"
set "PORTABLE_PHP_DIR=%~dp0tools\php"
set "PORTABLE_COMPOSER_DIR=%~dp0tools\composer"

if /I "%~1"=="--skip-composer" set "RUN_COMPOSER=0"
if /I "%~1"=="--no-composer" set "RUN_COMPOSER=0"
if not "%~1"=="" if /I not "%~1"=="--skip-composer" if /I not "%~1"=="--no-composer" set "PHP_EXE=%~1"
if defined PHP_EXE_OVERRIDE set "PHP_EXE=%PHP_EXE_OVERRIDE%"

echo.
echo ================================================================
echo   Syndicati Web - PHP, OpenSSL and Composer installer
echo ================================================================
echo.

call :find_powershell
if not defined PS_EXE (
    echo [ERROR] PowerShell was not found.
    echo         This script needs Windows PowerShell to download/install prerequisites.
    echo         Expected path:
    echo         %SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe
    goto :fail
)

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

if exist "%PORTABLE_PHP_DIR%\php.exe" set "PHP_EXE=%PORTABLE_PHP_DIR%\php.exe"
if defined PHP_EXE exit /b 0

if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe"
if defined PHP_EXE exit /b 0

if exist "C:\tools\php\php.exe" set "PHP_EXE=C:\tools\php\php.exe"
exit /b 0

:install_php
where winget >nul 2>&1
if errorlevel 1 (
    call :install_winget
    set "PATH=%LOCALAPPDATA%\Microsoft\WindowsApps;%PATH%"
)

where winget >nul 2>&1
if errorlevel 1 (
    echo [WARN] WinGet still was not found after installation attempt.
    echo [WARN] Falling back to portable PHP download...
    call :download_portable_php
    exit /b %ERRORLEVEL%
)

echo [INFO] PHP was not found. Installing PHP with WinGet...
winget install -e --id PHP.PHP.8.4 --accept-package-agreements --accept-source-agreements
if errorlevel 1 (
    echo [WARN] PHP.PHP.8.4 failed. Trying generic PHP package...
    winget install -e --id PHP.PHP --accept-package-agreements --accept-source-agreements
)
if errorlevel 1 (
    echo [WARN] WinGet could not install PHP. Falling back to portable PHP download...
    call :download_portable_php
    exit /b %ERRORLEVEL%
)
exit /b 0

:install_winget
echo [WARN] WinGet was not found.
echo [INFO] Installing Microsoft App Installer / WinGet...
echo.

"%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; $bundle=Join-Path $env:TEMP 'Microsoft.DesktopAppInstaller_8wekyb3d8bbwe.msixbundle'; Invoke-WebRequest -Uri 'https://aka.ms/getwinget' -OutFile $bundle; Add-AppxPackage -Path $bundle"
if errorlevel 1 (
    echo [WARN] WinGet installation failed or was blocked by Windows policy.
    echo [WARN] The script can still continue with portable PHP.
    exit /b 1
)

echo [OK] WinGet installation command completed.
echo [INFO] If Windows just installed App Installer, a fresh terminal may be needed.
exit /b 0

:download_portable_php
echo [INFO] Downloading portable PHP:
echo        %PORTABLE_PHP_URL%

if not exist "%~dp0tools" mkdir "%~dp0tools" >nul 2>&1
if exist "%PORTABLE_PHP_DIR%\php.exe" (
    echo [OK] Portable PHP already exists.
    set "PHP_EXE=%PORTABLE_PHP_DIR%\php.exe"
    exit /b 0
)

"%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $url=$env:PORTABLE_PHP_URL; $dest=Join-Path $env:TEMP 'syndicati-php.zip'; $out=$env:PORTABLE_PHP_DIR; if(Test-Path $out){Remove-Item -LiteralPath $out -Recurse -Force}; New-Item -ItemType Directory -Path $out -Force | Out-Null; [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri $url -OutFile $dest; Expand-Archive -LiteralPath $dest -DestinationPath $out -Force"
if errorlevel 1 (
    echo [ERROR] Could not download/extract portable PHP.
    echo         Check internet access, then run this script again.
    exit /b 1
)

if not exist "%PORTABLE_PHP_DIR%\php.exe" (
    echo [ERROR] Portable PHP extracted, but php.exe was not found.
    exit /b 1
)

set "PHP_EXE=%PORTABLE_PHP_DIR%\php.exe"
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

"%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%OPENSSL_FIXER%" -PhpExe "%PHP_EXE%"
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
    "%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%OPENSSL_FIXER%" -PhpExe "%PHP_EXE%" -NormalizeOnly >nul 2>&1
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
if defined COMPOSER_CMD goto :composer_found

if exist "%PORTABLE_COMPOSER_DIR%\composer.phar" (
    set "COMPOSER_PHAR=%PORTABLE_COMPOSER_DIR%\composer.phar"
    set "COMPOSER_CMD=%PHP_EXE% %PORTABLE_COMPOSER_DIR%\composer.phar"
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
    call :install_winget
    set "PATH=%LOCALAPPDATA%\Microsoft\WindowsApps;%PATH%"
)

where winget >nul 2>&1
if errorlevel 1 (
    echo [WARN] Composer was not found and WinGet is unavailable.
    echo [INFO] Falling back to portable composer.phar download...
    call :download_portable_composer
    exit /b %ERRORLEVEL%
)

echo [INFO] Composer was not found. Installing Composer with WinGet...
winget install -e --id Composer.Composer --accept-package-agreements --accept-source-agreements
if errorlevel 1 (
    echo [WARN] WinGet could not install Composer.
    echo [INFO] Falling back to portable composer.phar download...
    call :download_portable_composer
    exit /b %ERRORLEVEL%
)
exit /b 0

:download_portable_composer
if not exist "%PORTABLE_COMPOSER_DIR%" mkdir "%PORTABLE_COMPOSER_DIR%" >nul 2>&1
set "COMPOSER_PHAR=%PORTABLE_COMPOSER_DIR%\composer.phar"
set "COMPOSER_CMD=%PHP_EXE% %COMPOSER_PHAR%"

echo [INFO] Downloading Composer:
echo        https://getcomposer.org/download/latest-stable/composer.phar

"%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $env:COMPOSER_PHAR"
if errorlevel 1 (
    echo [ERROR] Could not download Composer.
    exit /b 1
)

if not exist "%COMPOSER_PHAR%" (
    echo [ERROR] Composer download finished, but composer.phar was not found.
    exit /b 1
)

exit /b 0

:add_user_path
set "DIR_TO_ADD=%~1"
if not exist "%DIR_TO_ADD%" exit /b 0

"%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -Command "$dir=$env:DIR_TO_ADD; $old=[Environment]::GetEnvironmentVariable('Path','User'); $parts=@(); if($old){$parts=$old -split ';' | Where-Object { $_ -and ($_.TrimEnd('\') -ine $dir.TrimEnd('\')) }}; $new=($dir+$parts)-join ';'; [Environment]::SetEnvironmentVariable('Path',$new,'User')" >nul 2>&1
exit /b 0

:find_powershell
if exist "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" (
    set "PS_EXE=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
    exit /b 0
)

for /f "delims=" %%p in ('where powershell 2^>nul') do (
    if not defined PS_EXE set "PS_EXE=%%p"
)
if defined PS_EXE exit /b 0

for /f "delims=" %%p in ('where pwsh 2^>nul') do (
    if not defined PS_EXE set "PS_EXE=%%p"
)
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
