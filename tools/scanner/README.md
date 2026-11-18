# Windows scanner bridge

The local bridge supports the flatbed and automatic document feeder (ADF) through Windows native, WIA, and TWAIN drivers. Install the scanner manufacturer's driver on the scanning PC first.

From the project directory on the scanning PC:

```powershell
powershell.exe -STA -NoProfile -ExecutionPolicy Bypass -File tools/scanner/bridge.ps1 -AllowedOrigin "http://localhost:8000"
```

Use the exact origin from your browser address bar (scheme, host, and port; no path). The Scan dialog shows the command for the current origin. Leave the bridge running while scanning. Close its terminal to stop it.

The bridge listens only on `127.0.0.1:8765`. It accepts requests only from the configured app origin and pairs that origin with a session token. Browser local-network permission may need to be allowed. It does not write scans into the application database or permanent storage: pages return to the browser and are submitted with the final form.

In the attachment form, enter the document name and type and click **Scan documents**. Choose a scanner and paper source. For the document feeder, load only the pages for the current document and enter the number of sheets. Each scan creates one PDF attachment. The feeder accepts one to 30 single-sided pages per job, subject to the scanner's capacity and attachment size limit. Flatbed mode scans one page at a time. You can rename or remove the attachment before submission. Manual uploads accept PDF, JPG, and PNG files.

Set **Paper size** to match the sheet before scanning. For Philippine long bond paper, select **Long Bond (8.5 × 13 in)** and use the document feeder on the Epson L5390. The feeder holds up to 10 sheets of that size. [Epson ADF specifications](https://download4.epson.biz/sec_pubs/l5390_series/useg/en/GUID-AB4B668D-7019-4EA3-93E5-448B4D5298D2.htm)

Attachments are limited to 5 MB each and 6 MB combined. The bridge sends JPEG page data to the browser, which assembles the PDF. Scans are acquired at 150 DPI where supported. Review readability before submitting.

If Windows reports Access denied when starting the HTTP listener, an administrator can grant this specific local URL to your Windows account once:

```powershell
netsh http add urlacl url=http://127.0.0.1:8765/ user="DOMAIN\User"
```

Replace `DOMAIN\User` with the scanning user's actual account. This command is only needed if listener startup is denied; it does not need to be run for every scan. Do not expose the bridge on a network address or allow arbitrary website origins.

## TWAIN fallback

Choose the Epson/TWAIN entry if the Windows native scanner entry fails. The bridge finds NAPS2 Console in the standard NAPS2 installation or in the portable installation at `%LOCALAPPDATA%\NAPS2-ScannerBridge\App`.

On the Epson L5390, check for the ADF icon on the printer's **Copy** screen. A missing icon means the printer has yet to detect a sheet in the feeder. [Epson feeder troubleshooting](https://download4.epson.biz/sec_pubs/l5390_series/useg/en/GUID-99DE1097-F31E-45CC-AE1F-2C3CC705C40F.htm)
