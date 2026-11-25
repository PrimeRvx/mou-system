@echo off
rem =====================================================================
rem  tools\backup.bat - backup the MOU system (database dump + uploads folder)
rem  Run manually (double-click) or schedule daily with Windows Task Scheduler.
rem  Edit the 6 settings below to match this server before first use.
rem =====================================================================
setlocal

set "XAMPP=C:\xampp"
set "DEST=C:\xampp\backups\mou-system"
set "DB_NAME=mou_bangna"
set "DB_USER=root"
set "DB_PASS="
set "KEEP_DAYS=30"

set "APP=%~dp0.."
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "STAMP=%%i"
if not exist "%DEST%" mkdir "%DEST%"

set "PASSARG="
if not "%DB_PASS%"=="" set "PASSARG=--password=%DB_PASS%"

echo [1/3] Dumping database %DB_NAME% ...
"%XAMPP%\mysql\bin\mysqldump.exe" --user=%DB_USER% %PASSARG% --single-transaction --default-character-set=utf8mb4 --result-file="%DEST%\db_%STAMP%.sql" %DB_NAME%
if errorlevel 1 (
  echo [ERROR] Database dump failed. Check that MySQL is running and DB_USER / DB_PASS are correct.
  exit /b 1
)

echo [2/3] Archiving uploads folder ...
powershell -NoProfile -Command "Compress-Archive -Path '%APP%\uploads\*' -DestinationPath '%DEST%\uploads_%STAMP%.zip' -Force"
if errorlevel 1 (
  echo [ERROR] Could not archive the uploads folder.
  exit /b 1
)

echo [3/3] Removing backups older than %KEEP_DAYS% days ...
forfiles /p "%DEST%" /m db_*.sql /d -%KEEP_DAYS% /c "cmd /c del @path" >nul 2>&1
forfiles /p "%DEST%" /m uploads_*.zip /d -%KEEP_DAYS% /c "cmd /c del @path" >nul 2>&1

echo Done. Backup files are in %DEST%
endlocal
