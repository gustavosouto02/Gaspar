#!/bin/bash

# Cores para o terminal
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${RED}======================================${NC}"
echo -e "${RED}   Desinstalação do Sistema Gaspar    ${NC}"
echo -e "${RED}======================================${NC}"
echo ""

# Verifica se o PHP está instalado
if ! command -v php &> /dev/null; then
    echo -e "${RED}Erro: PHP não encontrado!${NC}"
    exit 1
fi

php artisan gaspar:uninstall
