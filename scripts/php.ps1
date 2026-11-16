# Runs only the project-local PHP runtime. Does not alter the system PATH.
$runtime = 'C:\xampp\htdocs\fieldflow\.runtime\php84\php.exe'
if (-not (Test-Path -LiteralPath $runtime)) { throw 'Install project-local PHP first. See docs/WINDOWS.md.' }
Push-Location (Join-Path $PSScriptRoot '..')
$previousIniScan = $env:PHP_INI_SCAN_DIR
$projectIni = Join-Path $PSScriptRoot 'php-conf'
try {
    $env:PHP_INI_SCAN_DIR = if ($previousIniScan) { $previousIniScan + ';' + $projectIni } else { $projectIni }
    & $runtime @args
    $result = $LASTEXITCODE
} finally {
    if ($null -eq $previousIniScan) { Remove-Item Env:PHP_INI_SCAN_DIR -ErrorAction SilentlyContinue } else { $env:PHP_INI_SCAN_DIR = $previousIniScan }
    Pop-Location
}
exit $result
