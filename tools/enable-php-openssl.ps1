param(
    [Parameter(Mandatory = $true)]
    [string] $PhpExe,

    [switch] $NormalizeOnly
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $PhpExe)) {
    Write-Host "[ERROR] PHP executable not found: $PhpExe"
    exit 1
}

$alreadyLoaded = $false
try {
    & $PhpExe -r "exit(extension_loaded('openssl') ? 0 : 1);" *> $null
    $alreadyLoaded = ($LASTEXITCODE -eq 0)
} catch {
    $alreadyLoaded = $false
}

$phpDir = Split-Path -Parent $PhpExe
$ini = $null

try {
    $iniOutput = & $PhpExe --ini 2>&1
    foreach ($line in $iniOutput) {
        if ($line -match '^\s*Loaded Configuration File:\s*(.+?)\s*$') {
            $candidate = $Matches[1].Trim().Trim('"')
            if ($candidate -and $candidate -ne '(none)') {
                $ini = $candidate
            }
            break
        }
    }
} catch {
    Write-Host "[WARN] Could not inspect PHP ini through php --ini: $($_.Exception.Message)"
}

if ([string]::IsNullOrWhiteSpace($ini)) {
    $ini = Join-Path $phpDir 'php.ini'
    Write-Host "[WARN] PHP is not loading a php.ini file."
    Write-Host "[INFO] Creating/using: $ini"

    if (-not (Test-Path -LiteralPath $ini)) {
        $production = Join-Path $phpDir 'php.ini-production'
        $development = Join-Path $phpDir 'php.ini-development'

        if (Test-Path -LiteralPath $production) {
            Copy-Item -LiteralPath $production -Destination $ini -Force
            Write-Host "[OK] Created php.ini from php.ini-production."
        } elseif (Test-Path -LiteralPath $development) {
            Copy-Item -LiteralPath $development -Destination $ini -Force
            Write-Host "[OK] Created php.ini from php.ini-development."
        } else {
            New-Item -ItemType File -Path $ini -Force | Out-Null
            Write-Host "[OK] Created minimal php.ini."
        }
    }
} else {
    Write-Host "[INFO] Loaded php.ini: $ini"
}

if (-not (Test-Path -LiteralPath $ini)) {
    Write-Host "[ERROR] php.ini path does not exist and could not be created: $ini"
    exit 1
}

$backup = "$ini.bak-syndicati"
if (-not (Test-Path -LiteralPath $backup)) {
    Copy-Item -LiteralPath $ini -Destination $backup -Force
    Write-Host "[OK] Backup created: $backup"
}

$text = Get-Content -LiteralPath $ini -Raw
$extDir = Join-Path $phpDir 'ext'

$enabledOpenSslPattern = '(?im)^\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$'
$enabledOpenSslMatches = [regex]::Matches($text, $enabledOpenSslPattern)
if ($enabledOpenSslMatches.Count -gt 1) {
    $seen = $false
    $text = [regex]::Replace($text, $enabledOpenSslPattern, {
        param($match)
        if ($seen) {
            '; duplicate disabled by Syndicati setup: ' + $match.Value.Trim()
        } else {
            $seen = $true
            'extension=openssl'
        }
    })
    Set-Content -LiteralPath $ini -Value $text -Encoding ASCII
    Write-Host "[OK] Removed duplicate OpenSSL extension lines from php.ini."
}

if ($alreadyLoaded) {
    Write-Host "[OK] OpenSSL is already loaded by this PHP build/configuration."
    exit 0
}

if ($NormalizeOnly) {
    Write-Host "[INFO] OpenSSL is not loaded yet; normalize-only mode did not enable it."
    exit 0
}

if (Test-Path -LiteralPath $extDir) {
    if ($text -notmatch '(?im)^\s*extension_dir\s*=') {
        Add-Content -LiteralPath $ini -Value ('extension_dir="' + $extDir.Replace('\', '/') + '"') -Encoding ASCII
        Write-Host "[OK] Added extension_dir."
        $text = Get-Content -LiteralPath $ini -Raw
    }

    $opensslDll = Join-Path $extDir 'php_openssl.dll'
    if (-not (Test-Path -LiteralPath $opensslDll)) {
        Write-Host "[WARN] php_openssl.dll was not found in: $extDir"
        Write-Host "[WARN] This PHP build may not include OpenSSL. Install a full PHP build if retry fails."
    }
} else {
    Write-Host "[WARN] PHP ext folder was not found: $extDir"
}

if ($text -match '(?im)^\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$') {
    Write-Host "[OK] OpenSSL extension line is already enabled."
    exit 0
}

if ($text -match '(?im)^\s*;\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$') {
    $text = [regex]::Replace(
        $text,
        '(?im)^\s*;\s*extension\s*=\s*(php_)?openssl(\.dll)?\s*$',
        'extension=openssl',
        1
    )
    Set-Content -LiteralPath $ini -Value $text -Encoding ASCII
    Write-Host "[OK] Uncommented extension=openssl."
    exit 0
}

Add-Content -LiteralPath $ini -Value '' -Encoding ASCII
Add-Content -LiteralPath $ini -Value 'extension=openssl' -Encoding ASCII
Write-Host "[OK] Added extension=openssl."
exit 0
