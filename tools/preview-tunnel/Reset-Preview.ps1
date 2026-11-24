[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$previewRoot = Join-Path $projectRoot '.preview'
$runtimeRoot = [System.IO.Path]::GetFullPath((Join-Path $previewRoot 'runtime'))
$envPath = Join-Path $runtimeRoot '.env.admin'
$php = (Get-Command php).Source
$statusPath = Join-Path $previewRoot 'status.json'
$logsRoot = Join-Path $previewRoot 'logs'

if (-not (Test-Path -LiteralPath $envPath)) { throw 'The isolated preview runtime is not initialized.' }
$envLines = Get-Content -LiteralPath $envPath
if ($envLines -notcontains 'PREVIEW_MODE=true' -or $envLines -notcontains 'DB_DATABASE=digital_hospitality_villa_mercedes_preview') {
    throw 'Refusing to reset: preview environment guards did not match.'
}

function Invoke-Artisan([string[]]$Arguments) {
    & $php artisan @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Artisan failed: $($Arguments -join ' ')" }
}

$status = $null
if (Test-Path -LiteralPath $statusPath) {
    $status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
    foreach ($entry in @($status.Processes | Where-Object { $_.Name -in @('queue', 'scheduler') })) {
        $process = Get-Process -Id ([int]$entry.Id) -ErrorAction SilentlyContinue
        if ($process) { Stop-Process -Id $process.Id -Force }
    }
}

Push-Location $runtimeRoot
try {
    Invoke-Artisan @('down', '--env=admin', '--retry=30')
    foreach ($relative in @('storage\app\uploads', 'storage\app\private', 'storage\app\public', 'storage\framework\sessions')) {
        $target = [System.IO.Path]::GetFullPath((Join-Path $runtimeRoot $relative))
        if (-not $target.StartsWith(($runtimeRoot.TrimEnd('\') + '\'), [System.StringComparison]::OrdinalIgnoreCase)) {
            throw "Unsafe reset path: $target"
        }
        if (Test-Path -LiteralPath $target) { Remove-Item -LiteralPath $target -Recurse -Force }
        New-Item -ItemType Directory -Force -Path $target | Out-Null
    }

    Invoke-Artisan @('migrate:fresh', '--env=admin', '--seed', '--force')
    Invoke-Artisan @('optimize:clear', '--env=admin')
}
finally {
    try { Invoke-Artisan @('up', '--env=admin') } catch { Write-Warning $_ }
    Pop-Location
}

if ($status) {
    $remaining = @($status.Processes | Where-Object { $_.Name -notin @('queue', 'scheduler') })
    $queueOut = Join-Path $logsRoot 'queue.out.log'
    $queueErr = Join-Path $logsRoot 'queue.err.log'
    $schedulerOut = Join-Path $logsRoot 'scheduler.out.log'
    $schedulerErr = Join-Path $logsRoot 'scheduler.err.log'
    $queue = Start-Process -FilePath $php -ArgumentList @('artisan', 'queue:work', '--env=admin', '--sleep=3', '--tries=1', '--timeout=90') -WorkingDirectory $runtimeRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput $queueOut -RedirectStandardError $queueErr
    $scheduler = Start-Process -FilePath $php -ArgumentList @('artisan', 'schedule:work', '--env=admin') -WorkingDirectory $runtimeRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput $schedulerOut -RedirectStandardError $schedulerErr
    $remaining += [pscustomobject]@{ Name = 'queue'; Id = $queue.Id; Path = $queue.Path; StartedAt = $queue.StartTime.ToUniversalTime().ToString('o'); Stdout = $queueOut; Stderr = $queueErr }
    $remaining += [pscustomobject]@{ Name = 'scheduler'; Id = $scheduler.Id; Path = $scheduler.Path; StartedAt = $scheduler.StartTime.ToUniversalTime().ToString('o'); Stdout = $schedulerOut; Stderr = $schedulerErr }
    $status.Processes = $remaining
    $status | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $statusPath -Encoding utf8NoBOM
}

Write-Host 'The isolated preview database and uploads were reset to their seeded demonstration state.' -ForegroundColor Green
