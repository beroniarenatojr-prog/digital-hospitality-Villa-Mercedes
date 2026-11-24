param(
    [Parameter(Mandatory)]
    [string]$StatusPath
)

$signature = @'
using System;
using System.Runtime.InteropServices;
public static class PreviewPower {
    [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern uint SetThreadExecutionState(uint flags);
}
'@
Add-Type -TypeDefinition $signature

$continuous = [Convert]::ToUInt32('80000000', 16)
$systemRequired = [uint32]1

try {
    while (Test-Path -LiteralPath $StatusPath) {
        [void][PreviewPower]::SetThreadExecutionState([uint32]($continuous -bor $systemRequired))
        Start-Sleep -Seconds 30
    }
}
finally {
    [void][PreviewPower]::SetThreadExecutionState($continuous)
}
