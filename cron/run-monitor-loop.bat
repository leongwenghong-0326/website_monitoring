@echo off
REM Continuous monitoring loop for local XAMPP (runs every 60 seconds).
REM For production, use Windows Task Scheduler with run-monitor.bat instead.

set PHP="C:\xampp\php\php.exe"
set SCRIPT="C:\xampp\htdocs\website_monitoring\cron\monitor.php"

:loop
%PHP% %SCRIPT%
timeout /t 60 /nobreak >nul
goto loop
