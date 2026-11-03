@echo off
title SmartAcc Hub - Server
echo ==============================================================
echo              MEMULAI APLIKASI SMARTACC HUB
echo    Sistem Akuntansi & Manajemen Top Up Game Terpadu
echo ==============================================================
echo.
echo Server berjalan di http://localhost:8000
echo Tekan Ctrl+C untuk menghentikan server.
echo.
start http://localhost:8000
php -S localhost:8000
pause
