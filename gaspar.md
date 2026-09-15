# GASPAR — Plataforma Institucional de Gestão por Processos

> Sistema institucional de BPM (*Business Process Management*) e Central de Demandas desenvolvido para o INCT CO₂ Zero, com foco em automação, padronização, rastreabilidade e parametrização dinâmica de fluxos de trabalho através de uma Arquitetura Orientada a Metadados (*Metadata-Driven Architecture*).

---

## 🎯 Objetivo e Premissas Arquiteturais

A plataforma foi projetada para permitir que administradores e gestores criem e adaptem processos, campos dinâmicos, situações de atendimento e papéis sem a necessidade de alterações manuais de código ou criação contínua de tabelas físicas no banco relacional.

### Premissas Técnicas:
1. **Identificadores Únicos**: Todas as tabelas utilizam **UUID versão 7** (`Str::uuid7()`), garantindo unicidade global e ordenação cronológica natural por chave primária.
2. **Metadata-Driven Forms**: Os formulários são compostos dinamicamente a partir de registros das tabelas de metadados (`custom_entities`, `custom_fields`, `demand_field_values`).
3. **Imutabilidade e Segurança de Cadastros Nativos**: Registros e campos básicos do sistema possuem travas (`is_system`) que impedem exclusão acidental por parte dos usuários.
4. **Rastreabilidade Global**: Todas as alterações críticas são auditadas de forma automática via `ActivityLog` (salvando IP, User-Agent, dados antes e depois da operação).

---

## 📊 Status do Roadmap de Desenvolvimento

### 🟩 Etapa 1 — Autenticação, Perfis e Governança [CONCLUÍDO]
- [x] Configuração de `users` com UUID7 e controle de status (`is_active`).
- [x] Papéis globais tipados via `UserRoleEnum`: `ADMIN`, `GESTOR`, `EXECUTOR`, `VIEWER`.
- [x] Policies de autorização granulares aplicadas a todos os recursos.

### 🟩 Etapa 2 — Macroprocessos e Organização Institucional [CONCLUÍDO]
- [x] Cadastro de Macroprocessos (`macroprocesses`) com código/sigla, nome e descrição.
- [x] Vinculação de processos a macroprocessos.
- [x] Prefixo dinâmico com a sigla do macroprocesso (`SIGLA - Nome do Processo`) em todos os selects, tabelas, relatórios e formulários do sistema.

### 🟩 Etapa 3 — Engine Dinâmica de Metadados [CONCLUÍDO]
- [x] Modelagem de `custom_entities` (Processos) e `custom_fields` (Campos).
- [x] Tipos de campo suportados: `TEXT`, `TEXTAREA`, `NUMBER`, `DATE`, `DATETIME`, `SELECT`, `CHECKBOX`, `RADIO`, `FILE`.
- [x] Armazenamento normalizado de respostas em `demand_field_values`.
- [x] Campos customizados extensíveis para cadastros permanentes (`User`, `Client`, `Project`, `Supplier`, `Macroprocess`) via Trait `HasCustomFields`.

### 🟩 Etapa 4 — Workflow, Situações e Transições [CONCLUÍDO]
- [x] Papéis de processo (`process_roles`) e Situações reutilizáveis (`process_statuses`).
- [x] Matriz de transições (`status_transitions`): validação de situação de origem/destino, papéis autorizados e autoatribuição automática de executor.
- [x] Ação de **Devolução Retroativa**: permite retornar a demanda para a situação anterior e devolvê-la automaticamente ao usuário que a tramitou.

### 🟩 Etapa 5 — Central de Gestão de Demandas [CONCLUÍDO]
- [x] Abertura de demandas ativas e rascunhos com cálculo automático de SLA em horas úteis.
- [x] Busca universal inteligente por código com ou sem hashtag (`#019F7D18`), título, descrição, processo, sigla de macroprocesso, situação, cliente, projeto, responsáveis, **nomes de campos customizados** (ex: *"Destino"*) e **valores preenchidos** (ex: *"São Paulo"*).
- [x] Busca nos cadastros permanentes (`User`, `Client`, `Project`, `Supplier`, `Macroprocess`) incluindo os novos campos dinâmicos (`custom_data`).
- [x] Subdemandas filhas com trava de segurança impedindo o encerramento da demanda-mãe se houver subdemandas pendentes.
- [x] Linha do tempo de relatos/tratamentos com layout escuro e tipografia com contraste ideal.
- [x] Módulo de Pesquisa de Satisfação pós-conclusão da demanda.
- [x] Exportação completa do registro da demanda em PDF (dados gerais, SLA, formulário dinâmico, histórico de tramitação "por quem passou", relatos, subdemandas e avaliação).

