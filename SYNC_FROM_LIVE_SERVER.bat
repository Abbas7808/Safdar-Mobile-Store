@echo off
setlocal enabledelayedexpansion
title Safdar Mobile Store (SMS) - Live Website Synchronizer
color 0E

echo =========================================================================
echo       SAFDAR MOBILE STORE (SMS) - SYNC FROM LIVE SAFDARMOBILESTORE.COM
echo =========================================================================
echo.
echo [INFO] Fetching latest products, images, categories, and settings
echo        directly from https://safdarmobilestore.com/ ...
echo.

set "PHP_CMD="
if exist "C:\xampp\php\php.exe" (
    set "PHP_CMD=C:\xampp\php\php.exe"
) else if exist "D:\xampp\php\php.exe" (
    set "PHP_CMD=D:\xampp\php\php.exe"
) else (
    where php >nul 2>nul
    if !errorlevel! equ 0 (
        set "PHP_CMD=php"
    )
)

if "%PHP_CMD%"=="" (
    color 0C
    echo [ERROR] PHP executable not found.
    pause
    exit /b 1
)

"%PHP_CMD%" "%~dp0sync_from_live.php"

echo.
echo =========================================================================
echo   [SUCCESS] All live website data & images have been synchronized!
echo =========================================================================
echo.
pause
