# GASPAR — Plataforma Institucional de Gestão por Processos

> Sistema institucional de BPM (Business Process Management) desenvolvido para o INCT CO₂ Zero com foco em automação, padronização, rastreabilidade e gerenciamento dinâmico de processos baseado em uma arquitetura orientada a metadados (*metadata-driven architecture*).

---

## 🎯 Fase Atual do Projeto: MVP Concluído

O MVP do Gaspar foi finalizado e validado com sucesso. A plataforma está totalmente funcional e conta com os seguintes módulos operacionais:

*   **Engine Dinâmica (Metadata-Driven)**: Criação de Entidades (Processos) e Campos Dinâmicos (texto, data, booleano, etc.) via painel administrativo, salvando as respostas dinamicamente em formato JSON no banco, sem a necessidade de alterar tabelas físicas do MySQL.
*   **Workflow Avançado**: Mapeamento de Papéis de Processo (`process_roles`), Situações (`process_statuses`) e transições dinâmicas autorizadas. O sistema move as demandas entre situações, alterando dinamicamente os usuários/papéis responsáveis a cada transição.
*   **Dashboard Inteligente**: Indicadores agregados na tela inicial (Minhas Demandas Pendentes, Vencidas, Concluídas, Ativas) visíveis para todos os colaboradores, além de uma listagem inteligente de pendências dinâmicas para o executor logado.
*   **Auditoria Completa (Logs de Atividades)**: Rastreabilidade total. Cada ação de criação, alteração ou exclusão nos principais modelos gera logs detalhados estruturados (guardando valores antigos e novos no banco) visíveis apenas para Administradores.
*   **Instalador e Desinstalador Automatizados**: Suporte a instalações e desinstalações automáticas tanto em servidores web (interface visual via navegador) quanto em ambientes locais (scripts CLI para Windows e Mac).

---

## 📂 Organização das Pastas

O projeto segue a estrutura padrão do Laravel 11/12 e do FilamentPHP v3. Abaixo estão os principais diretórios customizados do sistema:

```txt
Gaspar/
├── app/
│   ├── Concerns/               # Traits auxiliares (ex: LogsActivity para auditoria automática)
│   ├── Enums/                  # Enumeradores tipados do sistema (ex: UserRoleEnum, DemandStatusEnum)
│   ├── Filament/               # Configurações do painel administrativo FilamentPHP
│   │   ├── Resources/          # Telas de CRUD e lógicas de negócios (CustomEntity, Demand, User, etc.)
│   │   └── Widgets/            # Blocos e gráficos do Dashboard (DemandStatsWidget, MyDemandsWidget)
│   ├── Http/Controllers/       # Controladores da aplicação (ex: WebInstallerController)
│   └── Models/                 # Modelos do Eloquent mapeando o banco (User, Demand, StatusTransition, etc.)
├── database/
│   ├── migrations/             # Estrutura física fixa do banco de dados MySQL
│   └── seeders/                # Populadores de dados de teste (seeder de banco inicial)
├── resources/
│   ├── views/                  # Telas HTML/Blade do sistema (ex: install.blade.php, uninstall.blade.php)
│   └── css/                    # Estilos CSS globais da aplicação
├── routes/
│   └── web.php                 # Definições de rotas web (redirecionamento e rotas de instalação)
├── instalar.bat / desinstalar.bat   # Scripts rápidos de instalação e remoção para Windows
├── install.sh / uninstall.sh       # Scripts rápidos de instalação e remoção para Linux/macOS
└── README.md                   # Documentação técnica do projeto
```

---

## 🔄 Como Funciona o Processo (BPM) no Gaspar

O Gaspar funciona através de um fluxo lógico de parametrização dinâmica de metadados:

