$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
& "$PSScriptRoot\php.ps1" artisan serve --host=127.0.0.1 --port=8090 --no-reload
