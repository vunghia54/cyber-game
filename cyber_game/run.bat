
@echo off
chcp 65001 >nul
title Cyber Game Launcher
cd /d "%~dp0"

echo ====================================
echo        CYBER GAME - START
echo ====================================

where php >nul 2>&1
if %errorlevel%==0 goto start

if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
    goto server
)

echo [ERROR] Khong tim thay PHP.
echo Hay cai XAMPP hoac them PHP vao PATH.
pause
exit /b 1

:start
set "PHP_EXE=php"

:server
echo Starting PHP Server...
start "" "http://localhost:8000"
"%PHP_EXE%" -S localhost:8000 -t "%~dp0"

echo.
echo Server stopped or failed.
pause
  