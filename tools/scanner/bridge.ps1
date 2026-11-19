param(
    [Parameter(Mandatory = $true)]
    [string]$AllowedOrigin
)

$ErrorActionPreference = 'Stop'

if ([Threading.Thread]::CurrentThread.ApartmentState -ne 'STA') {
    throw 'Start this script with powershell.exe -STA.'
}

$originUri = $null
if (
    ![Uri]::TryCreate($AllowedOrigin, [UriKind]::Absolute, [ref]$originUri) -or
    $originUri.Scheme -notin @('http', 'https') -or
    $AllowedOrigin -cne $originUri.GetLeftPart([UriPartial]::Authority)
) {
    throw 'AllowedOrigin must be an exact origin, for example http://franchising.com'
}

Add-Type -AssemblyName System.Drawing
. (Join-Path $PSScriptRoot 'properties.ps1')
. (Join-Path $PSScriptRoot 'native.ps1')
. (Join-Path $PSScriptRoot 'twain.ps1')
$twainConsole = @(
    (Join-Path $env:ProgramFiles 'NAPS2\NAPS2.Console.exe'),
    (Join-Path $env:LOCALAPPDATA 'NAPS2-ScannerBridge\App\NAPS2.Console.exe')
) | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } | Select-Object -First 1
$script:twainDevices = @()
$nativeAvailable = $false
try {
    Initialize-NativeScanner
    $nativeAvailable = $true
}
catch {
    Write-Warning "Windows native scanner support is unavailable: $($_.Exception.Message)"
}

$sessionToken = [Guid]::NewGuid().ToString('N') +
                [Guid]::NewGuid().ToString('N')
$jpegFormat = '{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}'
$paperSizes = @('auto', 'letter', 'a4', 'long-bond', 'legal')

function Assert-ScannerIsIdle {
    if ($nativeAvailable -and (Test-NativeScannerOperationPending)) {
        throw 'A Windows scanner request is still stopping. Wait for the scanner to become idle before trying again.'
    }
}

function Get-ScannerDevices([hashtable]$TwainSources = @{}) {
    $manager = $null
    try {
        $manager = New-Object -ComObject WIA.DeviceManager
        foreach ($info in $manager.DeviceInfos) {
            if ([int]$info.Type -ne 1) { continue }

            $name = [string](PropertyValue $info.Properties 7 'Windows scanner')
            $sources = $TwainSources[$name]
            if ($null -eq $sources) {
                $device = $null
                $sources = @('flatbed')
                try {
                    $device = $info.Connect()
                    $sources = @(Get-WiaSources $device (Get-WiaSourceItems $device))
                }
                catch { Write-Warning "WIA could not check sources for ${name}: $(Get-ScannerDriverMessage $_.Exception)" }
                finally { Release-WiaObject $device }
            }

            @{
                id = [string]$info.DeviceID
                name = $name
                backend = 'wia'
                sources = $sources
            }
        }
    }
    finally {
        Release-WiaObject $manager
    }
}

function Get-AvailableScannerDevices {
    Assert-ScannerIsIdle
    try {
        $script:twainDevices = @(Get-TwainScanners)
    }
    catch {
        $script:twainDevices = @()
        Write-Warning "TWAIN scanner discovery failed: $($_.Exception.Message)"
    }

    # Use the matching TWAIN entry's sources without opening the Windows driver during discovery.
    $twainSources = @{}
    foreach ($device in $script:twainDevices) { $twainSources[$device.name] = $device.sources }
    $native = @()
    if ($nativeAvailable) {
        try { $native = @(Get-NativeScanners $twainSources) }
        catch { Write-Warning "Windows native scanner discovery failed: $($_.Exception.Message)" }
    }
    Assert-ScannerIsIdle

    # Prefer TWAIN for a matching scanner while keeping other devices in discovery order.
    $remainingTwainDevices = $script:twainDevices
    foreach ($device in $native) {
        if ($device.sources -contains 'feeder') {
            $remainingTwainDevices | Where-Object { $_.name -eq $device.name }
            $remainingTwainDevices = @($remainingTwainDevices | Where-Object { $_.name -ne $device.name })
        }
        $device
    }
    $remainingTwainDevices

    try { Get-ScannerDevices $twainSources }
    catch {
        if (!$native.Count -and !$script:twainDevices.Count) { throw }
        Write-Warning "Legacy scanner discovery failed: $($_.Exception.Message)"
    }
}

