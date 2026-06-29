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
    echo Erro: PHP nao encontrado! O Gaspar precisa do PHP para rodar.
    pause
    exit /b 1
)

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

echo.
echo Tudo pronto! O sistema vai abrir no seu navegador.
echo Mantenha esta janela preta aberta enquanto estiver usando o Gaspar.
echo.
:: Abre o navegador padrao no localhost
start http://localhost:8000
:: Inicia o servidor do Laravel
call php artisan serve