
@echo off
cd /d "C:\wamp64\www\franchising-system"

powershell.exe -STA -NoProfile -ExecutionPolicy Bypass -File "C:\wamp64\www\franchising-system\tools\scanner\bridge.ps1" -AllowedOrigin "http://franchising.com"
