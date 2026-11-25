[CmdletBinding()]
param(
    [switch]$Wait
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$tunnelRoot = Join-Path $projectRoot '.full-tunnel'
$statusPath = Join-Path $tunnelRoot 'status.json'
$runId = [Guid]::NewGuid().ToString('N')
$logsRoot = Join-Path (Join-Path $tunnelRoot 'logs') $runId
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
Write-Host "Tunnel logs: $logsRoot"

function Start-LoggedProcess([string]$Name, [string]$FilePath, [string[]]$Arguments, [string]$WorkingDirectory = $projectRoot) {
    $stdout = Join-Path $logsRoot "$Name.out.log"
    $stderr = Join-Path $logsRoot "$Name.err.log"
    $process = Start-Process -FilePath $FilePath -ArgumentList $Arguments -WorkingDirectory $WorkingDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput $stdout -RedirectStandardError $stderr
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
$previousAppUrl = $env:APP_URL
$previousConfigCache = $env:APP_CONFIG_CACHE
$previousDatabaseQueue = $env:DB_QUEUE
try {
    $portProbe = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    $portProbe.Start()
    try {
        $port = $portProbe.LocalEndpoint.Port
    } finally {
        $portProbe.Stop()
    }
    $localUrl = "http://127.0.0.1:$port"
    Write-Host "Starting Cloudflare Tunnel to port $port..." -ForegroundColor Cyan
    $tunnelUrl = $null
    $retryDelays = @(20, 45)
    for ($attempt = 0; $attempt -le $retryDelays.Count; $attempt++) {
        $cf = Start-LoggedProcess "cloudflared-$($attempt + 1)" $cloudflared @('tunnel', '--no-autoupdate', '--url', $localUrl)
        $cf.Name = 'cloudflared'
        $processes.Add($cf)

        for ($second = 0; $second -lt 60; $second++) {
            $running = Get-Process -Id $cf.Id -ErrorAction SilentlyContinue
            $log = (Get-Content -LiteralPath @($cf.Stdout, $cf.Stderr) -Raw -ErrorAction SilentlyContinue) -join "`n"
            if (-not $running) { break }

            $match = [regex]::Match($log, 'https://(?!api\.)[a-z0-9-]+\.trycloudflare\.com')
            if ($match.Success) { $tunnelUrl = $match.Value; break }
            Start-Sleep -Seconds 1
        }

        if ($tunnelUrl) { break }
        if ($running) { throw 'Cloudflare did not provide a Quick Tunnel URL within 60 seconds.' }
        [void]$processes.Remove($cf)
        if ($log -notmatch 'quick tunnel provisioning failed with status 429\b') {
            throw "cloudflared stopped before creating a Quick Tunnel.`n$log"
        }
        if ($attempt -eq $retryDelays.Count) {
            throw "Cloudflare is still rate-limiting Quick Tunnel creation (HTTP 429). Wait a few minutes before starting it again. Details: $($cf.Stderr)"
        }

        $delay = $retryDelays[$attempt]
        Write-Warning "Cloudflare rate-limited Quick Tunnel creation (HTTP 429). Retrying in $delay seconds."
        Start-Sleep -Seconds $delay
    }

    # Give the web server and background workers the same URL for outgoing mail.
    $env:APP_URL = $tunnelUrl
    $env:APP_CONFIG_CACHE = Join-Path $logsRoot 'config-cache.php'
    # Local workers on the default queue must not send tunnel mail with local links.
    $env:DB_QUEUE = 'villa-mercedes-full-tunnel'

    Write-Host "Starting Laravel server (port $port)..." -ForegroundColor Cyan
    $router = Join-Path $projectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
    $server = Start-LoggedProcess 'server' $php @('-S', "127.0.0.1:$port", ('"{0}"' -f $router)) (Join-Path $projectRoot 'public')
    $processes.Add($server)

    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if (-not (Get-Process -Id $server.Id -ErrorAction SilentlyContinue)) { break }
        try {
            $response = Invoke-WebRequest -Uri "$localUrl/up" -UseBasicParsing -TimeoutSec 2
            $started = Select-String -LiteralPath $server.Stderr -SimpleMatch "($localUrl) started" -Quiet
            if ($started -and $response.StatusCode -eq 200) { $ready = $true; break }
        } catch {}
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw "The newly launched Laravel server did not become ready on port $port. Details: $($server.Stderr)" }

    Write-Host 'Starting background Queue worker...' -ForegroundColor Cyan
    $processes.Add((Start-LoggedProcess 'queue' $php @('artisan', 'queue:work', '--sleep=3', '--tries=3', '--timeout=90')))

    Write-Host 'Starting Schedule worker...' -ForegroundColor Cyan
    $processes.Add((Start-LoggedProcess 'scheduler' $php @('artisan', 'schedule:work')))

    $status = [ordered]@{
        StartedAt = (Get-Date).ToUniversalTime().ToString('o')
        PublicUrl = $tunnelUrl
        AdminUrl = "$tunnelUrl/admin"
        CustomerUrl = "$tunnelUrl"
        LocalUrl = $localUrl
        Processes = @($processes)
    }
    $status | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $statusPath -Encoding utf8

    Write-Host ''
    Write-Host '========================================================================' -ForegroundColor Green
    Write-Host ' FULL SYSTEM TUNNEL IS LIVE ON THE INTERNET!' -ForegroundColor Green
    Write-Host '========================================================================' -ForegroundColor Green
    Write-Host 'Guest site' -ForegroundColor Yellow
    Write-Host $status.CustomerUrl
    Write-Host 'Admin portal' -ForegroundColor Yellow
    Write-Host $status.AdminUrl
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
    foreach ($proc in $processes) {
        $p = Get-Process -Id ([int]$proc.Id) -ErrorAction SilentlyContinue
        if ($p) { Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue }
    }
    throw
}
finally {
    $env:APP_URL = $previousAppUrl
    $env:APP_CONFIG_CACHE = $previousConfigCache
    $env:DB_QUEUE = $previousDatabaseQueue
}
