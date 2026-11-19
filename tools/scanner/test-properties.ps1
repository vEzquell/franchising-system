$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'properties.ps1')
# Position 1 is deliberately NOT property 1; real drivers use sparse IDs.
$properties = @(
    [pscustomobject]@{ PropertyID = 3088; Value = 2 },
    [pscustomobject]@{ PropertyID = 6147; Value = 300 },
    [pscustomobject]@{ PropertyID = 7; Value = 'EPSON L5390' },
    [pscustomobject]@{ PropertyID = 15; Value = 'local' }
)
if ((PropertyValue $properties 7 'fallback') -ne 'EPSON L5390') { throw 'Scanner name lookup failed.' }
SetProperty $properties 3088 1 $true
if ($properties[0].Value -ne 1) { throw 'Feeder selection failed.' }
SetProperty $properties 6147 150
if ($properties[1].Value -ne 150) { throw 'Resolution setting failed.' }
if ((PropertyValue $properties 9999 'fallback') -ne 'fallback') { throw 'Missing property fallback failed.' }
SetProperty $properties 9999 150
$failed = $false
try { SetProperty $properties 9999 1 $true } catch { $failed = $_.Exception.Message.Contains('does not expose property 9999') }
if (!$failed) { throw 'Required missing property must report a useful error.' }
Write-Output 'Passed 6 scanner property regression checks.'

# Verify retry policy without operating scanner hardware.
$state = @{ Attempts = 0 }
$result = Invoke-WiaTransfer -DelayMilliseconds 0 -Transfer {
    $state.Attempts++
    if ($state.Attempts -lt 3) { throw (New-Object Runtime.InteropServices.COMException 'Scanner busy', -2145320954) }
    return 'page-image'
}
if ($state.Attempts -ne 3 -or $result -ne 'page-image') { throw 'Busy retry did not return the successful page.' }
$state.Attempts = 0
try {
    Invoke-WiaTransfer -MaxAttempts 3 -DelayMilliseconds 0 -Transfer { $state.Attempts++; throw (New-Object Runtime.InteropServices.COMException 'Scanner busy', -2145320954) }
    throw 'Expected busy failure'
} catch { if ((Get-WiaErrorCode $_.Exception) -ne '80210006' -or $state.Attempts -ne 3) { throw } }
$state.Attempts = 0
try {
    Invoke-WiaTransfer -DelayMilliseconds 0 -Transfer { $state.Attempts++; throw (New-Object Runtime.InteropServices.COMException 'Paper empty', -2145320957) }
    throw 'Expected empty feeder'
} catch { if ((Get-WiaErrorCode $_.Exception) -ne '80210003' -or $state.Attempts -ne 1) { throw } }
Write-Output 'Passed busy recovery, bounded retries, and no retry for empty feeder.'

$state.Attempts = 0
$result = Invoke-WiaTransfer -DelayMilliseconds 0 -PaperReady { $true } -Transfer {
    $state.Attempts++
    if ($state.Attempts -lt 3) { throw (New-Object Runtime.InteropServices.COMException 'Stale paper empty', -2145320957) }
    return 'recovered-page'
}
if ($result -ne 'recovered-page' -or $state.Attempts -ne 3) { throw 'Ready feeder must recover from stale empty response.' }
$state.Attempts = 0
try {
    Invoke-WiaTransfer -DelayMilliseconds 0 -PaperReady { $false } -Transfer { $state.Attempts++; throw (New-Object Runtime.InteropServices.COMException 'Paper empty', -2145320957) }
    throw 'Expected empty feeder'
} catch { if ((Get-WiaErrorCode $_.Exception) -ne '80210003' -or $state.Attempts -ne 1) { throw } }
$state.Attempts = 0
try {
    Invoke-WiaTransfer -DelayMilliseconds 0 -PaperReady { $true } -Transfer { $state.Attempts++; throw (New-Object Runtime.InteropServices.COMException 'Stale paper empty', -2145320957) }
    throw 'Expected bounded stale-empty failure'
} catch { if ((Get-WiaErrorCode $_.Exception) -ne '80210003' -or $state.Attempts -ne 4) { throw } }
Write-Output 'Passed feeder-ready recovery, actual empty detection, and bounded empty retries.'

