# GASPAR — Relatório Técnico de Arquitetura e Engenharia de Software
**Visão Aprofundada da Arquitetura, Tecnologias, Mecanismos de Decisão e Ciclo de Vida**

---

## 1. Visão Geral da Arquitetura e Stack Tecnológico

O **Gaspar** é uma aplicação corporativa orientada a serviços de gestão por processos de negócio (*BPM — Business Process Management*), concebida sobre o paradigma de **Arquitetura Orientada a Metadados (*Metadata-Driven Architecture*)**.

A premissa central é permitir que novos processos, formulários dinâmicos, situações de atendimento, regras de encaminhamento e permissões sejam totalmente configuráveis via interface administrativa, sem necessidade de alterações no código-fonte ou execução de novas migrações de banco de dados estruturais.

| Camada de Arquitetura | Tecnologias & Componentes Principais | Responsabilidade no Sistema |
| :--- | :--- | :--- |
| **Apresentação (UI/UX)** | Filament v3, Livewire 3, Alpine.js, Tailwind CSS | Painel administrativo reativo, visão Kanban/Lista, formulários dinâmicos e modo escuro. |
| **Aplicação & Regras** | Laravel 12 (PHP 8.3), Policies, Middlewares | Controle de autorização, regras de negócio, serviços de transição e emissão de PDF. |
| **Motor de Workflow (BPM)** | `DemandTransitionService`, `TransitionDecisionEvaluator` | Orquestração de situações, gateways de decisão DMN e cálculo dinâmico de SLA. |
| **Persistência de Dados** | MySQL 8.0+ / MariaDB, Eloquent ORM, UUIDv7 | Armazenamento de dados e metadados, identificadores cronológicos e auditoria completa. |

### Componentes Tecnológicos Principais

* **Linguagem & Framework Base:** PHP 8.3 e Laravel 12.
* **Painel Administrativo e Componentes Reativos:** Filament v3 integrado com Livewire 3 e Alpine.js, permitindo renderizações parciais reativas e suporte a visualização em Lista ou Quadro Kanban (`HasKanbanView`).
* **Estilização e Design System:** Tailwind CSS com paleta institucional, suporte a modo escuro e componentes com feedback visual e microanimações.
* **Banco de Dados Relacional:** MySQL / MariaDB (com total compatibilidade para SQLite em suítes de testes em memória).
* **Identificadores Únicos Universais (UUIDv7):** Implementação nativa através de `Str::uuid7()` em todas as entidades do sistema. O UUIDv7 combina um carimbo temporal milissegundo de alta precisão nos primeiros bits com entropia aleatória, propiciando **ordenação cronológica natural por chave primária** e evitando fragmentação de índices B-Tree no banco de dados.
* **Emissão de Relatórios:** `barryvdh/laravel-dompdf`, convertendo templates Blade tipograficamente estruturados em documentos PDF estritos.

---

## 2. Engine Dinâmica de Metadados (*Metadata-Driven Forms*)

Para suportar múltiplos tipos de processos sem criar tabelas físicas para cada um, o sistema adota uma derivação otimizada do padrão **EAV (*Entity-Attribute-Value*)**:

```
[ custom_entities ] (Processo)
        │ 1:N
        ▼
[ custom_entity_custom_field ] (Pivô de Associação)
        │ N:1
        ▼
[ custom_fields ] (Definição dos Campos)
        │
        ├─ Tipo: TEXT, NUMBER, DATE, SELECT, CHECKBOX, RADIO, FILE...
        ├─ Regras: is_required, options_json, default_value, field_order
        │
        ▼ 1:N
[ demand_field_values ] (Respostas Armazenadas)
        │
        ├─ demand_id (FK UUIDv7)
        ├─ custom_field_id (FK UUIDv7)
        └─ value (TEXT / JSON)
```

### Principais Características da Engine:
1. **Tipagem e Serialização:** Campos escalares (texto, número, data) são gravados diretamente, enquanto listas, seleções múltiplas e arrays são serializados em JSON.
2. **Integração Reativa no Filament:** O método `buildDynamicFieldSchema()` lê as definições dos campos associados ao processo selecionado (`entity_id`) e monta a árvore de componentes de formulário dinamicamente em tempo de execução.
3. **Resolução de Permissão de Campos:** O serviço `FieldPermissionResolver` avalia a situação atual da demanda e os papéis do usuário conectado para determinar se cada campo deve estar no modo **Obrigatório**, **Opcional**, **Somente Leitura (*Readonly*)** ou **Oculto**.

