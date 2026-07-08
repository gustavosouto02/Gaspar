# Manual de Instalação e Desinstalação - Gaspar

Bem-vindo ao Gaspar. Este sistema foi desenhado para ter uma instalação "Drop-In", o que significa que **não é necessário utilizar o terminal (SSH)** ou rodar comandos complexos no servidor. 

Siga o passo a passo abaixo para colocar o sistema no ar.

---

## 🚀 Requisitos do Servidor
* Servidor Web (Apache, Nginx, LiteSpeed, etc.)
* PHP 8.2 ou superior
* Banco de Dados MySQL
* Extensões PHP padrão do Laravel (BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML)

---

## 📥 1. Instalação do Sistema (Primeiro Uso)

### Passo 1: Upload e Extração
1. Pegue o arquivo `gaspar.zip` e faça o upload para o seu servidor web (por exemplo, dentro da pasta `public_html`, `www` ou `htdocs`).
2. Descompacte o arquivo `.zip`. Ele criará uma pasta com os arquivos do projeto (ex: `public_html/gaspar`).

### Passo 2: Permissões de Escrita
O servidor web precisará de permissão para escrever e criar arquivos dentro da pasta do projeto. 
Garanta que a pasta raiz do Gaspar tenha permissões de leitura/escrita apropriadas, pois o sistema criará o arquivo `.env` e gravará logs de sistema automaticamente.

### Passo 3: Acesso e Assistente Web
1. Abra o seu navegador e acesse a URL correspondente à pasta onde você extraiu o sistema. 
   - *Exemplo:* `http://inct.org.br/gaspar` (se houver o arquivo `.htaccess` na raiz direcionando para a `/public`).
   - *Alternativa:* `http://inct.org.br/gaspar/public` (caso o servidor não use o `.htaccess` raiz).
2. O sistema detectará automaticamente que é o primeiro acesso e redirecionará você para o **Assistente de Instalação**.

### Passo 4: Configuração
1. **Banco de Dados MySQL:** Insira os dados de conexão do seu banco de dados MySQL (Host, Porta, Usuário, Senha e Nome do Banco). Se o banco de dados não existir e o seu usuário do MySQL tiver privilégios de ROOT, o sistema tentará criar o banco automaticamente.
2. **Administrador:** Defina o Nome, E-mail e Senha do primeiro usuário Administrador do sistema.
3. Clique em **Instalar Gaspar**.
   - *O que acontece por baixo dos panos:* O sistema gera as chaves de criptografia, cria o arquivo `.env`, constrói todas as tabelas do banco de dados automaticamente e grava o seu usuário administrador. Ao final, ele cria uma "trava" de segurança (`installed.txt`) para garantir que o assistente de instalação nunca mais seja acessado acidentalmente.

---

## 🗑️ 2. Como Desinstalar o Sistema (Wipe Completo)

Se você precisar formatar o sistema, limpar todos os dados de testes ou refazer a instalação do zero, você pode usar o módulo de desinstalação seguro.

1. Navegue até a URL de desinstalação: `http://inct.org.br/gaspar/desinstalar` (ajuste a URL conforme o seu domínio).
2. Confirme a ação na tela.
   - *O que acontece por baixo dos panos:* O sistema irá destruir permanentemente todas as tabelas e dados do banco MySQL, excluirá o arquivo `.env` (credenciais) e removerá a trava de segurança de instalação.
3. Você será redirecionado imediatamente de volta para o Assistente de Instalação, com o sistema zerado e pronto para ser configurado novamente.

---

## ⚠️ Dica Importante sobre Segurança
Nunca deixe arquivos `.zip` de backup largados na pasta pública do servidor. Após realizar a extração e instalação, se preferir, você pode apagar o arquivo `gaspar.zip` original do servidor para poupar espaço.
