Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
& "$PSScriptRoot\php.ps1" artisan migrate --force
if ($LASTEXITCODE -ne 0) {exit $LASTEXITCODE}
& "$PSScriptRoot\php.ps1" artisan db:seed --class=OperationsDemoSeeder --force
if ($LASTEXITCODE -ne 0) {exit $LASTEXITCODE}
& "$PSScriptRoot\php.ps1" artisan db:seed --class=CreditDemoSeeder --force
