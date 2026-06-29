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

call php artisan gaspar:uninstall

echo Limpando arquivos de instalacao...
:: Apaga o .env gerado
if exist .env del /q .env
:: Apaga a pasta vendor (se quiser limpar as dependencias do composer)
if exist vendor rmdir /s /q vendor
:: Se o banco for SQLite, apague o arquivo para limpar totalmente:
:: if exist database\database.sqlite del /q database\database.sqlite

echo.
echo Desinstalacao concluida e arquivos limpos!
pause