function Get-ScannedPages($Body) {
    if ([string]::IsNullOrWhiteSpace([string]$Body.device_id)) {
        throw 'Choose a scanner.'
    }
    $source = [string]$Body.source
    if ($source -cnotin @('flatbed', 'feeder')) {
        throw 'Choose the flatbed or document feeder.'
    }
    $requested = 0
    if (![int]::TryParse([string]$Body.pages, [ref]$requested) -or $requested -lt 1 -or $requested -gt 30) {
        throw 'Enter a page count from 1 to 30.'
    }
    if ($source -eq 'flatbed' -and $requested -ne 1) {
        throw 'Choose one page for a flatbed scan.'
    }
    $paperSize = [string]$Body.paper_size
    if (!$paperSize) { $paperSize = 'legal' }
    if ($paperSize -cnotin $paperSizes) { throw 'Choose a supported paper size.' }
    Assert-ScannerIsIdle

    if (([string]$Body.device_id).StartsWith('twain:', [StringComparison]::Ordinal)) {
        $selected = $script:twainDevices | Where-Object { $_.id -ceq [string]$Body.device_id } | Select-Object -First 1
        if (!$selected) { throw 'TWAIN scanner unavailable. Refresh the scanner list.' }
        return Scan-TwainPages $selected $source $requested $paperSize
    }
    if (([string]$Body.device_id).StartsWith('winrt:', [StringComparison]::Ordinal)) {
        if (!$nativeAvailable) { throw 'Windows native scanning is unavailable. Refresh the scanner list.' }
        return Scan-NativePages ([string]$Body.device_id) $source $requested $paperSize
    }

    return Scan-WiaPages ([string]$Body.device_id) $source $requested $paperSize
}

function Scan-WiaPages([string]$DeviceId, [string]$Source, [int]$Requested, [string]$PaperSize) {
    $manager = $null
    $device = $null
    $item = $null
    $results = New-Object 'System.Collections.Generic.List[object]'
    $totalBytes = 0

    try {
        $manager = New-Object -ComObject WIA.DeviceManager

        foreach ($info in $manager.DeviceInfos) {
            if (
                [int]$info.Type -eq 1 -and
                [string]$info.DeviceID -ceq $DeviceId
            ) {
                $device = $info.Connect()
                break
            }
        }

        if ($null -eq $device) {
            throw 'Scanner not found. Refresh scanners and try again.'
        }

        $sourceItems = Get-WiaSourceItems $device
        if ($Source -notin @(Get-WiaSources $device $sourceItems)) {
            throw 'This scanner does not support the selected source through WIA.'
        }

        if ($sourceItems.ContainsKey($Source)) {
            $item = $device.Items.Item($sourceItems[$Source])
            $handlingProperties = $item.Properties
        }
        else {
            # Legacy drivers expose one transfer item and switch sources on the device.
            $handlingProperties = $device.Properties
            $sourceFlag = if ($Source -eq 'feeder') { 1 } else { 2 }
            SetProperty $handlingProperties 3088 $sourceFlag ($Source -eq 'feeder')
            $item = $device.Items.Item(1)
        }

        # Request 150 DPI; unsupported settings keep the driver default.
        foreach ($id in @(6147, 6148)) {
            SetProperty $item.Properties $id 150
        }
        Set-WiaScanPaperSize $item.Properties $handlingProperties $PaperSize $Source

        $supportedFormats = @($item.Formats)
        $format = if ($supportedFormats.Count -and $supportedFormats -notcontains $jpegFormat) { [string]$supportedFormats[0] }
            else { $jpegFormat }
        while ($results.Count -lt $Requested) {
            $image = $null
            $stream = $null
            $bitmap = $null
            try {
                $paperReady = $null
                if ($Source -eq 'feeder') {
                    # WIA Automation returns one image per transfer.
                    SetProperty $handlingProperties 3096 1 $true
                    $paperReady = { ((PropertyValue $handlingProperties 3087 0) -band 1) -ne 0 }
                }
                try {
                    $image = Invoke-WiaTransfer -PaperReady $paperReady -Transfer { $item.Transfer($format) }
                }
                catch {
                    if ($Source -ne 'feeder' -or (Get-WiaErrorCode $_.Exception) -ne '80210003') { throw }
                    if (!$results.Count) {
                        throw 'WIA reports that the document feeder is empty. Reload and align the paper, or try the Epson/TWAIN scanner entry.'
                    }
                    break
                }
                [byte[]]$rawBytes = $image.FileData.BinaryData
                $stream = [IO.MemoryStream]::new($rawBytes, $false)
                $bitmap = [Drawing.Image]::FromStream($stream)
                Add-ScannerImagePages $bitmap $results ([ref]$totalBytes) $Requested
            }
            finally {
                if ($bitmap) { $bitmap.Dispose() }
                if ($stream) { $stream.Dispose() }
                Release-WiaObject $image
            }
        }
        return Get-ScanBatchResult ($results.ToArray()) $Requested
    }
    finally {
        Release-WiaObject $item
        Release-WiaObject $device
        Release-WiaObject $manager
    }
}

