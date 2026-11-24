[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$previewRoot = Join-Path $projectRoot '.preview'
$runtimeRoot = Join-Path $previewRoot 'runtime'
$statusPath = Join-Path $previewRoot 'status.json'
$logsRoot = Join-Path $previewRoot 'logs'
$php = (Get-Command php).Source
$cloudflared = Join-Path $previewRoot 'bin\cloudflared.exe'

if (-not (Test-Path -LiteralPath (Join-Path $runtimeRoot 'artisan'))) {
    throw 'Preview runtime is missing. Run Initialize-Preview.ps1 first.'
}
if (-not (Test-Path -LiteralPath $cloudflared)) {
    throw 'cloudflared is missing. Run Initialize-Preview.ps1 without -SkipCloudflaredDownload.'
}
if (Test-Path -LiteralPath $statusPath) {
    throw 'A preview status file already exists. Run Stop-Preview.ps1 first.'
}

New-Item -ItemType Directory -Force -Path $logsRoot | Out-Null
Get-ChildItem -LiteralPath $logsRoot -File -ErrorAction SilentlyContinue | Remove-Item -Force

function Start-LoggedProcess([string]$Name, [string]$FilePath, [string[]]$Arguments) {
    $stdout = Join-Path $logsRoot "$Name.out.log"
    $stderr = Join-Path $logsRoot "$Name.err.log"
    $process = Start-Process -FilePath $FilePath -ArgumentList $Arguments -WorkingDirectory $runtimeRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput $stdout -RedirectStandardError $stderr
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
    $processes.Add((Start-LoggedProcess 'public' $php @('artisan', 'serve', '--env=public', '--host=127.0.0.1', '--port=8081')))
    $processes.Add((Start-LoggedProcess 'admin' $php @('artisan', 'serve', '--env=admin', '--host=127.0.0.1', '--port=8082')))
    $processes.Add((Start-LoggedProcess 'queue' $php @('artisan', 'queue:work', '--env=admin', '--sleep=3', '--tries=1', '--timeout=90')))
    $processes.Add((Start-LoggedProcess 'scheduler' $php @('artisan', 'schedule:work', '--env=admin')))

    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8081/up' -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) { $ready = $true; break }
        } catch {}
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'The public Laravel preview did not become ready on port 8081.' }

    $listeners = netstat -ano | Select-String '^\s*TCP\s+127\.0\.0\.1:(8081|8082)\s+.*LISTENING\s+(\d+)\s*$'
    foreach ($listener in $listeners) {
        $match = [regex]::Match($listener.Line, '^\s*TCP\s+127\.0\.0\.1:(8081|8082)\s+.*LISTENING\s+(\d+)\s*$')
        if (-not $match.Success) { continue }
        $child = Get-Process -Id ([int]$match.Groups[2].Value) -ErrorAction SilentlyContinue
        if ($child -and -not ($processes.Id -contains $child.Id)) {
            $processes.Add([pscustomobject]@{
                Name = if ($match.Groups[1].Value -eq '8081') { 'public-server' } else { 'admin-server' }
                Id = $child.Id
                Path = $child.Path
                StartedAt = $child.StartTime.ToUniversalTime().ToString('o')
                Stdout = ''
                Stderr = ''
            })
        }
    }

    $processes.Add((Start-LoggedProcess 'cloudflared' $cloudflared @('tunnel', '--no-autoupdate', '--url', 'http://127.0.0.1:8081')))

    $tunnelUrl = $null
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        $text = @()
        foreach ($path in @((Join-Path $logsRoot 'cloudflared.out.log'), (Join-Path $logsRoot 'cloudflared.err.log'))) {
            if (Test-Path -LiteralPath $path) { $text += Get-Content -LiteralPath $path -Raw }
        }
        $cloudflaredEntry = $processes | Where-Object { $_.Name -eq 'cloudflared' } | Select-Object -First 1
        if ($cloudflaredEntry -and -not (Get-Process -Id $cloudflaredEntry.Id -ErrorAction SilentlyContinue)) {
            $errorText = Get-Content -LiteralPath (Join-Path $logsRoot 'cloudflared.err.log') -Raw -ErrorAction SilentlyContinue
            throw "cloudflared stopped before publishing a tunnel URL.`n$errorText"
        }
        $match = [regex]::Match(($text -join "`n"), 'https://(?!api\.)[a-z0-9-]+\.trycloudflare\.com')
        if ($match.Success) { $tunnelUrl = $match.Value; break }
        Start-Sleep -Seconds 1
    }
    if (-not $tunnelUrl) { throw 'Cloudflare did not provide a Quick Tunnel URL within 60 seconds.' }

    $status = [ordered]@{
        StartedAt = (Get-Date).ToUniversalTime().ToString('o')
        PublicUrl = $tunnelUrl
        AdminUrl = 'http://127.0.0.1:8082/admin'
        Runtime = $runtimeRoot
        Processes = @($processes)
    }
    $status | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $statusPath -Encoding utf8NoBOM

    $windowsPowerShell = (Get-Command powershell.exe).Source
    $keepAwakeOut = Join-Path $logsRoot 'keep-awake.out.log'
    $keepAwakeErr = Join-Path $logsRoot 'keep-awake.err.log'
    $keepAwake = Start-Process -FilePath $windowsPowerShell -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', (Join-Path $PSScriptRoot 'Keep-Awake.ps1'), '-StatusPath', $statusPath) -WindowStyle Hidden -PassThru -RedirectStandardOutput $keepAwakeOut -RedirectStandardError $keepAwakeErr
    $status.Processes += [pscustomobject]@{
        Name = 'keep-awake'
        Id = $keepAwake.Id
        Path = $keepAwake.Path
        StartedAt = $keepAwake.StartTime.ToUniversalTime().ToString('o')
        Stdout = ''
        Stderr = ''
    }

    $manila = [TimeZoneInfo]::FindSystemTimeZoneById('Singapore Standard Time')
    $resetAt = [TimeZoneInfo]::ConvertTimeToUtc(
        [datetime]::SpecifyKind([datetime]'2026-09-20T20:00:00', [DateTimeKind]::Unspecified),
        $manila
    )
    if ((Get-Date).ToUniversalTime() -lt $resetAt) {
        $scheduledResetOut = Join-Path $logsRoot 'sunday-reset.out.log'
        $scheduledResetErr = Join-Path $logsRoot 'sunday-reset.err.log'
        $scheduledReset = Start-Process -FilePath $windowsPowerShell -ArgumentList @(
            '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', (Join-Path $PSScriptRoot 'Schedule-SundayReset.ps1'),
            '-StatusPath', $statusPath,
            '-ResetScript', (Join-Path $PSScriptRoot 'Reset-Preview.ps1')
        ) -WindowStyle Hidden -PassThru -RedirectStandardOutput $scheduledResetOut -RedirectStandardError $scheduledResetErr
        $status.Processes += [pscustomobject]@{
            Name = 'sunday-reset'
            Id = $scheduledReset.Id
            Path = $scheduledReset.Path
            StartedAt = $scheduledReset.StartTime.ToUniversalTime().ToString('o')
            Stdout = ''
            Stderr = ''
        }
    }
    $status | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $statusPath -Encoding utf8NoBOM

    Write-Host ''
    Write-Host 'Villa Mercedes demonstration is online.' -ForegroundColor Green
    Write-Host "Public guest/customer URL: $tunnelUrl" -ForegroundColor Cyan
    if (Select-String -LiteralPath (Join-Path $runtimeRoot '.env.public') -Pattern '^PREVIEW_PUBLIC_INTERFACE=false\s*$' -Quiet) {
        Write-Host "Public administrator sign-in: $tunnelUrl/admin/login" -ForegroundColor Cyan
    }
    Write-Host 'Local administrator URL: http://127.0.0.1:8082/admin' -ForegroundColor Yellow
    Write-Host "Credentials: $(Join-Path $previewRoot 'admin-credentials.txt')"
    Write-Host 'Keep this computer powered on and connected. Do not close MySQL.'
}
catch {
    foreach ($entry in $processes) {
        Stop-Process -Id $entry.Id -Force -ErrorAction SilentlyContinue
    }
    Remove-Item -LiteralPath $statusPath -Force -ErrorAction SilentlyContinue
    throw
}
