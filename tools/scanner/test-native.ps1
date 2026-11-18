$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
. (Join-Path $PSScriptRoot 'properties.ps1')
. (Join-Path $PSScriptRoot 'native.ps1')
. (Join-Path $PSScriptRoot 'twain.ps1')
Initialize-NativeScanner
$feederConfiguration = [pscustomobject]@{
    AutoDetectPageSize = $true
    AutoCroppingMode = [Windows.Devices.Scanners.ImageScannerAutoCroppingMode]::SingleRegion
    PageOrientation = $null
    PageSize = [Windows.Graphics.Printing.PrintMediaSize]::IsoA4
    MaxScanArea = [pscustomobject]@{ Width = 8.5; Height = 14 }
    SelectedScanRegion = [pscustomobject]@{ X = 0; Y = 0; Width = 8.267; Height = 11.69 }
}
$feederConfiguration | Add-Member ScriptMethod IsPageSizeSupported { param($size, $orientation) $size.ToString() -eq 'NorthAmericaLegal' }
Set-NativeScanPaperSize $feederConfiguration 'feeder' 'long-bond'
$region = $feederConfiguration.SelectedScanRegion
if ($feederConfiguration.PageSize -ne 'NorthAmericaLegal' -or $region.Width -ne 8.5 -or $region.Height -ne 13 -or $feederConfiguration.AutoDetectPageSize -or $feederConfiguration.AutoCroppingMode -ne 'Disabled') {
    throw 'WinRT must request an exact Long Bond region on a Legal-capable scanner.'
}
Write-Output 'Passed WinRT exact Long Bond region with Legal feeder fallback.'

