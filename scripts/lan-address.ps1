param([string]$ListenAddress = '')
$ErrorActionPreference = 'Stop'
$candidates = @(Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.IPv4Address } | ForEach-Object {
  $configuration = $_
  foreach ($address in @($configuration.IPv4Address)) {
    if ($address.IPAddress -match '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)') {
      [pscustomobject]@{ Address = $address.IPAddress; PrefixLength = $address.PrefixLength; InterfaceAlias = $configuration.InterfaceAlias; InterfaceIndex = $configuration.InterfaceIndex }
    }
  }
})
if ($ListenAddress) { $candidates = @($candidates | Where-Object Address -eq $ListenAddress) }
if ($candidates.Count -ne 1) { throw 'Select one active private LAN address using -ListenAddress. No public or wildcard address is allowed.' }
$candidates[0]
