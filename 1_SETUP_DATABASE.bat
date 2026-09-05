@echo off
setlocal enabledelayedexpansion
title Safdar Mobile Store (SMS) - Database & Environment Setup
color 0B

echo =========================================================================
echo       SAFDAR MOBILE STORE (SMS) - AUTOMATED DATABASE & LOCAL SETUP
echo =========================================================================
echo.

rem 1. Check & Start XAMPP Apache & MySQL Services
echo [1/3] Checking XAMPP Apache and MySQL services...

powershell -Command "if (!(Get-Process -Name httpd -ErrorAction SilentlyContinue)) { if (Test-Path 'C:\xampp\apache_start.bat') { Start-Process 'C:\xampp\apache_start.bat' -WindowStyle Hidden; Write-Host '  [+] Apache service started successfully' -ForegroundColor Green } } else { Write-Host '  [OK] Apache is already running' -ForegroundColor Green }"

powershell -Command "if (!(Get-Process -Name mysqld -ErrorAction SilentlyContinue)) { if (Test-Path 'C:\xampp\mysql_start.bat') { Start-Process 'C:\xampp\mysql_start.bat' -WindowStyle Hidden; Write-Host '  [+] MySQL service started successfully' -ForegroundColor Green; Start-Sleep -Seconds 2 } } else { Write-Host '  [OK] MySQL is already running' -ForegroundColor Green }"

echo.
echo [2/3] Locating PHP CLI executable...

set "PHP_CMD="
if exist "C:\xampp\php\php.exe" (
    set "PHP_CMD=C:\xampp\php\php.exe"
) else if exist "D:\xampp\php\php.exe" (
    set "PHP_CMD=D:\xampp\php\php.exe"
) else if exist "E:\xampp\php\php.exe" (
    set "PHP_CMD=E:\xampp\php\php.exe"
) else (
    where php >nul 2>nul
    if !errorlevel! equ 0 (
        set "PHP_CMD=php"
    )
)

if "%PHP_CMD%"=="" (
    color 0C
    echo [ERROR] PHP executable was not found in standard XAMPP paths.
    echo Please ensure XAMPP is installed at C:\xampp or add PHP to your system PATH.
    echo.
    pause
    exit /b 1
)

echo   [OK] Found PHP at: %PHP_CMD%
echo.

rem 2. Run Database Import and Sync
echo [3/3] Importing live database dump and synchronizing local data engine...
echo.
"%PHP_CMD%" "%~dp0database\import_db.php"

if %errorlevel% equ 0 (
    echo.
    color 0A
    echo =========================================================================
    echo   [SUCCESS] SETUP COMPLETED SUCCESSFULLY!
    echo.
    echo   Customer Storefront : http://localhost/sms/
    echo   Admin / POS Portal  : http://localhost/sms/secure-portal.php
    echo.
    echo   Default Logins:
    echo     - Super Admin : admin  /  smz1234  (or admin@admin.com / SafdarAdmin@2026!)
    echo     - Salesman    : salesman  /  sale1234  (or salesman@salesman.com / SalesmanPos#2026!)
    echo =========================================================================
) else (
    echo.
    color 0E
    echo [NOTICE] Setup finished with code %errorlevel%. Please verify your XAMPP MySQL service status.
)

echo.
echo Press any key to launch the website and POS...
pause >nul

start "" "%~dp02_LAUNCH_WEBSITE_AND_POS.bat"
