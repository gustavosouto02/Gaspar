#!/bin/bash

# Cores para o terminal
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}    Iniciando a instalação do Gaspar  ${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

# Verifica se o PHP está instalado
if ! command -v php &> /dev/null; then
    echo -e "${RED}Erro: PHP não encontrado! Por favor, instale o PHP antes de continuar.${NC}"
    exit 1
fi

# Verifica se o Composer está instalado
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Erro: Composer não encontrado! Por favor, instale o Composer antes de continuar.${NC}"
    exit 1
fi

echo -e "${GREEN}Instalando as dependências do projeto (isso pode demorar um pouco)...${NC}"
composer install --no-interaction --quiet

# Copia o .env se não existir
if [ ! -f .env ]; then
    echo -e "${GREEN}Criando arquivo .env...${NC}"
    cp .env.example .env
fi

# Gera a chave da aplicação se estiver vazia
php artisan key:generate --no-interaction --quiet

echo ""
echo -e "${GREEN}Tudo pronto para a configuração! Iniciando o assistente...${NC}"
echo ""

# Chama o comando interativo do Laravel
php artisan gaspar:install
