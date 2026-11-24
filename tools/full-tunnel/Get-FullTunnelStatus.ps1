[CmdletBinding()]
param()

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$statusPath = Join-Path $projectRoot '.full-tunnel\status.json'

if (-not (Test-Path -LiteralPath $statusPath)) {
    Write-Host 'Full system tunnel is NOT running.' -ForegroundColor Yellow
    exit 0
}

$status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
Write-Host "Public URL:   $($status.PublicUrl)" -ForegroundColor Green
Write-Host "Admin URL:    $($status.AdminUrl)" -ForegroundColor Cyan
Write-Host "Local URL:    $($status.LocalUrl)" -ForegroundColor White
Write-Host "Started At:   $($status.StartedAt)" -ForegroundColor Gray

Write-Host "`nProcesses:" -ForegroundColor White
foreach ($p in $status.Processes) {
    $alive = [bool](Get-Process -Id ([int]$p.Id) -ErrorAction SilentlyContinue)
    $state = if ($alive) { "[ALIVE]" } else { "[STOPPED]" }
    $color = if ($alive) { "Green" } else { "Red" }
    Write-Host "  $state $($p.Name) (PID: $($p.Id))" -ForegroundColor $color
}