function Send-Json($Context, [int]$Status, $Value) {
    $json = ConvertTo-Json -InputObject $Value -Depth 8 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)
    $Context.Response.StatusCode = $Status
    $Context.Response.ContentType = 'application/json; charset=utf-8'
    $Context.Response.ContentLength64 = $bytes.Length
    $Context.Response.OutputStream.Write($bytes, 0, $bytes.Length)
}

$listener = New-Object Net.HttpListener
$listener.Prefixes.Add('http://127.0.0.1:8765/')

try {
    $listener.Start()
    Write-Host 'Scanner bridge: http://127.0.0.1:8765'
    Write-Host "Allowed website: $AllowedOrigin"
    Write-Host 'Keep this window open. Press Ctrl+C to stop.'

    while ($listener.IsListening) {
        $context = $listener.GetContext()

        try {
            $req = $context.Request
            $res = $context.Response
            $res.Headers['Cache-Control'] = 'no-store'

            # Reject other websites before issuing tokens or touching WIA.
            if ($req.Headers['Origin'] -cne $AllowedOrigin) {
                Send-Json $context 403 @{ error = 'Origin is not allowed.' }
                continue
            }

            $res.Headers['Access-Control-Allow-Origin'] = $AllowedOrigin
            $res.Headers['Vary'] = 'Origin'
            $res.Headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS'
            $res.Headers['Access-Control-Allow-Headers'] =
                'Content-Type, X-Scanner-Token'
            $res.Headers['Access-Control-Allow-Private-Network'] = 'true'

            if ($req.HttpMethod -eq 'OPTIONS') {
                $res.StatusCode = 204
                continue
            }

            $path = $req.Url.AbsolutePath

            if ($path -eq '/session' -and $req.HttpMethod -eq 'GET') {
                Send-Json $context 200 @{ token = $sessionToken }
                continue
            }

            if ($req.Headers['X-Scanner-Token'] -cne $sessionToken) {
                Send-Json $context 401 @{ error = 'Invalid scanner session. Refresh scanners.' }
                continue
            }

            if ($path -eq '/devices' -and $req.HttpMethod -eq 'GET') {
                $found = @(Get-AvailableScannerDevices)
                Send-Json $context 200 @{ devices = $found; paper_sizes = $paperSizes }
                continue
            }

            if ($path -eq '/scan' -and $req.HttpMethod -eq 'POST') {
                if (
                    $req.ContentLength64 -lt 1 -or
                    $req.ContentLength64 -gt 4096 -or
                    $req.ContentType -notmatch '^application/json(?:;|$)'
                ) {
                    Send-Json $context 400 @{ error = 'Expected a small JSON request.' }
                    continue
                }

                $reader = New-Object IO.StreamReader($req.InputStream)
                try {
                    $body = $reader.ReadToEnd() | ConvertFrom-Json
                }
                finally {
                    $reader.Dispose()
                }

                $result = Get-ScannedPages $body
                Send-Json $context 200 $result
                continue
            }

            Send-Json $context 404 @{ error = 'Unknown scanner endpoint or method.' }
        }
        catch {
            Write-Warning $_.Exception.ToString()
            Write-Host $_.InvocationInfo.PositionMessage -ForegroundColor Yellow
            Write-Host $_.ScriptStackTrace -ForegroundColor Yellow
            try {
                Send-Json $context 500 @{ error = Get-ScannerDriverMessage $_.Exception }
            }
            catch { }
        }
        finally {
            $context.Response.Close()
        }
    }
}
finally {
    $listener.Close()
}