foreach ($code in @('80210006', '8021000D', '80070021')) {
    $driverError = [Runtime.InteropServices.COMException]::new('Scanner error', [Convert]::ToInt32($code, 16))
    $wrappedError = [InvalidOperationException]::new('Wrapped scanner error', $driverError)
    if ((Get-ScannerDriverMessage $wrappedError) -notlike '*busy or locked*') { throw "Expected an actionable scanner lock message for $code." }
}
$rpcFailure = [Runtime.InteropServices.COMException]::new('RPC failed', [Convert]::ToInt32('800706BE', 16))
if ((Get-ScannerDriverMessage $rpcFailure) -notlike '*lost contact with the scanner service*Epson/TWAIN*') { throw 'Expected an actionable scanner service message.' }
$offlineFailure = [Runtime.InteropServices.COMException]::new('Scanner offline', [Convert]::ToInt32('80210005', 16))
if ((Get-ScannerDriverMessage $offlineFailure) -notlike '*USB connection*') { throw 'Expected an actionable offline scanner message.' }
foreach ($code in @('80210001', '8021000E')) {
    $driverError = [Runtime.InteropServices.COMException]::new('Raw driver error', [Convert]::ToInt32($code, 16))
    $wrappedError = [InvalidOperationException]::new('GetResult failed', $driverError)
    if ((Get-ScannerDriverMessage $wrappedError) -notlike '*driver reported an error*Epson/TWAIN*') { throw "Expected an actionable scanner driver message for $code." }
}
Write-Output 'Passed scanner lock, device disconnect, service failure, and driver error message checks.'

$modernItems = [pscustomobject]@{
    Count = 2
    Entries = @(
        @{ Properties = @(@{ PropertyID = 4125; Value = '{FE131934-F84C-42AD-8DA4-6129CDDD7288}' }) },
        @{ Properties = @(@{ PropertyID = 4125; Value = '{FB607B1F-43F3-488B-855B-FB703EC342A6}' }) }
    )
}
$modernItems | Add-Member -MemberType ScriptMethod -Name Item -Value { param($index) $this.Entries[$index - 1] }
$modernDevice = @{ Items = $modernItems; Properties = @() }
$modernSources = Get-WiaSourceItems $modernDevice
if ($modernSources.feeder -ne 1 -or $modernSources.flatbed -ne 2) { throw 'Modern WIA sources must follow item categories.' }
if ((@(Get-WiaSources $modernDevice $modernSources) -join ',') -ne 'flatbed,feeder') { throw 'Modern WIA sources must be advertised without legacy properties.' }

$legacyDevice = @{
    Items = @{ Count = 1 }
    Properties = @(@{ PropertyID = 3086; Value = 19 }, @{ PropertyID = 3088; Value = 2 })
}
if ((@(Get-WiaSources $legacyDevice @{}) -join ',') -ne 'flatbed,feeder') { throw 'Legacy Epson capabilities must advertise both sources.' }
$legacyDevice.Properties = @()
if ((@(Get-WiaSources $legacyDevice @{}) -join ',') -ne 'flatbed') { throw 'An unknown legacy scanner must retain the flatbed fallback.' }
Write-Output 'Passed modern and legacy scanner source discovery checks.'

$scanProperties = @(
    [pscustomobject]@{ PropertyID = 6147; Value = 300 },
    [pscustomobject]@{ PropertyID = 6148; Value = 150 },
    [pscustomobject]@{ PropertyID = 6149; Value = 5 },
    [pscustomobject]@{ PropertyID = 6150; Value = 10 },
    [pscustomobject]@{ PropertyID = 6151; Value = 2545; SubTypeMax = 2550 },
    [pscustomobject]@{ PropertyID = 6152; Value = 1650; SubTypeMax = 2100 }
)
$feederProperties = @(
    [pscustomobject]@{ PropertyID = 3078; Value = 2 },
    [pscustomobject]@{ PropertyID = 3097; Value = 1 }
)
Set-WiaScanPaperSize $scanProperties $feederProperties 'auto' 'feeder'
if ((PropertyValue $scanProperties 6152 0) -ne 1650) { throw 'Driver default paper size changed.' }
Set-WiaScanPaperSize $scanProperties $feederProperties 'long-bond' 'feeder'
if ((PropertyValue $scanProperties 6151 0) -ne 2550 -or (PropertyValue $scanProperties 6152 0) -ne 1950) { throw 'Long bond must use the full 8.5 x 13 in at the negotiated DPI.' }
if ((PropertyValue $scanProperties 6150 10) -ne 0 -or (PropertyValue $feederProperties 3097 0) -ne 2) { throw 'The scan must start at the top of the custom paper size.' }

Set-WiaScanPaperSize $scanProperties $feederProperties 'a4' 'feeder'
if ((PropertyValue $scanProperties 6151 0) -ne 2480 -or (PropertyValue $scanProperties 6152 0) -ne 1754 -or (PropertyValue $scanProperties 6149 0) -ne 70) { throw 'A4 must honor the feeder alignment.' }

(Find-WiaProperty $scanProperties 6152).SubTypeMax = 1755
try {
    Set-WiaScanPaperSize $scanProperties @() 'long-bond' 'flatbed'
    throw 'Expected the short flatbed scan area to reject long bond.'
} catch {
    if (!$_.Exception.Message.Contains('cannot capture the full')) { throw }
}
Write-Output 'Passed driver-default, full long bond, A4 alignment, and unsupported scan extent checks.'