$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('scanner-native-test-' + [Guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory($testRoot)
try {
    $previousTwainConsole = $twainConsole
    $twainConsole = Join-Path $PSHOME 'powershell.exe'
    $processIdsPath = Join-Path $testRoot 'twain-process-ids.txt'
    $testConsoleScript = @'
$worker = Start-Process (Join-Path $PSHOME 'powershell.exe') -WindowStyle Hidden -PassThru -ArgumentList '-NoProfile', '-NonInteractive', '-Command', 'Start-Sleep -Seconds 15'
[IO.File]::WriteAllLines('__PROCESS_IDS_PATH__', [string[]]@($PID, $worker.Id))
Start-Sleep -Seconds 15
'@
    $testConsoleScript = $testConsoleScript.Replace('__PROCESS_IDS_PATH__', $processIdsPath.Replace("'", "''"))
    $encodedTestConsole = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($testConsoleScript))
    try {
        try {
            $null = Invoke-TwainConsole @('-NoProfile', '-NonInteractive', '-EncodedCommand', $encodedTestConsole) 3000
            throw 'Expected TWAIN process timeout.'
        } catch {
            if (!$_.Exception.Message.Contains('TWAIN scanner timed out')) { throw }
        }
        $testProcessIds = [int[]][IO.File]::ReadAllLines($processIdsPath)
        foreach ($testProcess in Get-Process -Id $testProcessIds -ErrorAction SilentlyContinue) {
            if (!$testProcess.WaitForExit(2000)) { throw "TWAIN process $($testProcess.Id) survived its job timeout." }
        }
        $testOutput = Invoke-TwainConsole @('-NoProfile', '-NonInteractive', '-Command', "Write-Output 'EPSON L5390 Series'") 3000
        if ($testOutput.Trim() -ne 'EPSON L5390 Series') { throw 'TWAIN console output or argument quoting changed.' }
        Write-Output 'Passed TWAIN console output and process-tree cleanup on timeout.'
    } finally { $twainConsole = $previousTwainConsole }

    foreach ($number in @(10, 2, 1, 4, 3, 6, 5)) {
        $path = Join-Path $testRoot "page-$number.bmp"
        $bitmap = New-Object Drawing.Bitmap ($number + 16), 16
        try { $bitmap.Save($path, [Drawing.Imaging.ImageFormat]::Bmp) } finally { $bitmap.Dispose() }
    }
    $paths = @(Get-NativeScanPaths @{ ScannedFiles = @() } $testRoot)
    $expected = @('page-1.bmp', 'page-2.bmp', 'page-3.bmp', 'page-4.bmp', 'page-5.bmp', 'page-6.bmp', 'page-10.bmp')
    if ((($paths | ForEach-Object { [IO.Path]::GetFileName($_) }) -join ',') -ne ($expected -join ',')) { throw 'Folder scan results are out of order.' }
    $reported = @(Get-NativeScanPaths @{ ScannedFiles = @(@{ Path = $paths[-1] }, @{ Path = $paths[0] }) } $testRoot)
    if ($reported.Count -ne 2 -or $reported[0] -ne $paths[-1]) { throw 'Reported scan order must be preserved.' }
    $pages = New-Object 'System.Collections.Generic.List[object]'
    $total = 0
    foreach ($path in $paths) {
        $bitmap = [Drawing.Image]::FromFile($path)
        try { Add-ScannerImagePages $bitmap $pages ([ref]$total) 7 } finally { $bitmap.Dispose() }
    }
    $batch = Get-ScanBatchResult ($pages.ToArray()) 7
    if ($batch.pages.Count -ne 7 -or !$batch.complete -or $batch.requested_pages -ne 7) { throw 'Seven-page batch lost pages.' }
    foreach ($page in $batch.pages) {
        $bytes = [Convert]::FromBase64String($page.data)
        if ($bytes.Length -eq 0 -or $bytes[0] -ne 255 -or $bytes[1] -ne 216) { throw 'Invalid JPEG conversion.' }
    }
    try {
        $null = Get-ScanBatchResult @($pages[0]) 7
        throw 'Expected incomplete batch rejection'
    } catch {
        if (!$_.Exception.Message.Contains('1 of 7')) { throw }
    }

    $twainJob = @{ Source = $null; PageSize = $null; PageCount = 2; EmptyError = $false }
    function Invoke-TwainConsole([string[]]$Arguments, [int]$TimeoutMs) {
        $twainJob.Source = $Arguments[[Array]::IndexOf($Arguments, '--source') + 1]
        $twainJob.PageSize = $Arguments[[Array]::IndexOf($Arguments, '--pagesize') + 1]
        if ($twainJob.EmptyError) { throw 'TWAIN scanner error: No scanned pages to export.' }
        $output = $Arguments[[Array]::IndexOf($Arguments, '-o') + 1]
        for ($index = 1; $index -le $twainJob.PageCount; $index++) {
            $bitmap = [Drawing.Image]::FromFile($paths[$index - 1])
            try { $bitmap.Save($output.Replace('$(nnnn)', $index.ToString('D4')), [Drawing.Imaging.ImageFormat]::Jpeg) }
            finally { $bitmap.Dispose() }
        }
    }
    $twainScanner = @{ name = 'Test scanner' }
    $batch = Scan-TwainPages $twainScanner 'feeder' 2 'long-bond'
    if ($twainJob.Source -ne 'feeder' -or $batch.scanned_pages -ne 2) { throw 'TWAIN did not return both feeder pages.' }
    if ($twainJob.PageSize -ne '8.5x13in') { throw 'TWAIN did not request the full long bond paper size.' }
    try {
        $null = Scan-TwainPages $twainScanner 'feeder' 1
        throw 'Expected excess feeder page rejection'
    } catch {
        if (!$_.Exception.Message.Contains('more pages than requested')) { throw }
    }
    $twainJob.PageCount = 1
    $batch = Scan-TwainPages $twainScanner 'flatbed' 1
    if ($twainJob.Source -ne 'glass' -or $batch.scanned_pages -ne 1) { throw 'TWAIN did not return the flatbed page.' }
    if ($twainJob.PageSize -ne 'letter') { throw 'TWAIN did not preserve the default Letter size.' }
    $twainJob.EmptyError = $true
    try {
        $null = Scan-TwainPages $twainScanner 'feeder' 1
        throw 'Expected empty feeder error'
    } catch {
        if (!$_.Exception.Message.Contains('scanner detects the paper in the feeder')) { throw }
    }
    Write-Output 'Passed TWAIN feeder, long bond and default paper sizes, extra page rejection, flatbed, and empty feeder checks.'

    # Real Windows async operation, without driving scanner hardware.
    $folder = Wait-ScannerOperation ([Windows.Storage.StorageFolder]::GetFolderFromPathAsync($testRoot)) ([Windows.Storage.StorageFolder])
    if ($folder.Path -ne $testRoot) { throw 'Windows async operation returned the wrong folder.' }
    Write-Output 'Passed bitmap folder recovery, seven-page JPEG conversion, incomplete batch rejection, and Windows async operation checks.'

    Add-Type -TypeDefinition @'
using System;
using System.Threading;
using System.Threading.Tasks;

public static class ScannerTimeoutTestOperation
{
    public static volatile bool CancellationRequested;
    public static readonly TaskCompletionSource<string> Result = new TaskCompletionSource<string>();
    public static readonly Func<CancellationToken, IProgress<uint>, Task<string>> Start = (token, progress) =>
    {
        token.Register(() => CancellationRequested = true);
        return Result.Task;
    };
}
'@
    $run = [System.Runtime.InteropServices.WindowsRuntime.AsyncInfo].GetMethods() | Where-Object {
        $_.Name -eq 'Run' -and $_.GetGenericArguments().Count -eq 2
    } | Select-Object -First 1
    $operation = $run.MakeGenericMethod([Type[]]@([string], [uint32])).Invoke($null, @([ScannerTimeoutTestOperation]::Start))
    try {
        $null = Wait-ScannerOperation $operation ([string]) ([uint32]) -TimeoutMs 0 -UsesScanner
        throw 'Expected Windows Runtime operation timeout'
    } catch [TimeoutException] {
        if (!$_.Exception.Message.Contains('Wait for the scanner to stop')) { throw }
    }
    if (![ScannerTimeoutTestOperation]::CancellationRequested -or !(Test-NativeScannerOperationPending)) {
        throw 'The timeout must request WinRT cancellation and keep the scanner busy until the operation finishes.'
    }
    $canceledScanDirectory = Join-Path $testRoot 'canceled-scan'
    [void][IO.Directory]::CreateDirectory($canceledScanDirectory)
    try {
        $script:pendingNativeScannerOperation.Directory = $canceledScanDirectory
        [ScannerTimeoutTestOperation]::Result.SetResult('finished')
        $deadline = [DateTime]::UtcNow.AddSeconds(2)
        while ((Test-NativeScannerOperationPending) -and [DateTime]::UtcNow -lt $deadline) { Start-Sleep -Milliseconds 10 }
        if (Test-NativeScannerOperationPending) { throw 'The scanner must become available when a timed-out operation finishes.' }
        if (Test-Path -LiteralPath $canceledScanDirectory) { throw 'Canceled scan files must be cleaned up after the driver finishes.' }
    } finally {
        if (Test-Path -LiteralPath $canceledScanDirectory) { Remove-ScannerJobDirectory $canceledScanDirectory }
    }
    Write-Output 'Passed Windows Runtime cancellation, pending scanner, and deferred cleanup checks.'
} finally {
    foreach ($file in Get-ChildItem -LiteralPath $testRoot -File) { Remove-Item -LiteralPath $file.FullName }
    [IO.Directory]::Delete($testRoot, $false)
}
