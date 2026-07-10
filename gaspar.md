# GASPAR — Plataforma Institucional de Gestão por Processos
> Sistema institucional de BPM (Business Process Management) desenvolvido para o INCT CO₂ Zero com foco em automação, padronização, rastreabilidade e gerenciamento dinâmico de processos.

---

## 🎯 Objetivo do MVP
Validar a engine dinâmica do sistema (*Metadata-Driven Architecture*), provando que o administrador consegue criar processos, campos dinâmicos e fluxos sem alterar a estrutura física do banco MySQL (usando UUID7 e estruturas JSON).

---

## 📊 Status do Roadmap do MVP

### 🟩 Etapa 0 — Estrutura Inicial [CONCLUÍDO]
- [x] Configuração do Laravel 12 + PHP 8.3+
- [x] Configuração do banco de dados MySQL
- [x] Configuração do repositório GitHub (`main`, `develop`)
- [x] Configuração do ambiente local (`.env`)

### 🟩 Etapa 1 — Autenticação & Governança [CONCLUÍDO]
- [x] Instalação e configuração inicial do Filament v3
- [x] Criação do `UserResource` no Filament
- [x] Definição do `UserRoleEnum` (ADMIN, GESTOR, EXECUTOR, VIEWER)
- [x] Criação da `UserPolicy` básica de acessos locais
- [x] Especificação física da Migration de `users` (UUID7) e ajuste do Model de User

### 🟩 Etapa 2 — Engine Dinâmica [CONCLUÍDO]
- [x] Mapeamento e criação de `custom_entities` (Entidades/Processos)
- [x] Mapeamento e criação de `custom_fields` (Campos Dinâmicos)
- [x] Implementação da renderização dinâmica de formulários no Filament
- [x] Salvamento em `custom_records` (Leitura/escrita estruturada do `data_json`)

### 🟩 Etapa 3 — Workflow [CONCLUÍDO]
- [x] Configuração de Papéis de Processo (`process_roles`) e Situações (`process_statuses`)
- [x] Vinculação de Membros ao processo (`process_members`) com múltiplos papéis por usuário
- [x] Vinculação de Situações por processo (`entity_process_status`) reutilizáveis globalmente
- [x] Cadastro de Clientes (`clients`) e Projetos (`projects`)
- [x] Abertura de `demands` vinculadas a processo, situação, cliente, projeto, responsável
- [x] Relatos de Tratamento (`demand_comments`) com histórico por demanda
- [x] Suporte a Subdemandas via `parent_demand_id`

### 🟩 Etapa 4 — Dashboard [CONCLUÍDO]
- [x] Widget `DemandStatsWidget`: cards de indicadores (filtrados por perfil — ADMIN/GESTOR veem tudo, demais veem só as suas)
- [x] Widget `MyDemandsWidget`: tabela "Minhas Demandas" com SLA vencido em vermelho, link direto para edição, filtros por processo e prioridade

### 🟩 Etapa 5 — Auditoria [CONCLUÍDO]
- [x] Migration `activity_logs` com UUID7, FK users, evento, tipo/id auditado, old/new values JSON, IP e user_agent
- [x] Model `ActivityLog` com labels amigáveis e cores por evento
- [x] Trait `LogsActivity` em `app/Concerns/` — auditoria automática via boot events (created/updated/deleted), silenciosa em caso de erro
- [x] Trait aplicada em: `Demand`, `CustomEntity`, `Client`, `Project`, `ProcessStatus`, `ProcessRole`
- [x] `ActivityLogResource` somente-leitura (só ADMIN) com infolist detalhado, filtros por evento/usuário/entidade/período e auto-refresh 30s

