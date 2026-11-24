[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$tunnelRoot = Join-Path $projectRoot '.full-tunnel'
$statusPath = Join-Path $tunnelRoot 'status.json'

if (-not (Test-Path -LiteralPath $statusPath)) {
    Write-Host 'No running full tunnel was recorded.'
    exit 0
}

$status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
foreach ($entry in $status.Processes) {
    $process = Get-Process -Id ([int]$entry.Id) -ErrorAction SilentlyContinue
    if (-not $process) { continue }

    $expectedPath = if ($entry.PSObject.Properties.Name -contains 'Path') { [string]$entry.Path } else { '' }
    if ($expectedPath -and -not [string]::Equals($process.Path, $expectedPath, [System.StringComparison]::OrdinalIgnoreCase)) {
        Write-Warning "Skipped PID $($entry.Id): executable no longer matches recorded process."
        continue
    }

    Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
    Write-Host "Stopped $($entry.Name) (PID $($entry.Id))."
}

Remove-Item -LiteralPath $statusPath -Force -ErrorAction SilentlyContinue
Write-Host 'The Full System Tunnel is offline. MySQL and Laragon remain running.' -ForegroundColor Green
