@echo off
title Revert to Old Version (Without Cart)
color 0C
echo =========================================================================
echo       SAFDAR MOBILE STORE (SMS) - RESTORING OLD VERSION (WITHOUT CART)
echo =========================================================================
echo.
echo Restoring files from pre-cart backups...

if exist "index.php.pre_cart_bak" (
    copy /y "index.php.pre_cart_bak" "index.php"
    copy /y "index.php.pre_cart_bak" "public_html\index.php"
)

if exist "style.css.pre_cart_bak" (
    copy /y "style.css.pre_cart_bak" "style.css"
    copy /y "assets\css\style.css.pre_cart_bak" "assets\css\style.css"
    copy /y "assets\css\style.css.pre_cart_bak" "public_html\assets\css\style.css"
)

if exist "app.js.pre_cart_bak" (
    copy /y "app.js.pre_cart_bak" "app.js"
    copy /y "assets\js\app.js.pre_cart_bak" "assets\js\app.js"
    copy /y "assets\js\app.js.pre_cart_bak" "public_html\assets\js\app.js"
)

rem Sync to C:\xampp\htdocs\sms if present
if exist "C:\xampp\htdocs\sms" (
    robocopy "." "C:\xampp\htdocs\sms" /E /XD .trash .gemini scratch .git >nul 2>nul
)

echo.
color 0A
echo [SUCCESS] Reverted to the previous version successfully!
echo Please refresh your browser (Ctrl + F5).
echo.
pause
