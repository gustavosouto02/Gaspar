# GASPAR — Plataforma Institucional de Gestão por Processos

> Sistema institucional de BPM (*Business Process Management*) e Central de Demandas, desenvolvido para gestão, automação, padronização, rastreabilidade e parametrização dinâmica de fluxos de trabalho baseado em uma arquitetura orientada a metadados (*Metadata-Driven Architecture*).

---

## 🚀 Visão Geral e Arquitetura

O **Gaspar** foi concebido para eliminar a necessidade de alterações manuais no código ou no banco de dados relacional sempre que uma nova demanda institucional ou processo necessitar de novos formulários, campos ou fluxos.

- **Framework**: Laravel 12 + FilamentPHP v3
- **Chaves Primárias**: UUID versão 7 (`Str::uuid7()`) em todas as entidades, ordenáveis cronologicamente por padrão.
- **Engine Dinâmica**: Metadados configuráveis em tempo de execução (`custom_entities`, `custom_fields`, `demand_field_values`).
- **Banco de Dados**: MySQL 8+ com suporte a JSON nativo.
- **Frontend / UI**: FilamentPHP com Livewire 3 e estilizações customizadas.

---

## ✨ Funcionalidades do Sistema

### 1. Engine Dinâmica de Processos e Campos
- **Processos Customizados**: Criação e edição de processos com finalidade, prazos de SLA e associação a macroprocessos.
- **Sigla do Macroprocesso**: Exibição automática e padronizada da sigla do macroprocesso antes do nome do processo (`SIGLA - Nome do Processo`) em todas as tabelas, formulários e relatórios.
- **Campos Dinâmicos Reutilizáveis**: Suporte aos tipos `TEXT`, `TEXTAREA`, `NUMBER`, `DATE`, `DATETIME`, `SELECT`, `CHECKBOX`, `RADIO`, `FILE`.
- **Campos Customizados em Cadastros Permanentes**: Usuários, Clientes, Projetos, Fornecedores e Macroprocessos aceitam novos campos personalizados, mantendo os campos nativos do sistema protegidos contra exclusão ou alteração indevida.

### 2. Workflow, Situações e Transições
- **Papéis de Processo (`process_roles`)**: Definição de papéis estáticos (ex: *Aprovador*, *Financeiro*, *Técnico*) e vinculação de usuários aos papéis em cada processo (`process_members`).
- **Matriz de Transição de Situações**: Configuração de quais situações podem transicionar para outras, quais papéis têm autorização para disparar a ação e auto-atribuição dinâmica do responsável na demanda.
- **Devolução Retroativa**: Ação inteligente para retornar a demanda à situação anterior, reatribuindo automaticamente ao executor anterior com base no histórico de auditoria.

### 3. Central de Gestão de Demandas
- **Busca Global Avançada**: Localização inteligente de demandas por ID curto ou completo (com ou sem `#`), título, descrição, processo, sigla do macroprocesso, situação, cliente, projeto, responsáveis, **nomes de campos customizados** (ex: pesquisar *"Destino"*) e **valores preenchidos nos campos dinâmicos** (ex: pesquisar *"São Paulo"*).
- **Busca em Cadastros Permanentes**: Usuários, Clientes, Projetos, Fornecedores e Macroprocessos também realizam busca completa em seus campos customizados adicionais (`custom_data`).
- **Controle de SLA**: Definição automática de prazo limite de atendimento com base nas horas cadastradas no processo.
- **Linha do Tempo e Relatos de Tratamento**: Registro contínuo de observações, tratamentos e interações com layout escuro responsivo e tipografia otimizada.
- **Subdemandas**: Abertura de subdemandas filhas com trava de encerramento da demanda-mãe enquanto houver subdemandas em andamento.
- **Pesquisa de Satisfação**: Avaliação de 1 a 5 estrelas e comentário após a conclusão do atendimento.

### 4. Sistema de Notificações por E-mail
- **Provedor SMTP Homologado**: Integração com **Titan Email / HostGator** (`smtp.titan.email`, porta 465 SSL / 587 TLS).
- **Link Direto**: Todos os e-mails contêm botão de ação e **link direto** no corpo do e-mail para abrir e visualizar a demanda no sistema.
- **Disparos em Tempo Real**:
  - Ao criar e enviar uma demanda para atendimento (notifica solicitante e responsável atribuído).
  - Ao transicionar ou devolver a situação da demanda.
  - Ao registrar novos comentários/relatos de tratamento (notifica a outra parte envolvida).
  - Ao encerrar a demanda (envia pesquisa de satisfação ao solicitante).
- **Alertas Automatizados de SLA**:
  - **1 dia antes de vencer**: Alerta preventivo com contagem regressiva.
  - **No dia do vencimento**: Alerta de urgência para priorização do atendimento.
  - **Diariamente após vencida**: Lembrete diário contendo a contagem de dias em atraso.
  - **Proteção Antiduplicação**: Controle diário via `last_deadline_alert_date` para garantir que cada demanda receba no máximo 1 alerta por dia.

