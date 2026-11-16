param([string]$ListenAddress = '', [ValidateRange(1024,65535)][int]$Port = 8090)
$ErrorActionPreference = 'Stop'
$network = & "$PSScriptRoot\lan-address.ps1" -ListenAddress $ListenAddress
$principal = [Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Administrator access is required. Right-click enable-amrbit-lan.cmd and select Run as administrator.' }
$runtime = 'C:\xampp\htdocs\fieldflow\.runtime\php84\php.exe'
if (-not (Test-Path -LiteralPath $runtime)) { throw 'The project PHP runtime is missing.' }
$profileName = (Get-NetConnectionProfile -InterfaceIndex $network.InterfaceIndex).NetworkCategory.ToString()
if ($profileName -eq 'DomainAuthenticated') { $profileName = 'Domain' }
$ruleName = 'amrbit-LAN-' + $Port
$existing = Get-NetFirewallRule -Name $ruleName -ErrorAction SilentlyContinue
if ($existing) {
  $address = $existing | Get-NetFirewallAddressFilter
  $portFilter = $existing | Get-NetFirewallPortFilter
  $program = $existing | Get-NetFirewallApplicationFilter
  if ($existing.Action.ToString() -ne 'Allow' -or $existing.Direction.ToString() -ne 'Inbound' -or $existing.Enabled.ToString() -ne 'True' -or $portFilter.LocalPort -ne [string]$Port -or $portFilter.Protocol -ne 'TCP' -or $program.Program -ne $runtime -or @($address.LocalAddress) -notcontains $network.Address -or @($address.RemoteAddress) -notcontains 'LocalSubnet') {
    throw 'A different rule with this name already exists. Review it before making changes.'
  }
  Write-Host 'The scoped LAN firewall rule already exists.'
} else {
  New-NetFirewallRule -Name $ruleName -DisplayName "amrbit LAN $Port" -Description 'Local testing only; project PHP, selected Wi-Fi/LAN interface and local subnet.' -Direction Inbound -Action Allow -Enabled True -Protocol TCP -LocalPort $Port -Program $runtime -LocalAddress $network.Address -RemoteAddress LocalSubnet -InterfaceAlias $network.InterfaceAlias -Profile $profileName -EdgeTraversalPolicy Block | Out-Null
}
Write-Host ('Firewall ready for http://' + $network.Address + ':' + $Port + '/login') -ForegroundColor Green
