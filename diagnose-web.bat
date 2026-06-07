@echo off
setlocal EnableExtensions

cd /d "%~dp0"

set "PHP_EXE="
set "PS_EXE=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
set "REQUIRED_PHP_EXTENSIONS=openssl curl pdo_mysql intl mbstring fileinfo gd sodium"
if defined PHP_EXE_OVERRIDE set "PHP_EXE=%PHP_EXE_OVERRIDE%"

if "%PHP_EXE%"=="" (
    if exist "%~dp0tools\php\php.exe" set "PHP_EXE=%~dp0tools\php\php.exe"
)

if "%PHP_EXE%"=="" (
    for /f "delims=" %%p in ('where php 2^>nul') do (
        if "%PHP_EXE%"=="" set "PHP_EXE=%%p"
    )
)

echo.
echo ================================================================
echo   Syndicati Web - diagnostics
echo ================================================================
echo.

if "%PHP_EXE%"=="" (
    echo [ERROR] PHP was not found. Run install-web-deps.bat first.
    pause
    exit /b 1
)

echo [PHP]
echo %PHP_EXE%
"%PHP_EXE%" -v
echo.

echo [PHP extensions]
for %%e in (%REQUIRED_PHP_EXTENSIONS%) do (
    "%PHP_EXE%" -r "exit(extension_loaded('%%e') ? 0 : 1);" >nul 2>&1
    if errorlevel 1 (
        echo MISSING %%e
    ) else (
        echo OK      %%e
    )
)
echo.

set "EXTENSION_FIXER=%~dp0tools\enable-php-extensions.ps1"
if exist "%EXTENSION_FIXER%" if exist "%PS_EXE%" (
    echo [Repair PHP extensions]
    "%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%EXTENSION_FIXER%" -PhpExe "%PHP_EXE%" -Extensions %REQUIRED_PHP_EXTENSIONS%
    echo.
)

echo [PHP extensions after repair]
for %%e in (%REQUIRED_PHP_EXTENSIONS%) do (
    "%PHP_EXE%" -r "exit(extension_loaded('%%e') ? 0 : 1);" >nul 2>&1
    if errorlevel 1 (
        echo MISSING %%e
    ) else (
        echo OK      %%e
    )
)
echo.

echo [Symfony env]
"%PHP_EXE%" -r "require __DIR__.'/config/infisical-bootstrap.php'; foreach(['APP_ENV','DATABASE_URL','APP_SECRET','INFISICAL_BOOTSTRAP','INFISICAL_PROJECT_ID'] as $k){$v=getenv($k); echo $k.'='.(is_string($v)&&$v!==''?'set':'missing').PHP_EOL;}"
echo.

echo [Clear prod cache]
"%PHP_EXE%" bin\console cache:clear --env=prod --no-warmup
echo.

echo [Symfony about]
"%PHP_EXE%" bin\console about --env=prod
echo.

echo [Database check]
"%PHP_EXE%" bin\console doctrine:query:sql "SELECT 1" --env=prod
echo.

echo [Recent prod log]
if exist var\log\prod.log (
    if exist "%PS_EXE%" (
        "%PS_EXE%" -NoProfile -Command "Get-Content -Tail 80 'var\log\prod.log'"
    ) else (
        type var\log\prod.log
    )
) else (
    echo No var\log\prod.log found yet.
)

echo.
pause
