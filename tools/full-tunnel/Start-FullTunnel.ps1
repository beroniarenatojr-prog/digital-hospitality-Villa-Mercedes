[CmdletBinding()]
param(
    [switch]$Wait
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$tunnelRoot = Join-Path $projectRoot '.full-tunnel'
$statusPath = Join-Path $tunnelRoot 'status.json'
$logsRoot = Join-Path $tunnelRoot 'logs'
$php = (Get-Command php).Source

$cloudflared = Join-Path $projectRoot '.preview\bin\cloudflared.exe'
if (-not (Test-Path -LiteralPath $cloudflared)) {
    # If not found in .preview, check system path
    $cfCmd = Get-Command cloudflared -ErrorAction SilentlyContinue
    if ($cfCmd) {
        $cloudflared = $cfCmd.Source
    } else {
        throw 'cloudflared.exe was not found. Please ensure .preview\bin\cloudflared.exe exists.'
    }
}

if (Test-Path -LiteralPath $statusPath) {
    Write-Warning 'A full tunnel status file already exists. Running Stop-FullTunnel first...'
    & (Join-Path $PSScriptRoot 'Stop-FullTunnel.ps1')
}

New-Item -ItemType Directory -Force -Path $logsRoot | Out-Null
Get-ChildItem -LiteralPath $logsRoot -File -ErrorAction SilentlyContinue | Remove-Item -Force

function Start-LoggedProcess([string]$Name, [string]$FilePath, [string[]]$Arguments) {
    $stdout = Join-Path $logsRoot "$Name.out.log"
    $stderr = Join-Path $logsRoot "$Name.err.log"
    $process = Start-Process -FilePath $FilePath -ArgumentList $Arguments -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput $stdout -RedirectStandardError $stderr
    return [pscustomobject]@{
        Name = $Name
        Id = $process.Id
        Path = $process.Path
        StartedAt = $process.StartTime.ToUniversalTime().ToString('o')
        Stdout = $stdout
        Stderr = $stderr
    }
}

$processes = [System.Collections.Generic.List[object]]::new()
try {
    Write-Host 'Starting Laravel server (port 8000)...' -ForegroundColor Cyan
    $processes.Add((Start-LoggedProcess 'server' $php @('artisan', 'serve', '--host=127.0.0.1', '--port=8000')))

    Write-Host 'Starting background Queue worker...' -ForegroundColor Cyan
    $processes.Add((Start-LoggedProcess 'queue' $php @('artisan', 'queue:work', '--sleep=3', '--tries=3', '--timeout=90')))

    Write-Host 'Starting Schedule worker...' -ForegroundColor Cyan
    $processes.Add((Start-LoggedProcess 'scheduler' $php @('artisan', 'schedule:work')))

    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/up' -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) { $ready = $true; break }
        } catch {}
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'The Laravel application did not become ready on port 8000.' }

    # Capture child php server process listening on 8000
    $listeners = netstat -ano | Select-String '^\s*TCP\s+127\.0\.0\.1:8000\s+.*LISTENING\s+(\d+)\s*$'
    foreach ($listener in $listeners) {
        $match = [regex]::Match($listener.Line, '^\s*TCP\s+127\.0\.0\.1:8000\s+.*LISTENING\s+(\d+)\s*$')
        if (-not $match.Success) { continue }
        $child = Get-Process -Id ([int]$match.Groups[1].Value) -ErrorAction SilentlyContinue
        if ($child -and -not ($processes.Id -contains $child.Id)) {
            $processes.Add([pscustomobject]@{
                Name = 'http-server'
                Id = $child.Id
                Path = $child.Path
                StartedAt = $child.StartTime.ToUniversalTime().ToString('o')
                Stdout = ''
                Stderr = ''
            })
        }
    }

    Write-Host 'Starting Cloudflare Tunnel to port 8000...' -ForegroundColor Cyan
    $processes.Add((Start-LoggedProcess 'cloudflared' $cloudflared @('tunnel', '--no-autoupdate', '--url', 'http://127.0.0.1:8000')))

    $tunnelUrl = $null
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        $text = @()
        foreach ($path in @((Join-Path $logsRoot 'cloudflared.out.log'), (Join-Path $logsRoot 'cloudflared.err.log'))) {
            if (Test-Path -LiteralPath $path) { $text += Get-Content -LiteralPath $path -Raw }
        }
        $cf = $processes | Where-Object { $_.Name -eq 'cloudflared' } | Select-Object -First 1
        if ($cf -and -not (Get-Process -Id $cf.Id -ErrorAction SilentlyContinue)) {
            $err = Get-Content -LiteralPath (Join-Path $logsRoot 'cloudflared.err.log') -Raw -ErrorAction SilentlyContinue
            throw "cloudflared stopped prematurely.`n$err"
        }
        $match = [regex]::Match(($text -join "`n"), 'https://(?!api\.)[a-z0-9-]+\.trycloudflare\.com')
        if ($match.Success) { $tunnelUrl = $match.Value; break }
        Start-Sleep -Seconds 1
    }

    if (-not $tunnelUrl) { throw 'Cloudflare did not provide a Quick Tunnel URL within 60 seconds.' }

    $status = [ordered]@{
        StartedAt = (Get-Date).ToUniversalTime().ToString('o')
        PublicUrl = $tunnelUrl
        AdminUrl = "$tunnelUrl/admin"
        CustomerUrl = "$tunnelUrl"
        LocalUrl = 'http://127.0.0.1:8000'
        Processes = @($processes)
    }
    $status | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $statusPath -Encoding utf8

    Write-Host ''
    Write-Host '========================================================================' -ForegroundColor Green
    Write-Host ' FULL SYSTEM TUNNEL IS LIVE ON THE INTERNET!' -ForegroundColor Green
    Write-Host '========================================================================' -ForegroundColor Green
    Write-Host "Public URL (Guest & Admin): $tunnelUrl" -ForegroundColor Yellow
    Write-Host "Admin Portal URL:           $tunnelUrl/admin" -ForegroundColor Yellow
    Write-Host "Local Fallback URL:         http://127.0.0.1:8000" -ForegroundColor White
    Write-Host '========================================================================' -ForegroundColor Green
    Write-Host 'Every system function is enabled: Registration, OTP, Bookings, Payments,' -ForegroundColor White
    Write-Host 'Admin approvals, Walk-ins, Reports, and Background Queues.' -ForegroundColor White
    Write-Host '========================================================================' -ForegroundColor Green

    if ($Wait) {
        Write-Host "Full Tunnel is active. Keeping process alive..." -ForegroundColor Cyan
        while (Test-Path -LiteralPath $statusPath) {
            Start-Sleep -Seconds 10
        }
    }

    return $status
}
catch {
    Write-Error "Failed to start full system tunnel: $_"
    foreach ($proc in $processes) {
        $p = Get-Process -Id ([int]$proc.Id) -ErrorAction SilentlyContinue
        if ($p) { Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue }
    }
    throw
}
