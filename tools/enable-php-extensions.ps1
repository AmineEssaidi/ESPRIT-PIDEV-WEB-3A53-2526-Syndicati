param(
    [Parameter(Mandatory = $true)]
    [string] $PhpExe,

    [Parameter(Mandatory = $true)]
    [string] $Extensions
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $PhpExe)) {
    Write-Host "[ERROR] PHP executable not found: $PhpExe"
    exit 1
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
    Write-Host "[WARN] Could not inspect PHP ini: $($_.Exception.Message)"
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
        } elseif (Test-Path -LiteralPath $development) {
            Copy-Item -LiteralPath $development -Destination $ini -Force
        } else {
            New-Item -ItemType File -Path $ini -Force | Out-Null
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

if (Test-Path -LiteralPath $extDir) {
    if ($text -notmatch '(?im)^\s*extension_dir\s*=') {
        Add-Content -LiteralPath $ini -Value ('extension_dir="' + $extDir.Replace('\', '/') + '"') -Encoding ASCII
        Write-Host "[OK] Added extension_dir."
        $text = Get-Content -LiteralPath $ini -Raw
    }
} else {
    Write-Host "[WARN] PHP ext folder was not found: $extDir"
}

$changed = $false
$extensionList = $Extensions -split '[,\s]+' | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }

foreach ($extension in $extensionList) {
    $extension = $extension.Trim()
    if (-not $extension) {
        continue
    }

    $loaded = $false
    try {
        & $PhpExe -r "exit(extension_loaded('$extension') ? 0 : 1);" *> $null
        $loaded = ($LASTEXITCODE -eq 0)
    } catch {
        $loaded = $false
    }

    $escaped = [regex]::Escape($extension)
    $enabledPattern = "(?im)^\s*extension\s*=\s*(php_)?$escaped(\.dll)?\s*$"
    $disabledPattern = "(?im)^\s*;\s*extension\s*=\s*(php_)?$escaped(\.dll)?\s*$"

    $matches = [regex]::Matches($text, $enabledPattern)
    if ($matches.Count -gt 1) {
        $seen = $false
        $text = [regex]::Replace($text, $enabledPattern, {
            param($match)
            if ($seen) {
                '; duplicate disabled by Syndicati setup: ' + $match.Value.Trim()
            } else {
                $seen = $true
                "extension=$extension"
            }
        })
        $changed = $true
        Write-Host "[OK] Removed duplicate extension lines for $extension."
    }

    if ($loaded) {
        Write-Host "[OK] $extension is already loaded."
        continue
    }

    $dll = Join-Path $extDir ("php_$extension.dll")
    if ((Test-Path -LiteralPath $extDir) -and -not (Test-Path -LiteralPath $dll)) {
        Write-Host "[WARN] php_$extension.dll was not found in: $extDir"
        Write-Host "[WARN] This PHP build may not include $extension."
    }

    if ($text -match $enabledPattern) {
        Write-Host "[OK] $extension is already enabled in php.ini."
        continue
    }

    if ($text -match $disabledPattern) {
        $text = [regex]::Replace($text, $disabledPattern, "extension=$extension", 1)
        $changed = $true
        Write-Host "[OK] Uncommented extension=$extension."
        continue
    }

    $text += [Environment]::NewLine + "extension=$extension" + [Environment]::NewLine
    $changed = $true
    Write-Host "[OK] Added extension=$extension."
}

if ($changed) {
    Set-Content -LiteralPath $ini -Value $text -Encoding ASCII
}

$missing = @()
foreach ($extension in $extensionList) {
    try {
        & $PhpExe -r "exit(extension_loaded('$extension') ? 0 : 1);" *> $null
        if ($LASTEXITCODE -ne 0) {
            $missing += $extension
        }
    } catch {
        $missing += $extension
    }
}

if ($missing.Count -gt 0) {
    Write-Host "[WARN] These extensions are still not loaded:"
    foreach ($extension in $missing) {
        Write-Host "       $extension"
    }
    exit 2
}

Write-Host "[OK] Required PHP extensions are loaded."
exit 0
