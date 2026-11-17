# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Windows version of scripts/setup.sh (PowerShell 5.1 or 7).
# Creates .env from .env.example and fills in random secrets.
# Run it one time, before the first "docker compose up -d":
#   powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
# It never changes an .env file that already exists.
# Optional: $env:ADMIN_EMAIL = "you@example.org" before you run it.

$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

if (Test-Path '.env') {
    Write-Output 'OK: .env already exists. Nothing changed.'
    exit 0
}

$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
function Get-RandomByte([int]$Count) {
    $bytes = New-Object byte[] $Count
    $rng.GetBytes($bytes)
    return , $bytes
}
function Get-RandomHex([int]$Count) {
    return -join ((Get-RandomByte $Count) | ForEach-Object { $_.ToString('x2') })
}

$adminEmail = if ($env:ADMIN_EMAIL) { $env:ADMIN_EMAIL } else { 'admin@example.com' }
$adminPassword = Get-RandomHex 12
$values = @{
    'APP_KEY'                = 'base64:' + [Convert]::ToBase64String((Get-RandomByte 32))
    'DB_PASSWORD'            = Get-RandomHex 24
    'REVERB_APP_ID'          = [string](100000 + ([BitConverter]::ToUInt32((Get-RandomByte 4), 0) % 900000))
    'REVERB_APP_KEY'         = Get-RandomHex 16
    'REVERB_APP_SECRET'      = Get-RandomHex 24
    'INITIAL_ADMIN_EMAIL'    = $adminEmail
    'INITIAL_ADMIN_PASSWORD' = $adminPassword
}

$lines = foreach ($line in Get-Content '.env.example') {
    if ($line -match '^([A-Z_]+)=$' -and $values.ContainsKey($Matches[1])) {
        $Matches[1] + '=' + $values[$Matches[1]]
    } else {
        $line
    }
}

# LF line endings and no byte-order mark, the same file that setup.sh makes.
$path = Join-Path (Get-Location).Path '.env'
[System.IO.File]::WriteAllText($path, (($lines -join "`n") + "`n"), (New-Object System.Text.UTF8Encoding $false))

Write-Output 'OK: .env created with new random secrets.'
Write-Output ''
Write-Output 'First administrator (created on the first start):'
Write-Output "  Email:    $adminEmail"
Write-Output "  Password: $adminPassword"
Write-Output 'Save these now. They are also in .env.'
Write-Output ''
Write-Output 'Next: docker compose up -d'
