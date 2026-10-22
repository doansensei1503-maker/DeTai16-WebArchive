# =====================================================================
# DeTai16 - Khoi dong web app (Windows / PowerShell)
#
# Dung:
#   .\start.ps1
#   .\start.ps1 -Port 8000
#   .\start.ps1 -Fixtures     # chiay them may chu thu nghiem 8080
#
# Script nay tu tim php.exe, KHONG can cau hinh PATH.
# =====================================================================
param(
    [int]$Port = 8000,
    [switch]$Fixtures,
    [switch]$Foreground
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path

# ---------------------------------------------------------------------
# 1. Tim php.exe
# ---------------------------------------------------------------------
function Find-Php {
    # a) Trong PATH
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    # b) Thu muc cai dat cua project (neu da copi php.exe vao)
    $local = Join-Path $root 'php\php.exe'
    if (Test-Path $local) { return $local }

    # c) Winget packages (nhieu ban)
    $wingetRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (Test-Path $wingetRoot) {
        $found = Get-ChildItem -Path $wingetRoot -Filter 'php.exe' -Recurse -File -ErrorAction SilentlyContinue |
                 Sort-Object FullName -Descending |
                 Select-Object -First 1
        if ($found) { return $found.FullName }
    }

    # d) Thu muc PHP pho bien
    foreach ($p in @('C:\php\php.exe', 'C:\xampp\php\php.exe', 'C:\laragon\bin\php', 'C:\wamp64\bin\php')) {
        if (Test-Path $p) { return $p }
    }

    return $null
}

$php = Find-Php
if (-not $php) {
    Write-Host "[LOI] Khong tim thay php.exe." -ForegroundColor Red
    Write-Host "      Cai PHP 8.2+ hoac them thu muc chua php.exe vao PATH." -ForegroundColor Yellow
    exit 1
}

Write-Host "[OK] PHP : $php" -ForegroundColor Green
$ver = & $php -r "echo PHP_VERSION;" 2>$null
if ($ver) {
    Write-Host "     Version $ver" -ForegroundColor DarkGray
}

# ---------------------------------------------------------------------
# 2. Kiem tra port co dang bi chiem khong
# ---------------------------------------------------------------------
$existing = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
if ($existing) {
    $pid0 = $existing[0].OwningProcess
    Write-Host "[OK] Port $Port da co server dang chay (PID $pid0)." -ForegroundColor Green
    Write-Host "     Truy cap: http://127.0.0.1:$Port" -ForegroundColor Cyan
    Write-Host "     (Khong khoi dong trung lap. Muon dung lai: Stop-Process -Id $pid0)" -ForegroundColor DarkGray
    exit 0
}

# ---------------------------------------------------------------------
# 3. Kiem tra .env
# ---------------------------------------------------------------------
if (-not (Test-Path (Join-Path $root '.env'))) {
    Write-Host "[LOI] Khong tim thay file .env trong $root" -ForegroundColor Red
    exit 1
}

# ---------------------------------------------------------------------
# 4. Khoi dong web app
# ---------------------------------------------------------------------
Write-Host ""
Write-Host "=== DeTai16 - Web App ===" -ForegroundColor Cyan
Write-Host "  URL      : http://127.0.0.1:$Port" -ForegroundColor White
Write-Host "  Doc root : public" -ForegroundColor White
Write-Host "  Router   : public/router.php" -ForegroundColor White
Write-Host "  Login    : admin / admin1234" -ForegroundColor White
Write-Host ""

$webArgs = @('-S', "127.0.0.1:$Port", '-t', 'public', 'public/router.php')

if ($Foreground) {
    Write-Host "[Chay foreground - Ctrl+C de dung]" -ForegroundColor Yellow
    Push-Location $root
    & $php @webArgs
    Pop-Location
    exit 0
}

Push-Location $root
$web = Start-Process -FilePath $php -ArgumentList $webArgs -WorkingDirectory $root `
    -RedirectStandardOutput (Join-Path $root 'logs_server.txt') `
    -RedirectStandardError (Join-Path $root 'logs_server_err.txt') `
    -PassThru -WindowStyle Hidden
Pop-Location

Start-Sleep -Seconds 2

# ---------------------------------------------------------------------
# 5. Xac minh
# ---------------------------------------------------------------------
try {
    $r = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/login" -UseBasicParsing -TimeoutSec 5
    Write-Host "[OK] Web app phan hoi HTTP $($r.StatusCode)  (PID $($web.Id))" -ForegroundColor Green
} catch {
    Write-Host "[CANH BAO] Khong phan hoi duoc tren port $Port" -ForegroundColor Yellow
    Write-Host "         Kiem tra logs_server_err.txt" -ForegroundColor DarkGray
}

# ---------------------------------------------------------------------
# 6. May chu thu nghiem (optional)
# ---------------------------------------------------------------------
if ($Fixtures) {
    $fixPort = 8080
    $fix = Get-NetTCPConnection -LocalPort $fixPort -State Listen -ErrorAction SilentlyContinue
    if ($fix) {
        Write-Host "[OK] May chu thu nghiem da chay tren port $fixPort (PID $($fix[0].OwningProcess))." -ForegroundColor Green
    } else {
        Push-Location $root
        $fx = Start-Process -FilePath $php `
            -ArgumentList @('-S', "127.0.0.1:$fixPort", '-t', 'test_server', 'test_server/router.php') `
            -WorkingDirectory $root -PassThru -WindowStyle Hidden
        Pop-Location
        Start-Sleep -Seconds 1
        Write-Host "[OK] May chu thu nghiem: http://127.0.0.1:$fixPort (PID $($fx.Id))" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "Mo trinh duyet tai: http://127.0.0.1:$Port" -ForegroundColor Cyan