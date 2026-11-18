function Initialize-NativeScanner {
    Add-Type -AssemblyName System.Runtime.WindowsRuntime
    $null = [Windows.Devices.Scanners.ImageScanner, Windows.Devices.Scanners, ContentType=WindowsRuntime]
    $null = [Windows.Devices.Scanners.ImageScannerScanResult, Windows.Devices.Scanners, ContentType=WindowsRuntime]
    $null = [Windows.Devices.Scanners.ImageScannerAutoCroppingMode, Windows.Devices.Scanners, ContentType=WindowsRuntime]
    $null = [Windows.Devices.Enumeration.DeviceInformation, Windows.Devices.Enumeration, ContentType=WindowsRuntime]
    $null = [Windows.Devices.Enumeration.DeviceInformationCollection, Windows.Devices.Enumeration, ContentType=WindowsRuntime]
    $null = [Windows.Storage.StorageFolder, Windows.Storage, ContentType=WindowsRuntime]
    $null = [Windows.Graphics.Printing.PrintMediaSize, Windows.Graphics.Printing, ContentType=WindowsRuntime]
    $null = [Windows.Graphics.Printing.PrintOrientation, Windows.Graphics.Printing, ContentType=WindowsRuntime]
}
$script:pendingNativeScannerOperation = $null

function Test-NativeScannerOperationPending {
    $pending = $script:pendingNativeScannerOperation
    if ($null -eq $pending) { return $false }
    if (!$pending.Task.IsCompleted) { return $true }

    $script:pendingNativeScannerOperation = $null
    $pending.Cancellation.Dispose()
    if ($pending.Directory) {
        try { Remove-ScannerJobDirectory $pending.Directory }
        catch { Write-Warning "Could not remove the canceled scan's temporary files: $($_.Exception.Message)" }
    }
    return $false
}

