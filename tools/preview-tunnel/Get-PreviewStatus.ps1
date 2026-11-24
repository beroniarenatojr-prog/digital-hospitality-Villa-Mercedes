$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$statusPath = Join-Path $projectRoot '.preview\status.json'

if (-not (Test-Path -LiteralPath $statusPath)) {
    Write-Host 'Preview status: offline'
    exit 1
}

$status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
Write-Host "Public URL: $($status.PublicUrl)" -ForegroundColor Cyan
Write-Host "Private admin: $($status.AdminUrl)" -ForegroundColor Yellow
Write-Host "Started: $($status.StartedAt)"
foreach ($entry in $status.Processes) {
    $running = [bool](Get-Process -Id ([int]$entry.Id) -ErrorAction SilentlyContinue)
    Write-Host ("{0,-12} PID {1,-7} {2}" -f $entry.Name, $entry.Id, $(if ($running) { 'running' } else { 'STOPPED' }))
}
