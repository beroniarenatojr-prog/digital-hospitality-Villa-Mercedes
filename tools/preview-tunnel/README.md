# Cloudflare Quick Tunnel demonstration

This runtime is intentionally temporary. It exposes only the guest/customer
interface while keeping the back office on loopback.

```powershell
.\tools\preview-tunnel\Initialize-Preview.ps1
.\tools\preview-tunnel\Start-Preview.ps1
.\tools\preview-tunnel\Get-PreviewStatus.ps1
.\tools\preview-tunnel\Reset-Preview.ps1
.\tools\preview-tunnel\Stop-Preview.ps1
```

The launcher prints the random `trycloudflare.com` URL. It changes if
`cloudflared` stops or the computer restarts. Administrator credentials are
written only to `.preview/admin-credentials.txt`; that directory is ignored by
Git. Keep MySQL and the computer running for the lifetime of the demonstration.

The initializer uses the local demonstration administrator credential by
default (`admin@villamercedes.test` / `Password123!`). Supply
`-AdminPassword` when a private preview needs a different credential. The
administrator remains on loopback and is not exposed by the public tunnel.

Run `Reset-Preview.ps1` after testing and before the assessed demonstration.
The reset is guarded to the exact preview database name and never targets the
ordinary development database.

When the tunnel is started before September 20, the launcher also schedules one
guarded reset for 8:00 PM Asia/Manila on Sunday, September 20. The tunnel
process is kept running during that reset, so its public URL does not change.
