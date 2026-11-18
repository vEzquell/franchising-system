function New-TwainProcessJob {
    if (!('FranchiseScanner.TwainProcessJob' -as [type])) {
        Add-Type -ErrorAction Stop -TypeDefinition @'
using System;
using System.ComponentModel;
using System.Diagnostics;
using System.Runtime.InteropServices;
using Microsoft.Win32.SafeHandles;

namespace FranchiseScanner
{
    public static class TwainProcessJob
    {
        private const int ExtendedLimitInformation = 9;
        private const uint KillOnJobClose = 0x00002000;

        [StructLayout(LayoutKind.Sequential)]
        private struct BasicLimits
        {
            public long PerProcessUserTimeLimit;
            public long PerJobUserTimeLimit;
            public uint LimitFlags;
            public UIntPtr MinimumWorkingSetSize;
            public UIntPtr MaximumWorkingSetSize;
            public uint ActiveProcessLimit;
            public UIntPtr Affinity;
            public uint PriorityClass;
            public uint SchedulingClass;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct IoCounters
        {
            public ulong ReadOperationCount;
            public ulong WriteOperationCount;
            public ulong OtherOperationCount;
            public ulong ReadTransferCount;
            public ulong WriteTransferCount;
            public ulong OtherTransferCount;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct ExtendedLimits
        {
            public BasicLimits BasicLimitInformation;
            public IoCounters IoInfo;
            public UIntPtr ProcessMemoryLimit;
            public UIntPtr JobMemoryLimit;
            public UIntPtr PeakProcessMemoryUsed;
            public UIntPtr PeakJobMemoryUsed;
        }

        [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
        private static extern SafeFileHandle CreateJobObject(IntPtr attributes, string name);

        [DllImport("kernel32.dll", SetLastError = true)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool SetInformationJobObject(SafeFileHandle job, int informationClass, ref ExtendedLimits limits, int length);

        [DllImport("kernel32.dll", SetLastError = true)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool AssignProcessToJobObject(SafeFileHandle job, IntPtr process);

        public static SafeFileHandle Create()
        {
            var job = CreateJobObject(IntPtr.Zero, null);
            if (job.IsInvalid)
            {
                int error = Marshal.GetLastWin32Error();
                job.Dispose();
                throw new Win32Exception(error);
            }

            var limits = new ExtendedLimits();
            limits.BasicLimitInformation.LimitFlags = KillOnJobClose;
            if (!SetInformationJobObject(job, ExtendedLimitInformation, ref limits, Marshal.SizeOf(typeof(ExtendedLimits))))
            {
                int error = Marshal.GetLastWin32Error();
                job.Dispose();
                throw new Win32Exception(error);
            }
            return job;
        }

        public static void Attach(SafeFileHandle job, Process process)
        {
            if (!AssignProcessToJobObject(job, process.Handle))
                throw new Win32Exception(Marshal.GetLastWin32Error());
        }
    }
}
'@
    }
    return [FranchiseScanner.TwainProcessJob]::Create()
}

function Invoke-TwainConsole([string[]]$Arguments, [int]$TimeoutMs) {
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $twainConsole
    $start.Arguments = ($Arguments | ForEach-Object { '"' + $_ + '"' }) -join ' '
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $job = New-TwainProcessJob
    $process = $null
    $attached = $false
    try {
        $process = [Diagnostics.Process]::Start($start)
        try {
            [FranchiseScanner.TwainProcessJob]::Attach($job, $process)
            $attached = $true
        }
        catch {
            if (!$process.HasExited) { throw }
        }

        $output = $process.StandardOutput.ReadToEndAsync()
        $errors = $process.StandardError.ReadToEndAsync()
        if (!$process.WaitForExit($TimeoutMs)) {
            throw 'The TWAIN scanner timed out. Wait for the scanner to stop before retrying.'
        }

        # A worker can still hold the scanner and output pipes after the console exits.
        $job.Dispose()
        if (![Threading.Tasks.Task]::WaitAll([Threading.Tasks.Task[]]@($output, $errors), 5000)) {
            throw 'The TWAIN scanner did not finish closing. Wait for it to stop before retrying.'
        }
        if ($process.ExitCode -ne 0) {
            $detail = ($errors.GetAwaiter().GetResult() + ' ' + $output.GetAwaiter().GetResult()).Trim()
            if (!$detail) { throw 'The TWAIN scanner exited without reporting the cause. Check that the scanner is ready before retrying.' }
            if ($detail.Length -gt 300) { $detail = $detail.Substring(0, 300) }
            throw "TWAIN scanner error: $detail"
        }
        return $output.GetAwaiter().GetResult()
    } finally {
        $job.Dispose()
        if ($process) {
            try {
                if (!$attached -and !$process.HasExited) { $process.Kill() }
                [void]$process.WaitForExit(2000)
            } finally { $process.Dispose() }
        }
    }
}

function Get-TwainScanners {
    if (!$twainConsole) { return }
    $devices = Invoke-TwainConsole @('--listdevices', '--driver', 'twain') 5000
    foreach ($line in ($devices -split '\r?\n')) {
        $name = $line.Trim()
        if (!$name -or $name -match '["\x00-\x1F]') { continue }
        @{ id = "twain:$name"; name = $name; backend = 'twain'; sources = @('flatbed', 'feeder') }
    }
}

function Scan-TwainPages($Device, [string]$Source, [int]$Requested, [string]$PaperSize = 'auto') {
    $twainSource = if ($Source -eq 'feeder') { 'feeder' } else { 'glass' }
    $twainPageSize = switch ($PaperSize) {
        'auto' { 'letter' }
        'long-bond' { '8.5x13in' }
        default { $PaperSize }
    }
    $emptyFeederError = 'TWAIN returned no pages. Check that the scanner is ready and that the scanner detects the paper in the feeder before trying again.'
    $directory = Join-Path ([IO.Path]::GetTempPath()) ('franchise-twain-job-' + [Guid]::NewGuid().ToString('N'))
    [void][IO.Directory]::CreateDirectory($directory)
    try {
        $output = Join-Path $directory 'page-$(nnnn).jpg'
        try {
            $null = Invoke-TwainConsole @(
                '-o', $output, '--noprofile', '--driver', 'twain', '--device', $Device.name,
                '--source', $twainSource, '--dpi', '150', '--pagesize', $twainPageSize
            ) 540000
        }
        catch {
            if ($_.Exception.Message -match 'E1460-B305|scanner (?:is )?(?:busy|in use)|\bMaxConnections\b') {
                throw 'The TWAIN scanner is busy or unavailable. Close other scanning windows and wait for the scanner to be ready before retrying.'
            }
            if ($Source -eq 'feeder' -and $_.Exception.Message -match '^TWAIN scanner error:\s*No scanned pages to export\.?\s*$') {
                throw $emptyFeederError
            }
            throw
        }
        $paths = @(Get-ChildItem -LiteralPath $directory -Filter '*.jpg' -File | Sort-Object Name | ForEach-Object FullName)
        if ($Source -eq 'feeder' -and !$paths.Count) {
            throw $emptyFeederError
        }
        return Read-ScannerFiles $paths $Requested
    } finally {
        Remove-ScannerJobDirectory $directory
    }
}