function Wait-ScannerOperation($Operation, [Type]$ResultType, [Type]$ProgressType = $null, [int]$TimeoutMs = 540000, [switch]$UsesScanner) {
    $interfaceName = if ($null -ne $ProgressType) { 'IAsyncOperationWithProgress`2' } else { 'IAsyncOperation`1' }
    $method = [System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object {
        $parameters = $_.GetParameters()
        $_.Name -eq 'AsTask' -and $_.IsGenericMethod -and $parameters.Count -eq 2 -and
            $parameters[0].ParameterType.Name -eq $interfaceName -and $parameters[1].ParameterType -eq [Threading.CancellationToken]
    } | Select-Object -First 1
    $types = if ($null -ne $ProgressType) { [Type[]]@($ResultType, $ProgressType) } else { [Type[]]@($ResultType) }
    $cancellation = [Threading.CancellationTokenSource]::new()
    try {
        $task = $method.MakeGenericMethod($types).Invoke($null, @($Operation, $cancellation.Token))
        $completion = ([IAsyncResult]$task).AsyncWaitHandle
        if ($completion.WaitOne($TimeoutMs)) { return ,($task.GetAwaiter().GetResult()) }

        # Cancel on a timer thread so an unresponsive driver cannot block the bridge here.
        $cancellation.CancelAfter(0)
        if (!$completion.WaitOne(1000) -and $UsesScanner) {
            $script:pendingNativeScannerOperation = @{ Task = $task; Cancellation = $cancellation }
            $cancellation = $null
        }
        throw [TimeoutException]::new('The Windows scan driver timed out. Wait for the scanner to stop before retrying.')
    }
    finally {
        if ($null -ne $cancellation) { $cancellation.Dispose() }
    }
}
function Get-NativeScanners {
    $selector = [Windows.Devices.Scanners.ImageScanner]::GetDeviceSelector()
    $devices = Wait-ScannerOperation ([Windows.Devices.Enumeration.DeviceInformation]::FindAllAsync($selector)) ([Windows.Devices.Enumeration.DeviceInformationCollection]) -TimeoutMs 5000
    $scanSources = [Windows.Devices.Scanners.ImageScannerScanSource]
    foreach ($device in $devices) {
        $sources = @('flatbed')
        try {
            if (!(Test-NativeScannerOperationPending)) {
                $scanner = Wait-ScannerOperation ([Windows.Devices.Scanners.ImageScanner]::FromIdAsync($device.Id)) ([Windows.Devices.Scanners.ImageScanner]) -TimeoutMs 2000 -UsesScanner
                if ($null -ne $scanner) {
                    $sources = @(
                        if ($scanner.IsScanSourceSupported($scanSources::Flatbed)) { 'flatbed' }
                        if ($scanner.IsScanSourceSupported($scanSources::Feeder)) { 'feeder' }
                    )
                }
            }
        } catch {
            Write-Warning "Could not check scan sources for $($device.Name): $($_.Exception.Message)"
        }
        @{ id = 'winrt:' + $device.Id; name = $device.Name; backend = 'windows-native'; sources = $sources }
    }
}
function Convert-ScannerPage([Drawing.Image]$Image) {
    $bitmap = $null; $graphics = $null; $stream = $null; $parameters = $null
    try {
        $scale = [Math]::Min(1.0, 2000.0 / [Math]::Max($image.Width, $image.Height))
        $bitmap = New-Object Drawing.Bitmap ([Math]::Max(1, [int]($image.Width * $scale))), ([Math]::Max(1, [int]($image.Height * $scale)))
        $graphics = [Drawing.Graphics]::FromImage($bitmap)
        $graphics.DrawImage($image, 0, 0, $bitmap.Width, $bitmap.Height)
        $stream = New-Object IO.MemoryStream
        $codec = [Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object MimeType -eq 'image/jpeg'
        $parameters = New-Object Drawing.Imaging.EncoderParameters 1
        $parameters.Param[0] = New-Object Drawing.Imaging.EncoderParameter ([Drawing.Imaging.Encoder]::Quality), ([long]80)
        $bitmap.Save($stream, $codec, $parameters)
        return ,$stream.ToArray()
    } finally {
        if ($graphics) { $graphics.Dispose() }
        if ($bitmap) { $bitmap.Dispose() }
        if ($stream) { $stream.Dispose() }
        if ($parameters) { $parameters.Dispose() }
    }
}
function Add-ScannerImagePages([Drawing.Image]$Image, [System.Collections.Generic.List[object]]$Pages, [ref]$TotalBytes, [int]$Requested) {
    $dimension = [Drawing.Imaging.FrameDimension]::Page
    $hasPageFrames = $Image.FrameDimensionsList -contains $dimension.Guid
    $frameCount = if ($hasPageFrames) { $Image.GetFrameCount($dimension) } else { 1 }

    for ($index = 0; $index -lt $frameCount; $index++) {
        if ($Pages.Count -ge $Requested) { throw 'The scanner returned more pages than requested. Load only the pages for this document and scan again.' }
        if ($hasPageFrames) { [void]$Image.SelectActiveFrame($dimension, $index) }
        $bytes = Convert-ScannerPage $Image
        $TotalBytes.Value += $bytes.Length
        if ($TotalBytes.Value -gt 5MB) { throw 'The scan exceeds 5 MB. Scan a smaller batch.' }
        $Pages.Add(@{ data = [Convert]::ToBase64String($bytes) })
    }
}
function Get-ScanBatchResult([object[]]$Pages, [int]$Requested) {
    if ($Pages.Count -ne $Requested) {
        throw "The scanner returned $($Pages.Count) of $Requested expected pages. Reload the complete document, confirm the page count, and try again."
    }
    return @{ pages = @($Pages); scanned_pages = $Pages.Count; requested_pages = $Requested; complete = $true }
}

function Get-NativeScanPaths($Result, [string]$Directory) {
    $paths = @(foreach ($file in $Result.ScannedFiles) { $file.Path })
    if ($paths.Count) { return $paths }

    # Some drivers write the files without populating ScannedFiles.
    Get-ChildItem -LiteralPath $Directory -File |
        Where-Object Extension -In @('.jpg', '.jpeg', '.png', '.tif', '.tiff', '.bmp', '.dib') |
        Sort-Object { [regex]::Replace($_.Name, '\d+', { param($match) $match.Value.PadLeft(12, '0') }) } |
        ForEach-Object FullName
}

function Read-ScannerFiles([string[]]$Paths, [int]$Requested) {
    $pages = New-Object 'System.Collections.Generic.List[object]'
    $totalBytes = 0
    foreach ($path in $Paths) {
        $image = [Drawing.Image]::FromFile($path)
        try { Add-ScannerImagePages $image $pages ([ref]$totalBytes) $Requested }
        finally { $image.Dispose() }
    }
    return Get-ScanBatchResult ($pages.ToArray()) $Requested
}

function Remove-ScannerJobDirectory([string]$Directory) {
    $checkedRoot = [IO.Path]::GetFullPath($Directory) + [IO.Path]::DirectorySeparatorChar
    foreach ($file in Get-ChildItem -LiteralPath $Directory -File) {
        if (!$file.FullName.StartsWith($checkedRoot, [StringComparison]::OrdinalIgnoreCase)) { throw 'Invalid temporary scan path.' }
        Remove-Item -LiteralPath $file.FullName
    }
    [IO.Directory]::Delete($Directory, $false)
}

function Set-NativeScanPaperSize($Configuration, [string]$Source, [string]$PaperSize) {
    if ($PaperSize -eq 'auto') { return }

    $size = Get-ScannerPaperDimensions $PaperSize
    if (!$size) { throw 'Choose a supported paper size.' }
    $media = [Windows.Graphics.Printing.PrintMediaSize]
    $orientation = [Windows.Graphics.Printing.PrintOrientation]::Portrait

    if ($Source -eq 'feeder') {
        $mediaSize = switch ($PaperSize) {
            'letter' { $media::NorthAmericaLetter }
            'a4' { $media::IsoA4 }
            'long-bond' { $media::NorthAmericaGermanLegalFanfold }
            'legal' { $media::NorthAmericaLegal }
        }
        if ($Configuration.AutoDetectPageSize) { $Configuration.AutoDetectPageSize = $false }
        if (!$Configuration.IsPageSizeSupported($mediaSize, $orientation)) {
            # A Legal-sized feeder region can be limited to the exact Long Bond dimensions.
            if ($PaperSize -eq 'long-bond' -and $Configuration.IsPageSizeSupported($media::NorthAmericaLegal, $orientation)) {
                $mediaSize = $media::NorthAmericaLegal
            }
            else { throw 'The Windows driver cannot scan the selected paper size from the feeder.' }
        }
        $Configuration.PageOrientation = $orientation
        $Configuration.PageSize = $mediaSize
    }

    $limit = $Configuration.MaxScanArea
    if ($size.Width -gt ($limit.Width + 0.01) -or $size.Height -gt ($limit.Height + 0.01)) {
        if ($Source -eq 'flatbed') { throw 'The selected paper size is too large for the flatbed. Use the document feeder.' }
        throw 'The selected paper size exceeds the feeder scan area.'
    }

    $disabled = [Windows.Devices.Scanners.ImageScannerAutoCroppingMode]::Disabled
    if ($Configuration.AutoCroppingMode -ne $disabled) { $Configuration.AutoCroppingMode = $disabled }
    $region = $Configuration.SelectedScanRegion
    $region.X = 0
    $region.Y = 0
    $region.Width = [Math]::Min($size.Width, [double]$limit.Width)
    $region.Height = [Math]::Min($size.Height, [double]$limit.Height)
    $Configuration.SelectedScanRegion = $region
    $selected = $Configuration.SelectedScanRegion
    if ([Math]::Abs($selected.Width - $size.Width) -gt 0.01 -or [Math]::Abs($selected.Height - $size.Height) -gt 0.01) {
        throw 'The Windows driver did not apply the selected paper size. Try another scanner entry.'
    }
}

function Scan-NativePages([string]$DeviceId, [string]$Source, [int]$Requested, [string]$PaperSize = 'auto') {
    if (Test-NativeScannerOperationPending) { throw 'Windows is still releasing the scanner after a timeout. Wait for it to stop before retrying.' }
    $id = $DeviceId.Substring(6)
    $scanner = Wait-ScannerOperation ([Windows.Devices.Scanners.ImageScanner]::FromIdAsync($id)) ([Windows.Devices.Scanners.ImageScanner]) -TimeoutMs 5000 -UsesScanner
    if ($null -eq $scanner) { throw 'Scanner unavailable. Refresh the scanner list.' }
    $scanSources = [Windows.Devices.Scanners.ImageScannerScanSource]
    $scanSource = if ($Source -eq 'feeder') { $scanSources::Feeder } else { $scanSources::Flatbed }
    if (!$scanner.IsScanSourceSupported($scanSource)) { throw "The Windows scan driver does not support $Source scanning for this scanner." }

    $configuration = if ($Source -eq 'feeder') { $scanner.FeederConfiguration } else { $scanner.FlatbedConfiguration }
    if ($Source -eq 'feeder') {
        if ($configuration.Duplex) { $configuration.Duplex = $false }
        $configuration.MaxNumberOfPages = [uint32]$Requested
    }
    $formats = [Windows.Devices.Scanners.ImageScannerFormat]
    $preferredFormats = @($formats::Jpeg, $formats::Png, $formats::Tiff, $formats::DeviceIndependentBitmap)
    $format = $preferredFormats |
        Where-Object { $configuration.IsFormatSupported($_) } | Select-Object -First 1
    if ($null -eq $format) { throw 'This driver does not provide a supported image format for the selected scan source.' }
    $configuration.Format = $format
    $resolution = $configuration.DesiredResolution
    $resolution.DpiX = [single][Math]::Max($configuration.MinResolution.DpiX, [Math]::Min(150, $configuration.MaxResolution.DpiX))
    $resolution.DpiY = [single][Math]::Max($configuration.MinResolution.DpiY, [Math]::Min(150, $configuration.MaxResolution.DpiY))
    $configuration.DesiredResolution = $resolution
    Set-NativeScanPaperSize $configuration $Source $PaperSize
    $temporaryRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
    $temporary = Join-Path $temporaryRoot ('franchise-scan-job-' + [Guid]::NewGuid().ToString('N'))
    [void][IO.Directory]::CreateDirectory($temporary)
    try {
        $folder = Wait-ScannerOperation ([Windows.Storage.StorageFolder]::GetFolderFromPathAsync($temporary)) ([Windows.Storage.StorageFolder])
        $result = Wait-ScannerOperation ($scanner.ScanFilesToFolderAsync($scanSource, $folder)) ([Windows.Devices.Scanners.ImageScannerScanResult]) ([uint32]) -UsesScanner
        $paths = @(Get-NativeScanPaths $result $temporary)
        if ($Source -eq 'feeder' -and !$paths.Count) {
            throw 'The Windows driver returned no pages from the feeder. Reseat the paper and try again. If the scanner already detects the paper, try another scanner entry if available.'
        }
        return Read-ScannerFiles $paths $Requested
    } finally {
        if (Test-NativeScannerOperationPending) { $script:pendingNativeScannerOperation.Directory = $temporary }
        else { Remove-ScannerJobDirectory $temporary }
    }
}