---

## 3. Motor de Transições e Gateways de Decisão (BPM / DMN)

O direcionamento das demandas entre as situações de atendimento é controlado pelo serviço central `DemandTransitionService`, apoiado pelo avaliador de regras `TransitionDecisionEvaluator`.

### 3.1. Tipos de Transição
1. **Transição Direta:** A solicitação é enviada para uma situação pré-determinada (`to_status_id`).
2. **Transição Condicional (Gateway DMN):** O destino depende de regras lógicas cadastradas (`transition_rules`). O sistema avalia os campos da demanda contra as condições na ordem de prioridade (`rule_order`). A primeira regra satisfeita determina a situação de destino; caso nenhuma seja atendida, utiliza-se a situação padrão de contingência (*fallback*).

```
Demanda (Ex: Licitando)
        │
        ▼
[ Gatilho de Transição: "Concluir Licitação" ]
        │
        ▼
[ TransitionDecisionEvaluator ]
   ├─ Regra 1: "Preço > Y" ───(Verdadeiro)───➔ Situação: "Aprovando Alto"
   ├─ Regra 2: "Preço > X"  ───(Verdadeiro)───➔ Situação: "Aprovando"
   └─ Fallback: "Senão"            ──────────────────➔ Situação: "Adquirindo"
```

### 3.2. Operadores Suportados pelo Motor de Regras
* **Numéricos e Monetários:** `EQUALS`, `NOT_EQUALS`, `GREATER`, `GREATER_EQUAL`, `LESS`, `LESS_EQUAL`, `BETWEEN` (com normalização automática de pontuação decimal brasileira: `R$ 10.000,00` ➔ `10000.00`).
* **Datas:** `EQUALS`, `BEFORE`, `AFTER`, `BETWEEN`.
* **Textuais e Seleções:** `EQUALS`, `NOT_EQUALS`, `CONTAINS`, `IN_LIST`, `EMPTY`, `FILLED`.

---

## 4. Ciclo de Vida da Demanda e Travas de Integridade

O estado da demanda é regulado por dois eixos complementares:
* **Eixo Operacional (`status`):** `DRAFT` (Rascunho), `ACTIVE` (Em Atendimento), `CLOSED` (Encerrada), `EVALUATED` (Avaliada pelo Demandante) ou `CANCELED` (Cancelada).
* **Eixo de Fluxo de Trabalho (`process_status_id`):** Aponta para o registro específico em `process_statuses` (ex.: *Nova*, *Licitando*, *Aprovando*, *Adquirindo*, *Quitando*, *Encerrada*, *Avaliada*).

### Travas de Segurança Operacional

| Mecanismo de Integridade | Regra e Comportamento Operacional |
| :--- | :--- |
| **Subdemandas Abertas** | O método `hasPendingChildren()` bloqueia o encerramento ou cancelamento da demanda-mãe enquanto houver subdemandas em rascunho ou ativas. |
| **Ação de Retorno Retroativo** | Só permitida se a transição anterior possuir `allow_return = true` e o operador autenticado for o responsável atual (`assigned_to`) ou ADMIN. |
| **Imutabilidade de Processo** | O campo `entity_id` é travado contra alteração após a criação da demanda, evitando perda de integridade entre campos dinâmicos e situação. |
| **Situações Nativas (`is_system`)** | Situações essenciais (*Nova*, *Encerrada*, *Cancelada*) são blindadas no Model contra exclusão acidental ou em massa. |

---

## 5. Motor de Prazos, SLA e Monitoramento Automatizado

O controle de prazos é gerenciado diretamente pelo ciclo de vida da demanda e por rotinas de background:

1. **Cálculo Automático de Prazo:**
   * No evento `creating` do modelo `Demand`, se o processo possuir horas configuradas (`sla_hours > 0`), o prazo limite é computado automaticamente: `sla_due_at = now()->addHours(sla_hours)`.
2. **Início do Atendimento Oficial:**
   * Na primeira transição para fora da situação inicial/rascunho, o `DemandTransitionService` marca a estampa `started_at = now()` e recalcula o SLA a partir dessa data efetiva de início de atendimento.
3. **Comando de Verificação de Prazos (`CheckDemandDeadlines`):**
   * Comando executável via cron (`php artisan app:check-demand-deadlines`) ou rota administrativa protegida (`/trigger-deadlines`).
   * **Política de Notificações de SLA:**
     * Alerta preventivo emitido **1 dia antes** do vencimento.
     * Alerta de urgência no **dia do vencimento**.
     * Notificação diária de atraso enquanto a demanda permanecer vencida e aberta.
     * Controle de concorrência com estampa `last_deadline_alert_date` para garantir estritamente um único alerta por dia por demanda.