### 🟩 Etapa 6 — Auditoria Completa e Logs [CONCLUÍDO]
- [x] Trait `LogsActivity` nos modelos vitais (`Demand`, `CustomEntity`, `Client`, `Project`, etc.).
- [x] Registro estruturado de transições e valores anteriores e posteriores em formato JSON.
- [x] Painel de logs de atividades exclusivo para administradores com pesquisa e filtros.

### 🟩 Etapa 7 — Notificações Transacionais por E-mail [CONCLUÍDO]
- [x] Configuração SMTP via Titan Email / HostGator (`smtp.titan.email`).
- [x] **Link direto** para abertura da demanda presente em todos os e-mails enviados.
- [x] Disparos em tempo real ao criar demanda, transicionar situação e registrar relatos.
- [x] **Alertas de Prazo (SLA)**:
  - 1 dia antes do vencimento (alerta preventivo).
  - No dia do vencimento (alerta de urgência).
  - Diariamente enquanto a demanda estiver vencida e pendente.
  - Coluna `last_deadline_alert_date` para controle rigoroso de envio único diário.

### 🟩 Etapa 8 — Relatórios e Painéis de Consulta [CONCLUÍDO]
- [x] Tela de Relatórios de Demandas com botão manual **"Consultar"** para preservação de performance.
- [x] Exportação de dados estruturados.
- [x] Armazenamento de configurações de relatórios frequentes em `saved_reports`.

---

## 🗄️ Estrutura de Tabelas e Dicionário de Dados

### 1. Governança e Usuários
- **`users`**: `id (UUID7)`, `name`, `email`, `password`, `user_role (ENUM: ADMIN, GESTOR, EXECUTOR, VIEWER)`, `process_role_id (FK)`, `phone`, `is_active (BOOLEAN)`.

### 2. Macroprocessos e Entidades
- **`macroprocesses`**: `id (UUID7)`, `code`, `name`, `description`, `is_active (BOOLEAN)`.
- **`custom_entities`**: `id (UUID7)`, `macroprocess_id (FK)`, `name`, `description`, `purpose`, `sla_hours (INT)`, `is_active (BOOLEAN)`, `created_by (FK)`.

### 3. Engine de Campos Dinâmicos
- **`custom_fields`**: `id (UUID7)`, `name`, `key`, `field_type (ENUM)`, `placeholder`, `is_required`, `default_value`, `options_json (JSON)`, `field_order (INT)`.
- **`custom_entity_custom_field`**: Tabela pivô de relacionamento entre processos e campos permitidos.
- **`custom_record_types`**: Metadados de tipos de registro para cadastros permanentes (`User`, `Client`, `Project`, `Supplier`, `Macroprocess`).
- **`custom_records`**: Armazenamento flexível com payload `data_json (JSON)`.

### 4. Workflow e Papéis
- **`process_roles`**: `id (UUID7)`, `name`, `description`, `is_active (BOOLEAN)`.
- **`process_statuses`**: `id (UUID7)`, `name`, `description`, `color (ENUM: ProcessStatusColorEnum)`, `is_system (BOOLEAN)`.
- **`process_members`**: `id (UUID7)`, `entity_id (FK)`, `user_id (FK)`, `process_role_id (FK)`.
- **`status_transitions`**: `id (UUID7)`, `entity_id (FK)`, `from_status_id (FK)`, `to_status_id (FK)`, `label`, `allowed_role_ids (JSON)`, `display_order (INT)`.

### 5. Cadastros Auxiliares
- **`clients`**: `id (UUID7)`, `name`, `email`, `phone`, `custom_data (JSON)`, `is_active (BOOLEAN)`.
- **`projects`**: `id (UUID7)`, `client_id (FK)`, `name`, `description`, `status (ENUM)`, `custom_data (JSON)`.
- **`suppliers`**: `id (UUID7)`, `name`, `document`, `email`, `phone`, `custom_data (JSON)`, `is_active (BOOLEAN)`.

### 6. Demandas e Atendimentos
- **`demands`**:
  - `id (UUID7)`
  - `entity_id (FK custom_entities)`
  - `process_status_id (FK process_statuses)`
  - `client_id (FK clients)`, `project_id (FK projects)`
  - `requested_by (FK users)`, `assigned_to (FK users)`, `created_by (FK users)`
  - `parent_demand_id (FK demands)` (para subdemandas)
  - `title`, `description`
  - `status (ENUM: DRAFT, ACTIVE, COMPLETED, CANCELED, CLOSED, EVALUATED)`
  - `priority (ENUM: LOW, MEDIUM, HIGH, URGENT)`
  - `sla_due_at (TIMESTAMP)`, `last_deadline_alert_date (DATE)`
  - `started_at (TIMESTAMP)`, `completed_at (TIMESTAMP)`
  - `satisfaction_rating (ENUM)`, `satisfaction_comment`, `satisfaction_evaluated_at (TIMESTAMP)`
  - `attachments (JSON)`
