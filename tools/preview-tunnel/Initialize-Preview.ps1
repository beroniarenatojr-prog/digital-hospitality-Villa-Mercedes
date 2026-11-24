[CmdletBinding()]
param(
    [switch]$SkipBuild,
    [switch]$SkipCloudflaredDownload,
    [string]$AdminPassword = 'Password123!',
    [PSCredential]$DatabaseAdministrator
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$previewRoot = [System.IO.Path]::GetFullPath((Join-Path $projectRoot '.preview'))
$runtimeRoot = [System.IO.Path]::GetFullPath((Join-Path $previewRoot 'runtime'))
$databaseName = 'digital_hospitality_villa_mercedes_preview'
$databaseUser = 'villa_preview'

if ([string]::IsNullOrWhiteSpace($AdminPassword)) {
    throw 'AdminPassword cannot be empty. Use a private preview credential.'
}

function Assert-PreviewPath([string]$Path) {
    $resolved = [System.IO.Path]::GetFullPath($Path)
    $prefix = $previewRoot.TrimEnd('\') + '\'
    if (-not $resolved.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to modify a path outside $previewRoot"
    }
}

function Get-DotEnvValue([string]$Path, [string]$Name, [string]$Default = '') {
    $line = Get-Content -LiteralPath $Path | Where-Object { $_ -match "^$([regex]::Escape($Name))=" } | Select-Object -First 1
    if (-not $line) { return $Default }
    $value = $line.Substring($Name.Length + 1).Trim()
    if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
        return $value.Substring(1, $value.Length - 2)
    }
    return $value
}

function New-RandomSecret([int]$Bytes = 24) {
    $buffer = New-Object byte[] $Bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($buffer)
    return ([Convert]::ToBase64String($buffer).TrimEnd('=') -replace '\+', 'A' -replace '/', 'B')
}

function Invoke-Process([string]$FilePath, [string[]]$Arguments, [string]$WorkingDirectory, [hashtable]$Environment = @{}) {
    $info = [System.Diagnostics.ProcessStartInfo]::new()
    $info.FileName = $FilePath
    $info.WorkingDirectory = $WorkingDirectory
    $info.UseShellExecute = $false
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    foreach ($argument in $Arguments) { [void]$info.ArgumentList.Add($argument) }
    foreach ($entry in $Environment.GetEnumerator()) { $info.Environment[$entry.Key] = [string]$entry.Value }
    $process = [System.Diagnostics.Process]::Start($info)
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) {
        throw "$FilePath failed with exit code $($process.ExitCode).`n$stdout`n$stderr"
    }
    if ($stdout) { Write-Host $stdout.TrimEnd() }
}

$sourceEnv = Join-Path $projectRoot '.env'
if (-not (Test-Path -LiteralPath $sourceEnv)) {
    throw 'The local .env file is required to locate MySQL, but it will not be copied into the preview.'
}

$statusPath = Join-Path $previewRoot 'status.json'
if (Test-Path -LiteralPath $statusPath) {
    throw 'The preview appears to be running. Stop it before rebuilding the isolated runtime.'
}

if (-not $SkipBuild) {
    Invoke-Process -FilePath (Get-Command npm.cmd).Source -Arguments @('run', 'build') -WorkingDirectory $projectRoot
}

New-Item -ItemType Directory -Force -Path $previewRoot | Out-Null
Assert-PreviewPath $runtimeRoot
if (Test-Path -LiteralPath $runtimeRoot) {
    Remove-Item -LiteralPath $runtimeRoot -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $runtimeRoot | Out-Null

$sourceDirectories = @('app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'vendor')
foreach ($directory in $sourceDirectories) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination (Join-Path $runtimeRoot $directory) -Recurse -Force
}

$runtimePublic = Join-Path $runtimeRoot 'public'
New-Item -ItemType Directory -Force -Path $runtimePublic | Out-Null
Get-ChildItem -LiteralPath (Join-Path $projectRoot 'public') -Force |
    Where-Object { $_.Name -notin @('storage', 'hot') } |
    ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $runtimePublic $_.Name) -Recurse -Force }

foreach ($file in @('artisan', 'composer.json', 'composer.lock')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination (Join-Path $runtimeRoot $file) -Force
}

$storageDirectories = @(
    'storage\app\private',
    'storage\app\public',
    'storage\app\uploads',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\testing',
    'storage\framework\views',
    'storage\logs',
    'bootstrap\cache'
)
foreach ($directory in $storageDirectories) {
    New-Item -ItemType Directory -Force -Path (Join-Path $runtimeRoot $directory) | Out-Null
}

$publicStorage = Join-Path $runtimePublic 'storage'
Assert-PreviewPath $publicStorage
if (Test-Path -LiteralPath $publicStorage) { Remove-Item -LiteralPath $publicStorage -Force }
New-Item -ItemType Junction -Path $publicStorage -Target (Join-Path $runtimeRoot 'storage\app\public') | Out-Null

$appKeyBytes = New-Object byte[] 32
[System.Security.Cryptography.RandomNumberGenerator]::Fill($appKeyBytes)
$appKey = 'base64:' + [Convert]::ToBase64String($appKeyBytes)
$databasePassword = New-RandomSecret
$adminPassword = $AdminPassword
$guestPassword = 'DemoGuest2026!'

$dbHost = Get-DotEnvValue $sourceEnv 'DB_HOST' '127.0.0.1'
$dbPort = Get-DotEnvValue $sourceEnv 'DB_PORT' '3306'
if (-not $DatabaseAdministrator -and $dbHost -notin @('127.0.0.1', '::1', 'localhost')) {
    throw 'Supply -DatabaseAdministrator when provisioning a preview on a remote MySQL host.'
}
$dbAdmin = if ($DatabaseAdministrator) { $DatabaseAdministrator.UserName } else { 'root' }
$dbAdminPassword = if ($DatabaseAdministrator) { $DatabaseAdministrator.GetNetworkCredential().Password } else { '' }

