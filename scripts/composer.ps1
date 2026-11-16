$runtime = 'C:\xampp\htdocs\fieldflow\.runtime\php84\php.exe'
$composerPhar = if ($env:FIELDFLOW_COMPOSER_PHAR) { $env:FIELDFLOW_COMPOSER_PHAR } else { 'C:\composer\composer.phar' }
if (-not (Test-Path -LiteralPath $runtime)) { throw 'Project-local PHP is missing. See docs/WINDOWS.md.' }
if (-not (Test-Path -LiteralPath $composerPhar)) { throw 'Set FIELDFLOW_COMPOSER_PHAR to your Composer PHAR path.' }
Push-Location (Join-Path $PSScriptRoot '..')
$previousPath = $env:PATH
try {
    $archiveTools = Join-Path $PSScriptRoot 'archive'
    $env:PATH = (Split-Path $runtime) + [IO.Path]::PathSeparator + $archiveTools + [IO.Path]::PathSeparator + $previousPath
    & $runtime $composerPhar @args
    $result = $LASTEXITCODE
} finally { $env:PATH = $previousPath; Pop-Location }
exit $result
