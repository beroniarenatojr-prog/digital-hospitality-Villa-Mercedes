param(
    [Parameter(Mandatory)]
    [string]$StatusPath,
    [Parameter(Mandatory)]
    [string]$ResetScript
)

$ErrorActionPreference = 'Stop'
$manila = [TimeZoneInfo]::FindSystemTimeZoneById('Singapore Standard Time')
$targetLocal = [datetime]::SpecifyKind([datetime]'2026-09-20T20:00:00', [DateTimeKind]::Unspecified)
$targetUtc = [TimeZoneInfo]::ConvertTimeToUtc($targetLocal, $manila)

while ((Get-Date).ToUniversalTime() -lt $targetUtc) {
    if (-not (Test-Path -LiteralPath $StatusPath)) { exit 0 }
    Start-Sleep -Seconds 30
}

if (Test-Path -LiteralPath $StatusPath) {
    & $ResetScript
}
