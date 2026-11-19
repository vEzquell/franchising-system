# WIA Properties.Item(integer) uses a collection position, not a PropertyID.
# Enumerate by PropertyID so sparse driver properties work correctly.
function Find-WiaProperty($properties, [int]$id) {
    foreach ($property in $properties) {
        if ([int]$property.PropertyID -eq $id) { return $property }
    }
    return $null
}
function PropertyValue($properties, [int]$id, $fallback) {
    try {
        $property = Find-WiaProperty $properties $id
        if ($null -ne $property) { return $property.Value }
    } catch {}
    return $fallback
}
function SetProperty($properties, [int]$id, $value, [bool]$required = $false) {
    $property = Find-WiaProperty $properties $id
    if ($null -eq $property) {
        if ($required) { throw "The scanner driver does not expose property $id. Try flatbed mode or check the scanner's WIA driver." }
        return
    }
    try { $property.Value = $value }
    catch {
        if ($required) { throw "The scanner driver could not set property ${id}: $($_.Exception.Message)" }
    }
}

function Get-ScannerPaperDimensions([string]$PaperSize) {
    switch ($PaperSize) {
        'letter' { return @{ Width = 8.5; Height = 11.0 } }
        'a4' { return @{ Width = 210 / 25.4; Height = 297 / 25.4 } }
        'long-bond' { return @{ Width = 8.5; Height = 13.0 } }
        'legal' { return @{ Width = 8.5; Height = 14.0 } }
    }
}

function Set-WiaScanPaperSize($ItemProperties, $HandlingProperties, [string]$PaperSize, [string]$Source) {
    if ($PaperSize -eq 'auto') { return }

    $size = Get-ScannerPaperDimensions $PaperSize
    $horizontalDpi = [int](PropertyValue $ItemProperties 6147 0)
    $verticalDpi = [int](PropertyValue $ItemProperties 6148 0)
    if (!$size -or $horizontalDpi -le 0 -or $verticalDpi -le 0) {
        throw 'The WIA driver could not calculate the scan area for the selected paper size.'
    }
    $width = [int][Math]::Round($size.Width * $horizontalDpi)
    $height = [int][Math]::Round($size.Height * $verticalDpi)

    # Legacy WIA exposes the page type on the device, but scan geometry on the item.
    # Changing the extents also selects a custom page size on modern drivers.
    SetProperty $HandlingProperties 3097 2
    SetProperty $ItemProperties 6156 0
    SetProperty $ItemProperties 6149 0
    SetProperty $ItemProperties 6150 0
    $horizontalExtent = Find-WiaProperty $ItemProperties 6151
    $verticalExtent = Find-WiaProperty $ItemProperties 6152
    $maximumWidth = $horizontalExtent.SubTypeMax
    $maximumHeight = $verticalExtent.SubTypeMax
    $unsupportedSize = "This WIA scan source cannot capture the full $($size.Width.ToString('0.##')) x $($size.Height.ToString('0.##')) inch paper size. Choose a source that supports that size."
    if (!$horizontalExtent -or !$verticalExtent -or ($maximumWidth -and $width -gt $maximumWidth) -or ($maximumHeight -and $height -gt $maximumHeight)) {
        throw $unsupportedSize
    }

    try {
        SetProperty $ItemProperties 6151 $width $true
        SetProperty $ItemProperties 6152 $height $true
        if ($Source -eq 'feeder' -and $maximumWidth -gt $width) {
            $remainingWidth = $maximumWidth - $width
            $registration = PropertyValue $HandlingProperties 3078 0
            if ($registration -eq 1) { SetProperty $ItemProperties 6149 ([int][Math]::Floor($remainingWidth / 2)) $true }
            if ($registration -eq 2) { SetProperty $ItemProperties 6149 $remainingWidth $true }
        }
    } catch { throw "$unsupportedSize $($_.Exception.Message)" }

    if ([Math]::Abs((PropertyValue $ItemProperties 6151 0) - $width) -gt 1 -or [Math]::Abs((PropertyValue $ItemProperties 6152 0) - $height) -gt 1) {
        throw $unsupportedSize
    }
}

function Get-WiaErrorCode($exception) {
    while ($null -ne $exception) {
        $code = ([long]$exception.HResult -band 4294967295).ToString('X8')
        if ($code.StartsWith('8021') -or $code -in @('80070020', '80070021', '800706BA', '800706BE')) { return $code }
        if ($exception.Message -match '(?i)802100[0-9a-f]{2}') { return $Matches[0].ToUpperInvariant() }
        $exception = $exception.InnerException
    }
    return ''
}

function Get-ScannerDriverMessage($Exception) {
    switch (Get-WiaErrorCode $Exception) {
        { $_ -in @('80210006', '8021000D', '80070020', '80070021') } {
            return 'The scanner is busy or locked. Close any other scanning windows, wait for the printer to become idle, and try again.'
        }
        { $_ -in @('80210001', '8021000E') } {
            return 'The Windows scanner driver reported an error. Select the Epson/TWAIN entry if available, or close other scanning apps and try again.'
        }
        { $_ -in @('800706BA', '800706BE') } {
            return 'Windows lost contact with the scanner service. Select the Epson/TWAIN entry if available, or restart Windows and try again.'
        }
        { $_ -in @('80210005', '8021000A') } {
            return 'Windows lost contact with the scanner driver. Check the printer and USB connection. If the printer is still unavailable, restart it and try again.'
        }
    }
    return $Exception.Message
}

function Invoke-WiaTransfer([scriptblock]$Transfer, [int]$MaxAttempts = 20, [int]$DelayMilliseconds = 750, [scriptblock]$PaperReady = $null) {
    $emptyRetries = 0
    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        try { return (& $Transfer) }
        catch {
            $code = Get-WiaErrorCode $_.Exception
            if ($attempt -eq $MaxAttempts) { throw }
            if ($code -eq '80210003') {
                # Only retry a stale empty response when the feeder sensor reports FEED_READY.
                if ($null -eq $PaperReady -or $emptyRetries -ge 3 -or !(& $PaperReady)) { throw }
                $emptyRetries++
            } elseif ($code -ne '80210006') { throw }
            Start-Sleep -Milliseconds $DelayMilliseconds
        }
    }
}
function Release-WiaObject($value) {
    if ($null -ne $value -and [Runtime.InteropServices.Marshal]::IsComObject($value)) {
        [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($value)
    }
}

function Get-WiaSourceItems($Device) {
    $sources = @{}
    for ($index = 1; $index -le $Device.Items.Count; $index++) {
        $item = $Device.Items.Item($index)
        try {
            switch (PropertyValue $item.Properties 4125 '') {
                '{FB607B1F-43F3-488B-855B-FB703EC342A6}' { $sources.flatbed = $index }
                '{FE131934-F84C-42AD-8DA4-6129CDDD7288}' { $sources.feeder = $index }
            }
        } finally { Release-WiaObject $item }
    }
    return $sources
}

function Get-WiaSources($Device, [hashtable]$SourceItems) {
    if ($Device.Items.Count -lt 1) { return }
    $capabilities = [int](PropertyValue $Device.Properties 3086 0)
    $selection = Find-WiaProperty $Device.Properties 3088
    $legacyFlatbed = ($capabilities -band 2) -and (!$SourceItems.Count -or $selection)
    if ($SourceItems.ContainsKey('flatbed') -or $legacyFlatbed -or (!$SourceItems.Count -and !$capabilities)) {
        'flatbed'
    }
    if ($SourceItems.ContainsKey('feeder') -or ($selection -and ($capabilities -band 1))) {
        'feeder'
    }
}
