@echo off
setlocal
if not defined FIELDFLOW_ARCHIVE_PHP set "FIELDFLOW_ARCHIVE_PHP=C:\xampp\php\php.exe"
"%FIELDFLOW_ARCHIVE_PHP%" "%~dp0unzip.php" %*
exit /b %errorlevel%