### 5. Relatórios e Indicadores
- **Relatório de Demandas com Consulta Manual**: Botão "Consultar" para execução sob demanda, evitando lentidão ao carregar grandes massas de dados.
- **Relatórios Salvos**: Armazenamento de configurações de filtros frequentes.
- **Dashboards Operacionais**: Widgets com gráficos de SLA, distribuição por processo e resumo de pendências individuais.

### 6. Auditoria Completa (*Activity Log*)
- Rastreamento estruturado de eventos (`created`, `updated`, `deleted`, `transition`) com captura de valores anteriores e novos em formato JSON, IP do usuário e User-Agent.

### 7. Instalador e Utilitários de Hospedagem
- Rotas guiadas para instalação (`/instalar`) e desinstalação (`/desinstalar`) em hospedagens cPanel/HostGator.
- Rota para gatilho de prazos via Web (`/trigger-deadlines`) para integração com Cron jobs web.

---

## 📋 Pré-requisitos

Para executar o projeto localmente ou em servidor:

- **PHP** >= 8.2 (Recomendado **PHP 8.3**)
- **Extensões PHP obrigatórias**:
  - `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`, `fileinfo`, `xml`, `zip`
- **Composer** >= 2.5
- **Node.js** >= 18.x e **NPM**
- **MySQL** >= 8.0 ou **MariaDB** >= 10.5

---

## ⚙️ Instalação e Execução Local

### 1. Clonar o Repositório
```bash
git clone https://github.com/gustavosouto02/Gaspar.git
cd Gaspar
```

### 2. Instalar Dependências do PHP
```bash
composer install
```

### 3. Instalar Dependências do Frontend
```bash
npm install
npm run build
```

### 4. Configurar as Variáveis de Ambiente
Copie o arquivo de exemplo e configure sua conexão de banco e e-mail:
```bash
cp .env.example .env
```

Abra o `.env` e configure o banco de dados e as credenciais de e-mail:
```env
APP_NAME=Gaspar
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gaspar
DB_USERNAME=root
DB_PASSWORD=

# Configuração SMTP (Exemplo Titan Mail / HostGator)
MAIL_MAILER=smtp
MAIL_HOST=smtp.titan.email
MAIL_PORT=465
MAIL_USERNAME=no-reply@seu-dominio.com.br
MAIL_PASSWORD=SuaSenhaAqui
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=no-reply@seu-dominio.com.br
MAIL_FROM_NAME="Sistema Gaspar"
```

### 5. Gerar a Chave da Aplicação
```bash
php artisan key:generate
```

### 6. Executar Migrações e Dados Iniciais
```bash
php artisan migrate --seed
```

### 7. Criar Link Simbólico do Storage
```bash
php artisan storage:link
```

### 8. Iniciar o Servidor de Desenvolvimento
```bash
php artisan serve
```
Acesse no navegador: **`http://localhost:8000/admin`**

---

## 🛠️ Comandos Artisan Essenciais

| Comando | Descrição |
|---|---|
| `php artisan app:check-demand-deadlines` | Executa a verificação de prazos (1 dia antes, no dia e diariamente para vencidas) |
| `php artisan app:check-demand-deadlines --force` | Força a reemissão de alertas ignorando o envio diário prévio |
| `php artisan schedule:work` | Inicia o agendador do Laravel em ambiente de desenvolvimento |
| `php artisan optimize:clear` | Limpa todos os caches compilados (config, rotas, views, eventos) |
| `php artisan config:clear` | Limpa o cache exclusivo das configurações do `.env` |
| `php artisan migrate:status` | Exibe o status de execução de todas as migrations |
| `./build.sh` | Gera o pacote limpo `gaspar.zip` para deploy em produção |

---


## 📂 Estrutura de Diretórios

```txt
Gaspar/
├── app/
│   ├── Concerns/               # Traits (HasCustomFields, LogsActivity)
│   ├── Console/Commands/       # Comandos CLI (CheckDemandDeadlines)
│   ├── Enums/                  # Enumeradores tipados (Roles, Status, Prioridades, Cores)
│   ├── Filament/               # Recursos, páginas e widgets do painel Filament
│   │   ├── Resources/          # CRUDs e regras de negócio
│   │   ├── Pages/              # Páginas customizadas (Relatórios de Demandas)
│   │   └── Widgets/            # Cards e gráficos analíticos
│   ├── Models/                 # Modelos Eloquent mapeando o banco MySQL com UUID7
│   ├── Notifications/          # Classes de e-mail (Atividade, Prazos SLA, Satisfação)
│   └── Policies/               # Políticas de autorização e controle de acesso
├── database/
│   ├── migrations/             # Migrações com estrutura das tabelas fixas e relacionais
│   └── seeders/                # Populadores de dados padrão do sistema
├── public/                     # Ponto de entrada web público do servidor
├── resources/views/            # Componentes Blade e páginas do instalador
├── routes/
│   ├── console.php             # Agendamentos de tarefas CLI
│   └── web.php                 # Rotas do instalador, redirecionamentos e triggers web
└── build.sh                    # Script automatizado para geração do pacote de produção
```