- **`demand_field_values`**: `id (UUID7)`, `demand_id (FK)`, `custom_field_id (FK)`, `value (TEXT)`.
- **`demand_comments`**: `id (UUID7)`, `demand_id (FK)`, `user_id (FK)`, `comment (TEXT)`, `is_internal (BOOLEAN)`.

### 7. Rastreabilidade
- **`activity_logs`**: `id (UUID7)`, `user_id (FK)`, `event`, `auditable_type`, `auditable_id`, `old_values (JSON)`, `new_values (JSON)`, `ip_address`, `user_agent`.

---

## 🔄 Ciclo de Vida e Regras de Negócio da Demanda

```
[ Criação / Rascunho ]
         │
         ▼
 [ Envio para Atendimento ] ──► Auto-Assign do Responsável (via Papel da 1ª Situação)
         │                   ──► Envia e-mail de confirmação ao Solicitante e Atribuído
         ▼
[ Em Tramitação / Transições ] ──► Permissões validadas por Papel na Matriz de Transição
         │                     ──► Notificação por e-mail a cada avanço
         │                     ──► Opção de "Devolver" para Situação Anterior
         ▼
   [ Conclusão ] ──► Valida se todas as subdemandas filhas estão finalizadas
         │       ──► Envia e-mail de Pesquisa de Satisfação ao Solicitante
         ▼
   [ Avaliada ] ──► Nota (1 a 5 estrelas) + feedback registrado
```

---

## 📧 Política de Notificações por E-mail

| Evento | Destinatários | Conteúdo |
|---|---|---|
| Demanda Criada / Enviada | Solicitante e Responsável | Confirmação de abertura, dados do processo e link direto |
| Transição de Situação | Solicitante e Responsável | Nova situação, executor da mudança e link direto |
| Devolução de Situação | Solicitante e Responsável Anterior | Aviso de retorno e justificativa |
| Novo Relato de Tratamento | Outra parte da demanda | Resumo do comentário e link direto |
| Demanda Concluída | Solicitante | Solicitação de preenchimento da pesquisa de satisfação |
| 1 Dia Antes de Vencer | Responsável / Solicitante | Alerta preventivo de prazo limite de SLA |
| No Dia do Vencimento | Responsável / Solicitante | Alerta de prioridade máxima para conclusão hoje |
| Vencida (Diário) | Responsável / Solicitante | Alerta diário com número de dias em atraso |

---

## 🚀 Guia de Deploy e Instalação no Servidor

### 1. Requisitos de Infraestrutura
- **PHP**: 8.2 ou superior (recomendado 8.3).
- **Extensões**: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`, `fileinfo`, `xml`, `zip`.
- **Banco de Dados**: MySQL 8.0+ ou MariaDB 10.5+.
- **Servidor Web**: Apache (com `mod_rewrite`), Nginx ou LiteSpeed.

### 2. Geração do Pacote de Produção (`build.sh`)
O projeto possui um script de empacotamento automatizado (`build.sh`) que prepara uma versão autocontida sem necessidade de rodar composer/npm no servidor de produção:
```bash
./build.sh
```
O script:
1. Exporta apenas arquivos versionados pelo Git (ignora `.env`, caches locais e logs).
2. Instala dependências do Composer com `--no-dev --optimize-autoloader`.
3. Prepara a árvore de diretórios do `storage/` e `bootstrap/cache/`.
4. Compacta o projeto no arquivo **`gaspar.zip`**.

### 3. Instalação Drop-In no Servidor (HostGator / cPanel)
1. **Upload**: Enviar `gaspar.zip` para o diretório do domínio/subdomínio no cPanel.
2. **Extração**: Descompactar os arquivos no servidor.
3. **Permissões**: Aplicar permissão `775` (ou `755`) recursiva em `storage/` e `bootstrap/cache/`.
4. **Assistente Web**: Acessar a URL pelo navegador (ex: `https://gaspar.seusite.com.br`). O sistema redirecionará para `/instalar`, onde serão informadas as credenciais do banco MySQL e criado o usuário Administrador.

### 4. Atualização Contínua em Produção
Para atualizar um sistema já em produção:
1. Gerar novo `gaspar.zip` e descompactar sobrescrevendo os arquivos no servidor (o `.env` existente permanece intacto).
2. Acessar a URL: `https://seu-dominio/update-system` para rodar automaticamente `optimize:clear` e `migrate --force`.

