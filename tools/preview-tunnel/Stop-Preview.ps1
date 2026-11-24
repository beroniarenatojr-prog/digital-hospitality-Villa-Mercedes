[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$previewRoot = Join-Path $projectRoot '.preview'
$statusPath = Join-Path $previewRoot 'status.json'

if (-not (Test-Path -LiteralPath $statusPath)) {
    Write-Host 'No running preview was recorded.'
    exit 0
}

$status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
foreach ($entry in $status.Processes) {
    $process = Get-Process -Id ([int]$entry.Id) -ErrorAction SilentlyContinue
    if (-not $process) { continue }

    $expectedPath = if ($entry.PSObject.Properties.Name -contains 'Path') { [string]$entry.Path } else { '' }
    if ($expectedPath -and -not [string]::Equals($process.Path, $expectedPath, [System.StringComparison]::OrdinalIgnoreCase)) {
        Write-Warning "Skipped PID $($entry.Id): its executable no longer matches the recorded preview process."
        continue
    }

    Stop-Process -Id $process.Id -Force
    Write-Host "Stopped $($entry.Name) (PID $($entry.Id))."
}

Remove-Item -LiteralPath $statusPath -Force
Write-Host 'The Cloudflare preview is offline. Laragon and MySQL were left running.' -ForegroundColor Green