$php = (Get-Command php).Source
$databaseBootstrap = @'
$pdo = new PDO(
    "mysql:host=".getenv("VM_DB_HOST").";port=".getenv("VM_DB_PORT").";charset=utf8mb4",
    getenv("VM_DB_ADMIN"),
    getenv("VM_DB_ADMIN_PASSWORD"),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$password = $pdo->quote(getenv("VM_PREVIEW_DB_PASSWORD"));
$pdo->exec("CREATE DATABASE IF NOT EXISTS `digital_hospitality_villa_mercedes_preview` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("CREATE USER IF NOT EXISTS 'villa_preview'@'127.0.0.1' IDENTIFIED BY ".$password);
$pdo->exec("ALTER USER 'villa_preview'@'127.0.0.1' IDENTIFIED BY ".$password);
$pdo->exec("GRANT ALL PRIVILEGES ON `digital_hospitality_villa_mercedes_preview`.* TO 'villa_preview'@'127.0.0.1'");
$pdo->exec("FLUSH PRIVILEGES");
'@
Invoke-Process -FilePath $php -Arguments @('-r', $databaseBootstrap) -WorkingDirectory $projectRoot -Environment @{
    VM_DB_HOST = $dbHost
    VM_DB_PORT = $dbPort
    VM_DB_ADMIN = $dbAdmin
    VM_DB_ADMIN_PASSWORD = $dbAdminPassword
    VM_PREVIEW_DB_PASSWORD = $databasePassword
}

$commonEnvironment = @"
APP_NAME="Villa Mercedes Demonstration"
APP_KEY=$appKey
APP_DEBUG=false
APP_URL=http://127.0.0.1:8081
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=$dbHost
DB_PORT=$dbPort
DB_DATABASE=$databaseName
DB_USERNAME=$databaseUser
DB_PASSWORD=$databasePassword
SESSION_DRIVER=file
SESSION_LIFETIME=240
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
CACHE_STORE=file
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
MAIL_MAILER=log
MAIL_FROM_ADDRESS=preview@villamercedes.test
MAIL_FROM_NAME="Villa Mercedes Demonstration"
PREVIEW_MODE=true
PREVIEW_GUEST_EMAIL=customer@villamercedes.test
PREVIEW_GUEST_PASSWORD=$guestPassword
PREVIEW_ADMIN_EMAIL=admin@villamercedes.test
PREVIEW_ADMIN_PASSWORD=$adminPassword
RESORT_DEMO_CONTENT=true
RESORT_MESSENGER_URL=
RESORT_PHONE_HREF=#
RESORT_EMAIL=
PAYMENT_DEPOSIT_WORKFLOW=false
PAYMENT_GATEWAY=manual
PAYMONGO_SECRET_KEY=
PAYMONGO_PUBLIC_KEY=
PAYMENTS_GCASH_NUMBER=
PAYMENTS_MAYA_NUMBER=
PAYMENTS_BANK_NUMBER=
AUTH_REVEAL_PASSWORD_RESET_LINK=false
"@

$publicEnvironment = "APP_ENV=public`n" + $commonEnvironment + "`n" + @"
PREVIEW_PUBLIC_INTERFACE=true
SESSION_COOKIE=villa_mercedes_preview_public
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
"@
$adminEnvironment = "APP_ENV=admin`n" + $commonEnvironment + "`n" + @"
PREVIEW_PUBLIC_INTERFACE=false
APP_URL=http://127.0.0.1:8082
SESSION_COOKIE=villa_mercedes_preview_admin
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
"@

Set-Content -LiteralPath (Join-Path $runtimeRoot '.env.public') -Value $publicEnvironment -Encoding utf8NoBOM
Set-Content -LiteralPath (Join-Path $runtimeRoot '.env.admin') -Value $adminEnvironment -Encoding utf8NoBOM
Set-Content -LiteralPath (Join-Path $previewRoot 'admin-credentials.txt') -Value @"
Private administrator URL: http://127.0.0.1:8082/admin
Email: admin@villamercedes.test
Password: $adminPassword

Public demonstration guest
Email: customer@villamercedes.test
Password: $guestPassword
"@ -Encoding utf8NoBOM

Invoke-Process -FilePath $php -Arguments @('artisan', 'migrate:fresh', '--env=admin', '--seed', '--force') -WorkingDirectory $runtimeRoot
Invoke-Process -FilePath $php -Arguments @('artisan', 'optimize:clear', '--env=admin') -WorkingDirectory $runtimeRoot

if (-not $SkipCloudflaredDownload) {
    $binDirectory = Join-Path $previewRoot 'bin'
    New-Item -ItemType Directory -Force -Path $binDirectory | Out-Null
    $cloudflared = Join-Path $binDirectory 'cloudflared.exe'
    if (-not (Test-Path -LiteralPath $cloudflared)) {
        Write-Host 'Downloading the official Cloudflare Windows amd64 binary...'
        $curl = (Get-Command curl.exe).Source
        Invoke-Process -FilePath $curl -Arguments @(
            '-fL', '--retry', '3', '--output', $cloudflared,
            'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe'
        ) -WorkingDirectory $previewRoot
    }
    Invoke-Process -FilePath $cloudflared -Arguments @('version') -WorkingDirectory $previewRoot
}

Write-Host ''
Write-Host 'Preview runtime initialized safely.' -ForegroundColor Green
Write-Host "Runtime: $runtimeRoot"
Write-Host "Credentials: $(Join-Path $previewRoot 'admin-credentials.txt')"
Write-Host "Next: .\tools\preview-tunnel\Start-Preview.ps1"
