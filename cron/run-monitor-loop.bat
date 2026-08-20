@echo off
REM Keep this window open for 24/7 monitoring (checks every 5 seconds).
REM For production, use Windows Task Scheduler with cron\run-monitor.bat every 1 minute instead.
title Website Monitoring - Auto Runner
echo Website Monitoring auto runner started. Press Ctrl+C to stop.
:loop
"C:\xampp\php\php.exe" "C:\xampp\htdocs\website_monitoring\cron\monitor.php"
timeout /t 5 /nobreak >nul
goto loop
