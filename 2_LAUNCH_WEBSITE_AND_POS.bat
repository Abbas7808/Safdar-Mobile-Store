@echo off
setlocal enabledelayedexpansion
title Safdar Mobile Store (SMS) - Launcher
color 0A

echo =========================================================================
echo       SAFDAR MOBILE STORE (SMS) - LAUNCHING LOCAL POS & STOREFRONT
echo =========================================================================
echo.

rem 1. Ensure XAMPP Services are active
powershell -Command "if (!(Get-Process -Name httpd -ErrorAction SilentlyContinue)) { if (Test-Path 'C:\xampp\apache_start.bat') { Start-Process 'C:\xampp\apache_start.bat' -WindowStyle Hidden; Write-Host '  [+] Apache started' -ForegroundColor Green } } else { Write-Host '  [OK] Apache is running' -ForegroundColor Green }"

powershell -Command "if (!(Get-Process -Name mysqld -ErrorAction SilentlyContinue)) { if (Test-Path 'C:\xampp\mysql_start.bat') { Start-Process 'C:\xampp\mysql_start.bat' -WindowStyle Hidden; Write-Host '  [+] MySQL started' -ForegroundColor Green; Start-Sleep -Seconds 1 } } else { Write-Host '  [OK] MySQL is running' -ForegroundColor Green }"

rem 2. Determine Folder Name if inside htdocs
set "CURRENT_DIR=%~dp0"
set "CURRENT_DIR=%CURRENT_DIR:~0,-1%"
for %%f in ("%CURRENT_DIR%") do set "FOLDER_NAME=%%~nxf"

set "BASE_URL=http://localhost/sms"

rem If folder name is not sms, adjust base url
if /i not "%FOLDER_NAME%"=="htdocs" (
    set "BASE_URL=http://localhost/%FOLDER_NAME%"
)

echo.
echo [INFO] Opening Customer Storefront: %BASE_URL%/
start "" "%BASE_URL%/"

timeout /t 1 >nul

echo [INFO] Opening Admin & POS Portal:  %BASE_URL%/secure-portal.php
start "" "%BASE_URL%/secure-portal.php"

echo.
echo =========================================================================
echo   Storefront URL:   %BASE_URL%/
echo   Admin POS Portal: %BASE_URL%/secure-portal.php
echo.
echo   Default Credentials:
echo     Admin    : admin  /  smz1234  (or admin@admin.com / SafdarAdmin@2026!)
echo     Salesman : salesman  /  sale1234  (or salesman@salesman.com / SalesmanPos#2026!)
echo =========================================================================
echo.
timeout /t 4 >nul
exit
