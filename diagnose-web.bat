@echo off
setlocal EnableExtensions

cd /d "%~dp0"

set "PHP_EXE="
set "SYMFONY_PHP_CGI="
set "SYMFONY_PHP_CHECK="
set "PS_EXE=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
set "REQUIRED_PHP_EXTENSIONS=openssl curl pdo_mysql intl mbstring fileinfo gd sodium"
set "INSTALLER=%~dp0install-web-deps.bat"
set "PROJECT_PHP_INI_WRITER=%~dp0tools\write-symfony-project-php-ini.ps1"
if defined PHP_EXE_OVERRIDE set "PHP_EXE=%PHP_EXE_OVERRIDE%"

if "%PHP_EXE%"=="" (
    if exist "%~dp0tools\php\php.exe" set "PHP_EXE=%~dp0tools\php\php.exe"
)

if "%PHP_EXE%"=="" (
    for /f "delims=" %%p in ('where php 2^>nul') do (
        if "%PHP_EXE%"=="" set "PHP_EXE=%%p"
    )
)

for /f "delims=" %%p in ('where php-cgi 2^>nul') do (
    if "%SYMFONY_PHP_CGI%"=="" set "SYMFONY_PHP_CGI=%%p"
)

if not "%SYMFONY_PHP_CGI%"=="" (
    for %%I in ("%SYMFONY_PHP_CGI%") do (
        if exist "%%~dpIphp.exe" set "SYMFONY_PHP_CHECK=%%~dpIphp.exe"
    )
    if "%SYMFONY_PHP_CHECK%"=="" set "SYMFONY_PHP_CHECK=%SYMFONY_PHP_CGI%"
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

if not "%SYMFONY_PHP_CGI%"=="" (
    echo [Symfony CLI PHP-CGI candidate]
    echo %SYMFONY_PHP_CGI%
    "%SYMFONY_PHP_CGI%" -v
    echo Extension checks for this install use:
    echo %SYMFONY_PHP_CHECK%
    echo.
)

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
    "%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%EXTENSION_FIXER%" -PhpExe "%PHP_EXE%" -Extensions "%REQUIRED_PHP_EXTENSIONS%"
    if not "%SYMFONY_PHP_CGI%"=="" (
        echo.
        echo [Repair Symfony CLI PHP-CGI extensions]
        "%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%EXTENSION_FIXER%" -PhpExe "%SYMFONY_PHP_CGI%" -Extensions "%REQUIRED_PHP_EXTENSIONS%"
    )
    echo.
)

if not "%SYMFONY_PHP_CGI%"=="" if exist "%PROJECT_PHP_INI_WRITER%" if exist "%PS_EXE%" (
    echo [Write Symfony project php.ini]
    "%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%PROJECT_PHP_INI_WRITER%" -PhpExe "%SYMFONY_PHP_CGI%" -ProjectDir "%~dp0" -Extensions "%REQUIRED_PHP_EXTENSIONS%"
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

if not "%SYMFONY_PHP_CGI%"=="" (
    echo [Symfony CLI PHP-CGI extensions after repair]
    for %%e in (%REQUIRED_PHP_EXTENSIONS%) do (
        "%SYMFONY_PHP_CHECK%" -r "exit(extension_loaded('%%e') ? 0 : 1);" >nul 2>&1
        if errorlevel 1 (
            echo MISSING %%e
        ) else (
            echo OK      %%e
        )
    )
    echo.
)

echo [Composer/vendor repair]
if not exist vendor\autoload.php (
    echo vendor\autoload.php is missing.
    if exist "%INSTALLER%" (
        call "%INSTALLER%" --no-pause
    ) else (
        echo Missing install-web-deps.bat. Run composer install manually.
    )
) else if not exist vendor\google\apiclient-services\autoload.php (
    echo vendor\google\apiclient-services\autoload.php is missing.
    echo Composer dependencies look incomplete. Running installer repair...
    if exist "%INSTALLER%" (
        call "%INSTALLER%" --no-pause
    ) else (
        echo Missing install-web-deps.bat. Run composer install manually.
    )
) else (
    echo OK vendor dependencies look present.
)
echo.

echo [Symfony env]
"%PHP_EXE%" -r "require __DIR__.'/config/infisical-bootstrap.php'; foreach(['APP_ENV','DATABASE_URL','APP_SECRET','INFISICAL_BOOTSTRAP','INFISICAL_PROJECT_ID'] as $k){$v=getenv($k); echo $k.'='.(is_string($v)&&$v!==''?'set':'missing').PHP_EOL;}"
echo.

echo [Clear prod cache]
"%PHP_EXE%" bin\console cache:clear --env=prod --no-warmup
echo.

echo [Prepare frontend assets]
"%PHP_EXE%" bin\console importmap:install --env=prod --no-interaction
"%PHP_EXE%" bin\console assets:install public --env=prod --no-interaction
"%PHP_EXE%" bin\console asset-map:compile --env=prod --no-interaction
echo.

echo [Symfony about]
"%PHP_EXE%" bin\console about --env=prod
echo.

echo [Database check]
"%PHP_EXE%" bin\console doctrine:query:sql "SELECT 1" --env=prod
echo.

echo [Launch note]
echo If all checks above are OK but the browser still shows 500, stop the old server.
echo Then run start-web.bat so the site uses this repaired PHP executable.
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
