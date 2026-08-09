#!/bin/bash

# Define cores para o output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Iniciando o Build do Gaspar ===${NC}"

# Define variáveis
ORIGINAL_DIR=$(pwd)
BUILD_DIR="/tmp/gaspar-build"
ZIP_NAME="gaspar.zip"

# 1. Limpa o diretório de build anterior e o zip antigo
echo -e "\n${BLUE}[1/5] Preparando diretório de build...${NC}"
rm -rf $BUILD_DIR
rm -f $ZIP_NAME
mkdir -p $BUILD_DIR/gaspar

# 2. Usa o git archive para copiar apenas os arquivos trackeados pelo Git
# Isso ignora automaticamente o seu .env atual, a pasta vendor, e arquivos ignorados
echo -e "\n${BLUE}[2/5] Exportando código limpo...${NC}"
git archive --format tar HEAD | tar -x -C $BUILD_DIR/gaspar
rm -f $BUILD_DIR/gaspar/$ZIP_NAME

# 3. Instala as dependências do Composer para produção (sem pacotes de dev)
echo -e "\n${BLUE}[3/5] Instalando dependências de produção (vendor)...${NC}"
cd $BUILD_DIR/gaspar
composer install --no-dev --optimize-autoloader

# 4. Garante que os diretórios essenciais existam e estejam limpos
echo -e "\n${BLUE}[4/5] Limpando caches e configurando estrutura...${NC}"
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
rm -f storage/app/installed.txt
rm -f storage/logs/*.log
rm -f .env

# Volta para o diretório original do projeto
cd - > /dev/null

# 5. Cria o arquivo zip final
echo -e "\n${BLUE}[5/5] Gerando o arquivo ${ZIP_NAME}...${NC}"
cd $BUILD_DIR/gaspar
zip -r "$ORIGINAL_DIR/$ZIP_NAME" . -q
cd - > /dev/null

# Limpeza
rm -rf $BUILD_DIR

echo -e "\n${GREEN}=== Build Concluído com Sucesso! ===${NC}"
echo -e "O arquivo ${GREEN}${ZIP_NAME}${NC} foi gerado na raiz do projeto."
echo -e "Entregue este arquivo .zip para o seu chefe. Ele só precisa descomprimir no servidor e acessar pelo navegador."
