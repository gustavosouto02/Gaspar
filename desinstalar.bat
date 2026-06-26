@echo off
title Desinstalador Gaspar
cls
echo ==================================================
echo   Desinstalacao do Sistema Gaspar
echo ==================================================
echo.

:: Verifica se o PHP esta instalado
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo Erro: PHP nao encontrado!
    pause
    exit /b 1
)

call php artisan gaspar:uninstall

pause
