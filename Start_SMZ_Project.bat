@echo off
title Safdar Mobile Store (SMS) - Local XAMPP Launcher
echo ========================================================
echo   Safdar Mobile Store (SMS) - Local POS & Storefront
echo ========================================================
echo.
echo [1/3] Checking XAMPP Apache and MySQL services...

rem Start XAMPP Apache if not running
powershell -Command "if (!(Get-Process -Name httpd -ErrorAction SilentlyContinue)) { Start-Process 'C:\xampp\apache_start.bat' -WindowStyle Hidden; Write-Host '  - Apache Started' } else { Write-Host '  - Apache is already running' }"

rem Start XAMPP MySQL if not running
powershell -Command "if (!(Get-Process -Name mysqld -ErrorAction SilentlyContinue)) { Start-Process 'C:\xampp\mysql_start.bat' -WindowStyle Hidden; Write-Host '  - MySQL is already running' } else { Write-Host '  - MySQL is already running' }"

echo [2/3] Verifying database connection...
timeout /t 1 >nul

echo [3/3] Opening Safdar POS Admin & Storefront in browser...
start http://localhost/sms/secure-portal.php
start http://localhost/sms/

echo.
echo ========================================================
echo   Storefront URL:   http://localhost/sms/
echo   Admin POS Portal: http://localhost/sms/secure-portal.php
echo ========================================================
echo.
timeout /t 3 >nul
exit
