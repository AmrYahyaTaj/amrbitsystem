$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$projectRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$backupRoot = 'C:\Users\amrya\Documents\Amrbit Backups'
[IO.Directory]::CreateDirectory($backupRoot) | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
$snapshotPath = Join-Path $projectRoot ('.runtime\snapshot-' + $stamp + '.sqlite')
$env:FIELDFLOW_SNAPSHOT_PATH = $snapshotPath
try { & "$PSScriptRoot\php.ps1" "$PSScriptRoot\snapshot.php"; if ($LASTEXITCODE -ne 0) { throw 'Database snapshot failed.' } }
finally { Remove-Item Env:FIELDFLOW_SNAPSHOT_PATH -ErrorAction SilentlyContinue }
$archivePath = Join-Path $backupRoot ('fieldflow-next-' + $stamp + '.zip')
$stream = [IO.File]::Open($archivePath,[IO.FileMode]::CreateNew)
$archive = [IO.Compression.ZipArchive]::new($stream,[IO.Compression.ZipArchiveMode]::Create,$false)
try {
  foreach ($file in [IO.Directory]::EnumerateFiles($projectRoot,'*',[IO.SearchOption]::AllDirectories)) {
    $entry = [IO.Path]::GetRelativePath($projectRoot,$file).Replace('\','/')
    if ($entry -match '^(\.runtime|node_modules|\.git)/|^storage/(framework|logs)/|^database/.*\.sqlite.*$') { continue }
    [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,$file,$entry,[IO.Compression.CompressionLevel]::Fastest) | Out-Null
  }
  [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,$snapshotPath,'database/database.sqlite',[IO.Compression.CompressionLevel]::Fastest) | Out-Null
  $access = Join-Path $projectRoot '.runtime\demo-access.txt'
  if (Test-Path -LiteralPath $access) { [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,$access,'.runtime/demo-access.txt',[IO.Compression.CompressionLevel]::Fastest) | Out-Null }
} finally { $archive.Dispose();$stream.Dispose() }
$hash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash
[IO.File]::WriteAllText($archivePath+'.sha256',$hash+'  '+[IO.Path]::GetFileName($archivePath))
Write-Output $archivePath