---

## 6. Camada de Segurança, Permissões e Auditoria

### 6.1. Proteção de Rotas Administrativas
Rotas operacionais sensíveis (`/update-system`, `/trigger-deadlines`, `/test-email`, `POST /desinstalar`) são interceptadas pelo middleware dedicado `EnsureAdmin`:
* Verifica a autenticação do usuário.
* Exige `user_role === UserRoleEnum::ADMIN`.
* Rejeita acessos não autorizados com código HTTP 403 Forbidden.

### 6.2. Auditoria Estruturada (`ActivityLog`)
Todas as alterações críticas e transições de situação utilizam a trait `LogsActivity`, registrando:
* `user_id` do operador.
* `auditable_type` e `auditable_id`.
* `old_values` e `new_values` em formato JSON.
* Endereço IP e identificador de navegador (*User-Agent*).

### 6.3. Resiliência do Subsistema de Mensageria (SMTP)
Os disparos de e-mail através das notificações transacionais do Laravel (`DemandActivityNotification`, `DemandSatisfactionNotification`, `DemandDeadlineNotification`) operam com proteção `try/catch`. Caso o servidor SMTP externo oscile momentaneamente, a falha é registrada em log de advertência (`Log::warning`), sem abortar a transação do banco de dados nem devolver erro 500 ao usuário.

---

## 7. Painel do Demandante e Experiência do Usuário (UX)

Para eliminar o problema de demandas concluídas sumirem antes de serem avaliadas pelo solicitante, foi implementado o escopo dedicado `scopePendingActionForUser`:

```php
public function scopePendingActionForUser($query, User $user)
{
    return $query->where(function ($outer) use ($user) {
        // 1. Demandas ativas onde o usuário é responsável ou participante autorizado
        $outer->where(function ($activeQ) use ($user) {
            $activeQ->where('status', DemandStatusEnum::ACTIVE->value)
                ->pendingForUser($user);
        // 2. Demandas encerradas aguardando avaliação pelo solicitante logado
        })->orWhere(function ($evalQ) use ($user) {
            $evalQ->where('requested_by', $user->id)
                ->where(function ($sq) {
                    $sq->where('status', DemandStatusEnum::CLOSED->value)
                        ->orWhereHas('processStatus', fn ($ps) => $ps->where('system_key', 'closed'));
                })
                ->whereNull('satisfaction_rating');
        });
    });
}
```

* **Benefício:** A demanda encerrada permanece destacada na Home do demandante com a etiqueta visual `⭐ Aguardando sua avaliação de atendimento` e um botão de ação rápida "Avaliar". No momento em que a nota é enviada, o status é promovido para `EVALUATED` e o registro é arquivado das pendências imediatas.

---

## 8. Estrutura de Testes Automatizados e Qualidade

O sistema conta com suíte de testes de unidade e integração executada via PHPUnit / Pest:

```text
Testes de Unidade (Tests\Unit):
  ✓ FieldPermissionResolverTest (Resolução de permissões por papel/situação)
  ✓ TransitionDecisionEvaluatorTest (Operadores numéricos, datas, textos e fallback)

Testes de Funcionalidade e Integração (Tests\Feature):
  ✓ DemandTransitionServiceTest (Gateways, assertRequiredFields, bloqueio de subdemandas)
  ✓ ProcessoAquiE2ETest (Suite E2E do processo de contratação):
      - Roteamento por faixas de preço no Gateway (< R$ 2.000, 2.000 a 10.000 e > 10.000)
      - Retorno por reprovação de aprovação
      - Envio de notificações ao registrar relatos
      - Bloqueio de rotas administrativas para não-administradores
      - Trava de conclusão com subdemandas abertas
      - Renderização Livewire e fluxo de avaliação no painel do demandante
      - Cálculo automático de SLA (120h), started_at e emissão do PDF sem pendências

Status: 48 testes passando com sucesso (173 asserções).
```

---

## 9. Conclusão Técnica

O **Gaspar** apresenta uma arquitetura robusta, extensível e totalmente alinhada aos padrões modernos do ecossistema Laravel e BPM. O isolamento de responsabilidades entre motor de regras, serviços de transição e interface reativa garante manutenibilidade de longo prazo, alta performance e confiabilidade para a gestão institucional.