1.  **Criação de Papéis**: O Administrador define quais são os papéis do processo (ex: *Solicitante*, *Gestor*, *Financeiro*).
2.  **Vinculação de Membros**: Os usuários do sistema são associados aos papéis criados dentro de cada processo/entidade.
3.  **Configuração de Campos**: O Administrador adiciona campos dinâmicos ao processo (ex: *Destino da Viagem*, *Valor*, *Data de Ida*).
4.  **Definição das Situações e Transições**:
    *   Cria-se as etapas do fluxo (ex: *Pendente*, *Em Aprovação*, *Comprado*).
    *   Desenham-se as "pontes" (Transições): De qual situação para qual situação a demanda pode ir, quem tem permissão para disparar essa transição (ex: apenas o *Gestor*) e quem se tornará o responsável pela demanda ao chegar na próxima situação (ex: o papel *Financeiro*).
5.  **Operação**: O Solicitante abre a demanda preenchendo os campos dinâmicos. A demanda tramita de acordo com as permissões e o fluxo definidos, atualizando os dashboards e listagens dos respectivos responsáveis em tempo real.

---

## 🚀 Passo a Passo de Instalação e Desinstalação

### 🌐 Método 1: Instalação Web (Recomendado para Servidores/Hospedagens)

Se você vai rodar o sistema diretamente em um servidor web (como HostGator, cPanel, VPS, etc.) onde não deseja usar o terminal:

#### Instalar:
1.  Envie o arquivo `.zip` do projeto para a sua hospedagem e descompacte-o.
2.  Garanta que o arquivo `.env` **não** esteja presente na pasta (se existir, delete-o para iniciar uma instalação limpa).
3.  Acesse o seu domínio no navegador (ex: `https://seusite.com.br/gaspar`).
4.  O sistema detectará a primeira execução e o redirecionará automaticamente para a tela do **Instalador Web** (`/install`).
5.  Preencha os dados de conexão do seu banco de dados MySQL e crie o usuário Administrador nos campos indicados.
6.  Clique em **Instalar Gaspar**. O sistema criará o arquivo `.env`, gerará a chave de segurança, criará as tabelas do banco e o usuário Administrador. Você será redirecionado para a tela de login pronto para usar!

#### Desinstalar:
1.  Acesse a URL do sistema adicionando `/uninstall` ao final (ex: `https://seusite.com.br/gaspar/uninstall`).
2.  Clique no botão vermelho **"Sim, apagar todos os dados"**.
3.  Confirme no alerta de segurança do navegador. O sistema removerá todas as tabelas do banco de dados e excluirá a trava de segurança, liberando o sistema para uma nova instalação.

---

### 💻 Método 2: Instalação Local via Terminal (Windows, macOS e Linux)

Se você vai rodar o projeto localmente na sua máquina de desenvolvimento:

#### Requisitos:
*   PHP 8.3+ instalado localmente e configurado no PATH do sistema.
*   Servidor MySQL ativo (ex: via XAMPP, Laragon ou Docker).

#### Instalar (Windows):
1.  Dê um duplo clique no arquivo `instalar.bat` na raiz do projeto.
2.  Preencha as informações do banco de dados que serão solicitadas de forma interativa na janela do terminal.
3.  Defina o E-mail e Senha do seu usuário Admin.
4.  O script abrirá o seu navegador padrão no endereço `http://localhost:8000` e iniciará o servidor local automaticamente. Mantenha a janela do terminal aberta enquanto utiliza o sistema.

#### Instalar (macOS e Linux):
1.  Abra o terminal na pasta do projeto.
2.  Execute o script com o comando:
    ```bash
    ./install.sh
    ```
3.  Responda às perguntas interativas no terminal para configurar o banco de dados e o Admin.
4.  Após a conclusão, inicie o servidor com:
    ```bash
    php artisan serve
    ```

#### Desinstalar (Windows):
1.  Dê um duplo clique no arquivo `desinstalar.bat`.
2.  Confirme a ação digitando "Sim" no prompt. O script apagará todas as tabelas do banco de dados e removerá o arquivo `.env` gerado.

#### Desinstalar (macOS e Linux):
1.  Execute no terminal:
    ```bash
    ./uninstall.sh
    ```
2.  Confirme a ação para limpar o banco e resetar a instalação.