### 🟩 Etapa 6 — Campos Customizados nas Demandas [CONCLUÍDO]
- [x] Migration `demand_field_values` (demand_id + custom_field_id + value, UNIQUE por par)
- [x] Model `DemandFieldValue` com relacionamentos
- [x] `Demand::fieldValues()` hasMany adicionado
- [x] `DemandResource::buildDynamicFieldSchema()` — renderiza campos do processo usando `field_data.{key}` como namespace no form (mesmo padrão do CustomRecordResource)
- [x] `CreateDemand` — extrai `field_data` antes do insert e persiste em `demand_field_values` após criar
- [x] `EditDemand` — carrega valores de volta no form ao abrir; atualiza/limpa demand_field_values ao salvar

### 🟩 Etapa 7 — Transições de Situação [CONCLUÍDO]
- [x] Migration `status_transitions` (entity_id + from_status → to_status + label + allowed_role_ids JSON, UNIQUE por tripla)
- [x] Model `StatusTransition` com método `canBeTriggeredBy(User, entityId)` centralizado
- [x] `StatusTransitionsRelationManager` na aba "Fluxo de Situações" do Processo — form com from/to/label/papéis, tabela reordenável
- [x] Registro da nova aba no `CustomEntityResource`
- [x] `EditDemand::getHeaderActions()` — botões dinâmicos por transição disponível: filtra por status atual + papel do usuário, exige confirmação, executa transição e grava audit log com contexto

### 🟥 Etapa 8 — Anexos [A INICIAR]
- [ ] `demand_attachments`: upload de arquivos vinculados à demanda
- [ ] Storage local (configurável para S3)
- [ ] Listagem e download dos anexos na demanda

---

### 🔮 Pós-MVP (após Etapa 8)
- Notificações por e-mail ao responsável (Laravel Mail + Queue)
- Dashboard com gráficos de SLA e desempenho (Charts)
- API REST pública para integração com outros sistemas
- Pesquisa de satisfação
- Modelagem gráfica de processos (BPMN)

---

## 🛠️ Especificação de Relacionamentos do Banco (Ajustado)
Para evitar falhas de integridade referencial nas próximas etapas, o banco implementará estritamente os seguintes vínculos sobre as tabelas fixas:
* `custom_entities` ➔ Pai de ➔ `custom_fields` e `processes(entity_id)`
* `custom_records` ➔ Pai de ➔ `demands(record_id)`
* `processes` ➔ Pai de ➔ `process_stages`, `process_transitions` e `process_members`
* `demands` ➔ Pai de ➔ `workflow_instances`, `demand_comments` e `demand_attachments`
* `workflow_instances` ➔ Pai de ➔ `workflow_history`

### Estrutura do banco de dados 

ENUMS:
field_type

TEXT
TEXTAREA
NUMBER
DATE
SELECT
CHECKBOX
RADIO
EMAIL

user_role_enum

ADMIN
GESTOR
EXECUTOR
VIEWER

process_role_enum

OWNER
MANAGER
EXECUTOR
VIEWER

priority_enum

LOW
MEDIUM
HIGH
URGENT

status

ACTIVE
COMPLETED
CANCELED

TABELAS

users

º id: UUID7 (PK)
- name: Text (Not Nullable)
- email: Text (Not Nullable, Unique)
- password: Text (Not Nullable)
- user_role: Enum (Not Nullable)
- is_active: Boolean (Not Nullable)
- created_at: Timestamp (Not Nullable)
- updated_at: Timestamp (Not Nullable)



audit_logs

- id: UUID7 (PK)
- user_id: UUID7 (FK users)
- event: Text 
- auditable_type: Text
- old_values: JSON
- new_values: JSON
- ip_address: Text
- created_at: Timestamp

custom_entities

º id: UUID7 (PK)
- name: Text (Not Nullable)
- description: Text (Nullable)
- is_active: Boolean (Not Nullable)
- created_by: UUID7 (FK users)
- created_at: Timestamp (Not Nullable)
- updated_at: Timestamp (Not Nullable)

custom_fields

º id: UUID7 (PK)
- entity_id: UUID7 (FK)
- name: Text (Not Nullable)
- key: Text(Not Nullable) (ex: destino_viagem)
- field_type: Enum (Not Nullable)
- placeholder: Text (Nullable)
- is_required: Boolean (Not Nullable)
- default_value: Text (Nullable)
- options_json: JSON (Nullable)
- field_order: Integer (Not Nullable)
- created_by: UUID7 (FK users)
- created_at: Timestamp (Not Nullable)
- updated_at: Timestamp (Not Nullable)

