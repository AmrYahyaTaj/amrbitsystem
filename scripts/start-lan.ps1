param([string]$ListenAddress = '', [ValidateRange(1024,65535)][int]$Port = 8090)
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$network = & "$PSScriptRoot\lan-address.ps1" -ListenAddress $ListenAddress
$url = 'http://' + $network.Address + ':' + $Port
$listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Where-Object { $_.LocalAddress -eq $network.Address -or $_.LocalAddress -eq '0.0.0.0' -or $_.LocalAddress -eq '::' }
if ($listener) { throw "The selected LAN address/port is already in use. Inspect it before starting another process: $url" }
Set-Location -LiteralPath $projectRoot
Write-Host "amrbit LAN: $url/login" -ForegroundColor Green
Write-Host 'Use another device on the same Wi-Fi. Keep this window and the computer running. Ctrl+C stops sharing.'
Write-Host 'GPS on another device requires HTTPS; this HTTP address is for local UI testing.'
$previousDebug = $env:APP_DEBUG
$previousUrl = $env:APP_URL
try {
  $env:APP_DEBUG = 'false'
  $env:APP_URL = $url
  & "$PSScriptRoot\php.ps1" artisan serve --host=$($network.Address) --port=$Port --no-reload
} finally {
  if ($null -eq $previousDebug) { Remove-Item Env:APP_DEBUG -ErrorAction SilentlyContinue } else { $env:APP_DEBUG = $previousDebug }
  if ($null -eq $previousUrl) { Remove-Item Env:APP_URL -ErrorAction SilentlyContinue } else { $env:APP_URL = $previousUrl }
}
