
# GASPAR — Plataforma Institucional de Gestão por Processos

Sistema institucional de BPM (Business Process Management) desenvolvido para o INCT CO₂ Zero com foco em automação, padronização, rastreabilidade e gerenciamento dinâmico de processos.

---

# Visão Geral

O GASPAR será uma plataforma capaz de permitir que administradores criem:

- Processos
- Fluxos de trabalho
- Formulários
- Campos personalizados
- Permissões
- Etapas
- Demandas

Sem necessidade de programação ou criação manual de tabelas SQL.

O sistema será orientado a metadados (*metadata-driven architecture*), permitindo flexibilidade e escalabilidade para diferentes áreas institucionais.

---

# Objetivo do Projeto

Centralizar e digitalizar processos institucionais do INCT CO₂ Zero através de uma plataforma dinâmica e expansível.

O sistema deverá permitir:

- Criação dinâmica de processos
- Gestão de demandas
- Workflows personalizados
- Controle de permissões
- Auditoria completa
- Formulários dinâmicos
- Rastreabilidade de ações
- Escalabilidade futura

---

# Objetivo do MVP

A primeira versão (MVP) terá como foco validar a engine principal do sistema.

## O MVP deverá permitir:

✅ Login e autenticação  
✅ Controle de permissões  
✅ Criação de processos  
✅ Criação dinâmica de formulários  
✅ Criação dinâmica de campos  
✅ Criação de demandas  
✅ Workflow simples  
✅ Histórico de ações  
✅ Dashboard inicial  

---

# O MVP NÃO terá inicialmente

❌ IA  
❌ BPMN avançado  
❌ Integrações externas  
❌ Microservices  
❌ Websocket realtime  
❌ Analytics avançado  
❌ Automação complexa  

---

# Arquitetura do Sistema

O projeto seguirá o conceito de:

# Metadata-Driven Architecture

Ou seja:

O administrador cria configurações e o sistema interpreta essas configurações dinamicamente.

---

# Exemplo

O administrador poderá criar:

## Processo:
Solicitação de Viagem

## Campos:
- Destino
- Data
- Justificativa
- Centro de custo

Sem necessidade de alterar o banco manualmente.

---

# Estratégia do Banco de Dados

O sistema NÃO criará tabelas dinamicamente.

A estrutura será baseada em:

- Tabelas fixas
- Metadados
- Campos JSON

---

# Exemplo de armazenamento

```json
{
  "destino": "Brasília",
  "justificativa": "Evento",
  "valor": 5000
}
```

---

# Stack Oficial do Projeto

## Backend
- Laravel 12
- PHP 8.3+

## Admin Panel
- FilamentPHP

## Frontend
- Livewire
- Alpine.js

## Banco de Dados
- MySQL

## Workflow Visual (futuro)
- BPMN.io

## Versionamento
- GitHub

## Hospedagem
- HostGator (Plano M)

---

# Estrutura Inicial do Sistema

## Core
- Usuários
- Permissões
- Processos
- Etapas
- Demandas
- Workflow
- Auditoria

---

# Estrutura Base do Banco

## Usuários e Controle
- users
- roles
- permissions

## Workflow
- processes
- process_stages
- process_transitions

## Metadata
- custom_entities
- custom_fields
- custom_records

## Operacional
- tasks
- task_history
- audit_logs

---

# Conceito Principal do MVP

O foco principal do MVP NÃO é construir um sistema bonito.

O foco principal é:

# Validar a engine dinâmica do sistema.

Se a engine funcionar corretamente, o restante do sistema poderá evoluir naturalmente.

---

# Fluxo Básico do Sistema

```txt
Administrador
↓
Cria Processo
↓
Cria Campos
↓
Define Workflow
↓
Usuário cria demanda
↓
Sistema executa fluxo
↓
Histórico e auditoria registrados
```

---

# Roadmap do MVP

# Etapa 0 — Estrutura Inicial
- Configuração do Laravel
- Configuração do banco
- Configuração do GitHub
- Configuração do ambiente

---

# Etapa 1 — Autenticação
- Login
- Logout
- Recuperação de senha
- Controle de sessão
- Controle de permissões

---

# Etapa 2 — Engine Dinâmica
- Criação de entidades
- Criação de campos
- Renderização dinâmica de formulários
- Salvamento dinâmico

---

# Etapa 3 — Workflow
- Processos
- Etapas
- Transições
- Demandas
- Histórico

---

# Etapa 4 — Dashboard
- Minhas demandas
- Pendências
- Demandas concluídas
- Indicadores simples

---

# Etapa 5 — Auditoria
- Logs de ações
- Histórico de alterações
- Rastreabilidade

---

# Organização do Repositório

## Branches

### main
Produção estável

### develop
Branch principal de desenvolvimento

### feature/*
Novas funcionalidades

---

# Estrutura do Trello

## Listas
- Backlog
- To Do
- Doing
- Testes
- Done


---

# Objetivo Estratégico

Construir uma plataforma institucional flexível capaz de atender múltiplos processos internos sem necessidade de desenvolvimento específico para cada demanda.

---

# Resultado Esperado do MVP

Ao final do MVP, o sistema deverá ser capaz de:

✅ Criar processos dinamicamente  
✅ Criar formulários dinamicamente  
✅ Gerenciar demandas  
✅ Executar workflows básicos  
✅ Controlar permissões  
✅ Registrar auditoria  
✅ Operar institucionalmente  

---

# Futuro da Plataforma

Após validação do MVP, o sistema poderá evoluir para:

- BPMN avançado
- SLA
- Notificações automáticas
- Integrações externas
- Analytics
- BI
- IA
- Automação avançada
- APIs institucionais
- Multi-organização

---

# GASPAR INCT

Plataforma institucional desenvolvida para modernização e automação dos processos do INCT CO₂ Zero.