custom_records

- id: UUID7 (PK)
- entity_id: UUID7 (FK custom_entities)
- created_by: UUID7 (FK users)
- data_json: JSON (Not Nullable)
- created_at: Timestamp (Not Nullable)
- updated_at: Timestamp (Not Nullable)

processes

- id: UUID7 (PK)
- entity_id: UUID7 (FK custom_entities)
- name: Text (Not Nullable)
- description: Text (Nullable)
- is_active: Boolean (Not Nullable)
- created_by: UUID7 (FK users)
- created_at: Timestamp
- updated_at: Timestamp


process_stages

- id: UUID7 (PK)
- process_id: UUID7 (FK processes)
- name: Text (Not Nullable)
- description: Text (Nullable)
- order: Integer (Not Nullable)
- is_initial: Boolean
- is_final: Boolean
- created_at: Timestamp
- updated_at: Timestamp


process_transitions

- id: UUID7 (PK)
- process_id: UUID7 (FK processes)
- from_stage_id: UUID7 (FK process_stages)
- name: Text (Not Nullable)
- allowed_roles: JSON
- created_at: Timestamp
- updated_at: Timestamp


process_transitions_stages

- id: UUID7 (PK)
- transition_id: UUID7 (FK processes)
- to_stage_id: UUID7 (FK process_stages)


process_members

- id: UUID7
- process_id: UUID7
- user_id: UUID7
- process_role: ENUM
- created_at: Timestamp
- updated_at: Timestamp

workflow_instances

- id: UUID7 (PK)
- demand_id: UUID& (FK custom_records)
- process_id: UUID7 (FK processes)
- current_stage_id: UUID7 (FK process_stages)
- assigned_to: UUID7 (FK users)
- started_at: Timestamp
- completed_at: Timestamp (Nullable)
- created_at: Timestamp
- updated_at: Timestamp

workflow_history


- id: UUID7
- workflow_instance_id: UUID7(FK workflow_instances)
- from_stage_id: UUID7(FK process_stages)
- to_stage_id: UUID7(FK process_stages)
- transition_id: UUID7 (FK transition)
- changed_by: UUID7 (FK users)
- comment: Text (Nullable)
- snapshot_data: JSON
- created_at: Timestamp
- updated_at: Timestamp


clients

- id: UUID7 (PK)
- name: Text
- email: Text
- phone: Text
- is_active: Boolean
- created_at: Timestamp
- updated_at: Timestamp

projects

- id: UUID7 (PK)
- client_id: UUID7 (FK clients)
- name: Text
- description: Text
- status: Enum
- created_at: Timestamp
- updated_at: Timestamp

demands

- id: UUID7 (PK)
- record_id: UUID7 (FK custom_records)
- process_id: UUID7 (FK processes)
- project_id: UUID7 (FK projects)
- client_id: UUID7 (FK clients)
- requested_by: UUID7 (FK users)
- assigned_to: UUID7 (FK users)
- parent_demand_id: UUID7 (FK demands) Nullable
- title: Text
- description: Text
- status: ENUM(status)
- priority: ENUM(priority_enum)
- sla_due_at: Timestamp Nullable
- started_at: Timestamp
- completed_at: Timestamp Nullable
- created_at
- updated_at

demand_comments

- id: UUID7 (PK)
- demand_id: UUID7 (FK demands)
- user_id: UUID7 (FK users)
- comment: Text
- is_internal: Boolean
- created_at
- updated_at

demand_attachments

- id: UUID7 (PK)
- demand_id: UUID7 (FK demands)
- uploaded_by: UUID7 (FK users)
- file_name: Text
- file_path: Text
- mime_type: Text
- file_size: Integer
- created_at: Timestamp
- updated_at: Timestamp
