@echo off
title Instalador Gaspar
cls
echo ==================================================
echo   Iniciando a instalacao do Gaspar...
echo ==================================================
echo.

:: Verifica se o PHP esta instalado
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo Erro: PHP nao encontrado! Por favor, instale o PHP antes de continuar.
    pause
    exit /b 1
)

:: Verifica se o Composer esta instalado
composer -v >nul 2>&1
if %errorlevel% neq 0 (
    echo Erro: Composer nao encontrado! Por favor, instale o Composer antes de continuar.
    pause
    exit /b 1
)

echo Instalando as dependencias do projeto (isso pode demorar um pouco)...
call composer install --no-interaction --quiet

:: Copia o .env se nao existir
if not exist .env (
    echo Criando arquivo .env...
    copy .env.example .env >nul
)

:: Gera a chave da aplicacao
call php artisan key:generate --no-interaction --quiet

echo.
echo Tudo pronto para a configuracao! Iniciando o assistente...
echo.

:: Executa o comando interativo do Laravel
call php artisan gaspar:install

pause
