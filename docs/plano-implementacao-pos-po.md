# Plano de implementação — tasks pós-resposta do PO

> Data do plano: 16/11/2026 · Base de código: branch `develop` · Devs: **Gustavo** e **Filipi**
> Janela: 1 semana (5 dias úteis × 2 devs = 10 dias-dev disponíveis)

Este documento consolida a resposta do PO (16/11/2026) em decisões de produto, descreve o que será construído em cada task, a ordem de execução e a divisão entre os dois devs. As estimativas são nossas, feitas a partir da leitura do código; não foram validadas em execução.

### Andamento

Legenda: ✅ feito · 🔄 em andamento (PR aberto) · ⬜ a fazer

| Item | Dev | Status | Observação |
|---|---|---|---|
| PR #39 (limite de 15 caracteres no cadastro) | Gustavo | ✅ | Mergeado na `develop` junto com os PRs #31, #32, #34, #35, #40, #41, #42 e #43. |
| R0 — `CustomFieldComponentFactory` | Filipi | ⬜ | |
| T1 — Gestor obrigatório | Gustavo | 🔄 | PR aberto na branch `feat/gestor-obrigatorio`; validado manualmente no ambiente local. Falta homologação. |
| T2 — Visibilidade de valores | Gustavo | 🔄 | PR aberto na branch `feat/visibilidade-valores`, empilhado sobre o PR do T1; validado manualmente no ambiente local. Inclui o padrão de moeda `1.000,00`. Falta homologação. |
| T3 — Cadeia de Valor | Gustavo | ⬜ | |
| T4 — Campo Vínculo | Filipi | ⬜ | Depende do R0. |
| T5 — Relatório de Cadastros | Gustavo | ⬜ | Depende de T2 e do formatter do T4. |
| T6 — Versionamento de processos | Filipi | ⬜ | Depende do T1 mergeado (clona `manager_id`). |

**Ajustes de regra confirmados pelo PO em 17/11/2026** (já refletidos nas seções abaixo):
- O gestor de Macroprocesso e de Processo precisa ter o perfil `GESTOR` no sistema.
- **Cadastro pode ter um ou mais gestores** (todos com perfil `GESTOR`); Macroprocesso e Processo têm exatamente um.
- O gestor do Macroprocesso também enxerga os valores dos processos desse macroprocesso (T2).

---

## 1. Decisões de produto (resposta do PO + alinhamentos internos)

| # | Task | Decisão |
|---|---|---|
| T1 | Gestor obrigatório | **Todo** Macroprocesso e Processo tem exatamente um Gestor; **todo Cadastro tem um ou mais Gestores**. Todos escolhidos entre usuários ativos com perfil `GESTOR`. O usuário Gestor passa a ter a relação inversa com tudo que gerencia. |
| T2 | Visibilidade de valores | Campos de valor (`CURRENCY`) só aparecem para `ADMIN` e para o Gestor **do dono do campo**: o Processo (na demanda; também o gestor do Macroprocesso ao qual ele pertence) ou o Cadastro (nas telas de cadastro; qualquer um dos seus gestores). Vale para formulário, consultas, relatórios, CSV, PDF e busca. |
| T3 | Cadeia de Valor | Visualização no Painel de Controle no desenho da cadeia de valor de Porter (apoio em faixas, primárias em colunas, seta de Margens), com os macroprocessos dentro de cada bloco e link para cada um. A "função na cadeia de valor" do Macroprocesso passa a seguir esses blocos. |
| T4 | Campo Vínculo | Novo **tipo de campo** "Vínculo com cadastro". O campo é criado uma vez, aponta para uma origem (ex.: cadastro "Equipamentos") e pode ser anexado a processos (formulário da demanda) e a cadastros. Seleção única. |
| T5 | Relatório de Cadastros | Escolhe-se o cadastro e filtram-se registros pelos campos dele (ex.: só impressoras; só uma cidade). Exporta CSV. |
| T6 | Versionamento de processos | Tudo que é definido no processo é versionado: campos, situações, transições e regras, permissões de campo, membros, SLA e gestor. Demanda criada numa versão só tramita nela. Não se cria demanda em versão antiga. Uma versão só pode ser **apagada** quando todas as demandas que dependem dela estiverem encerradas. |

---

## 2. Estado atual do código (o que já existe e vamos reaproveitar)

- **Painel:** `Dashboard` padrão do Filament; widgets em `app/Filament/Widgets/` são descobertos automaticamente (`AdminPanelProvider`).
- **Macroprocesso** (`app/Models/Macroprocess.php`): já tem `value_chain_function` (string). O formulário oferece 5 opções que **não** correspondem à imagem da cadeia de valor; o seeder (`AquiProcessSeeder`) e os testes usam `'Apoio'` e `'Finalístico'`, que nem estão nas opções do formulário.
- **Processo** (`CustomEntity`): tem `purpose` (Apoio/Finalístico/Gerencial), `sla_hours`, relações com campos (pivô `custom_entity_custom_field`), situações (`entity_process_status`), transições (`status_transitions`), regras (`transition_rules`), permissões (`field_permissions`) e membros (`process_members`). **Tudo é amarrado por `entity_id`.** Um hook `booted()` anexa as situações de sistema automaticamente ao criar um processo.
- **Cadastros:** 5 cadastros base (`CustomRecordType` com `is_system = true`: Usuários, Clientes, Projetos, Fornecedores, Macroprocessos) usam `custom_data` JSON nas próprias tabelas. Cadastros criados pelo admin usam `custom_records.data_json`.
- **Campos dinâmicos:** o `match` que monta o componente Filament por `FieldTypeEnum` está **copiado em 3 lugares**: `DemandResource::buildDynamicFieldSchema`, `CustomRecordResource::form` e `HasCustomFields::buildCustomFieldComponents`.
- **Campos de valor:** `FieldTypeEnum::CURRENCY` é renderizado/formatado em `DemandResource`, `CustomRecordResource`, `HasCustomFields`, `DemandReports`, `resources/views/pdf/demand-report.blade.php`, `RuleOperatorEnum`, `TransitionDecisionEvaluator` e `StatusTransitionsRelationManager`. Hoje nenhum aplica regra de papel.
- **Permissão de campo por situação/papel:** `FieldPermissionResolver::resolve()` já devolve `REQUIRED/OPTIONAL/READONLY/HIDDEN` por campo. É o ponto natural para esconder valores.
- **Relatórios:** `DemandReports` (consulta com botão "Consultar", CSV, `saved_reports` só para demandas).
- **PR #39** ✅ (mergeado): limita o nome do cadastro a 15 caracteres e mexe em `CustomRecordTypeResource` e nos resources base. Foi mergeado antes do T1, que também altera `CustomRecordTypeResource`.

---

## 3. Divisão e ordem de execução

### 3.1 Resumo da divisão

| Dev | Sequência | Dias-dev |
|---|---|---|
| **Gustavo** | T1 Gestor → T2 Valores → T3 Cadeia de Valor → T5 Relatório de Cadastros | ~4,5 |
| **Filipi** | R0 Refatoração da fábrica de campos → T4 Vínculo → T6 Versionamento | ~5,5 |

Motivo da divisão: T1 → T2 → T5 formam uma cadeia de dependência (gestor → valores → relatório) e ficam com a mesma pessoa. Filipi pega a refatoração dos campos, o Vínculo (que depende dela) e o Versionamento, que é independente e o maior.

### 3.2 Cronograma

| Dia | Gustavo | Filipi |
|---|---|---|
| **Seg** | Merge do PR #39. **T1** completo (migrations, formulários, relações, seeder, testes). PR de T1 aberto no fim do dia. | **R0** (manhã): extrai `CustomFieldComponentFactory` sem mudar comportamento, PR pequeno para merge rápido. **T4** começa (tarde): `LOOKUP` no enum e UI de origem em `CustomFieldResource`. |
| **Ter** | **T2** Visibilidade de valores (depende de T1 mergeado). | **T4** continua: componente do select, `CustomFieldValueFormatter`, integração em relatório/PDF/infolist. |
| **Qua** | **T3** Cadeia de Valor: enum, migration de conversão, widget. | **T4** fecha (manhã, testes e PR). **T6** começa (tarde): migration e `ProcessVersioningService`. |
| **Qui** | **T5** Relatório de Cadastros (dia 1 de 1,5). | **T6**: ação "Nova versão", filtro de seleção em nova demanda, rótulos de versão. |
| **Sex** | **T5** fecha; buffer, correções de review, homologação. | **T6**: trava de exclusão, testes de regressão do fluxo de demanda, homologação. |

**Realismo:** são ~10 dias-dev de trabalho para 10 dias-dev disponíveis, **sem folga**. Se estourar, a ordem de corte é:
1. **T6** é o primeiro a escorregar (o que não fechar vira PR da semana seguinte; a trava de exclusão e o bloqueio de edição estrutural são os candidatos a ficar para depois).
2. **T5** entra só como MVP (apenas cadastros dinâmicos, sem consultas salvas).
3. T1–T4 não devem ser cortadas.

### 3.3 Dependências

```
PR #39 ──► T1 (Gustavo) ──► T2 (Gustavo) ──► T5 (Gustavo)
                 │                              ▲
                 └──► T6 copia manager_id       │
R0 (Filipi) ──► T4 (Filipi) ── formatter ───────┘
T3 (Gustavo)  independente
T6 (Filipi)   independente, mas clona manager_id (precisa de T1 mergeado)
```

---

## 4. Detalhamento por task

### R0 — Refatoração: `CustomFieldComponentFactory` (Filipi, ~0,5 dia)

**Por quê:** T2 e T4 precisam mexer na criação de componentes por tipo. Sem extrair antes, as alterações entram em 3 cópias e geram conflito entre os devs.

**O que fazer**
- Criar `app/Services/CustomFieldComponentFactory.php` com `make(CustomField $field, string $statePath): Component`, contendo o `match` por `FieldTypeEnum` (já cobre `TEXT, TEXTAREA, NUMBER, CURRENCY, DATE, SELECT, RADIO, CHECKBOX, EMAIL`), label, placeholder e default.
- Trocar os 3 `match` duplicados pela chamada à fábrica. Obrigatoriedade/permissão continua em cada chamador (a regra da demanda usa `FieldPermissionResolver`, a dos cadastros usa `is_required`).

**Fora de escopo:** nenhuma mudança de comportamento.

**Aceite:** `FilamentPagesSmokeTest`, `CustomFieldValidationTest` e `ProcessoAquiFieldPermissionsTest` continuam passando sem alteração.

**Merge:** logo na segunda de manhã, antes de qualquer outra mudança em campos.

---

### T1 — Gestor obrigatório em Cadastro, Macroprocesso e Processo (Gustavo, ~1 dia) 🔄

> **Status:** implementado e com PR aberto (`feat/gestor-obrigatorio`); suíte com 199 testes passando; validado manualmente no ambiente local. Pendente: homologação.
> **Mudança em relação ao plano original:** o Cadastro passou a ter **N gestores** (tabela pivô), por resposta do PO em 17/11/2026. Macroprocesso e Processo seguem com 1.

**Banco**
- [x] Uma migration (`2026_11_19_150000_add_managers_to_record_types_macroprocesses_and_entities`):
  - `manager_id` (uuid, **nullable**, FK para `users`, `nullOnDelete`) em `macroprocesses` e `custom_entities`;
  - pivô `custom_record_type_manager` (`custom_record_type_id`, `user_id`, unique composto, `cascadeOnDelete`) para os Cadastros, que têm um ou mais gestores (cobre cadastros base e criados pelo admin).
- [x] Nullable no banco porque já existem registros; a obrigatoriedade é do formulário.
- [x] `down()` reversível (testado com migrate, rollback e migrate).
- Usar timestamp de migration **distinto** do que o Filipi usar na semana, para não haver colisão de ordenação.

**Models**
- [x] `Macroprocess`, `CustomEntity`: `manager()` (`belongsTo User`) e `manager_id` em `$fillable`.
- [x] `CustomRecordType`: `managers()` (`belongsToMany User`, via pivô).
- [x] `User`: `managedRecordTypes()` (`belongsToMany`), `managedMacroprocesses()`, `managedProcesses()` (`hasMany`). É a relação "perfil Gestor ↔ o que ele gerencia".

**Formulários e telas**
- [x] Trait `App\Filament\Resources\Concerns\HasManagerField`, com dois seletores que só listam usuários `GESTOR` ativos e validam no servidor (`Rule::exists`):
  - `managerSelect()` (único, obrigatório) em `MacroprocessResource` e `CustomEntityResource`;
  - `managersSelect()` (múltiplo, `minItems(1)`) em `CustomRecordTypeResource`.
- [x] Coluna "Gestor" / "Gestores" nas tabelas.
- [x] Infolist do Macroprocesso. `CustomRecordTypeResource` não tem infolist (só formulário e tabela) e o Processo mostra o gestor no formulário desabilitado da `ViewCustomEntity`.
- [x] `UserResource` (visualização do usuário Gestor): mostra o que ele gerencia.

**Seeder e testes**
- [x] `AquiProcessSeeder`: cria o usuário `gestor.aqui@gaspar.local` e o atribui ao macroprocesso e ao processo do AQUI.
- [x] Ajustados `MacroprocessoAndProcessoValidationTest`, `CustomRecordTypeValidationTest` e `ProcessoAquiE2ETest`.
- [x] Novos testes em `GestorObrigatorioTest`: gestor obrigatório nos três formulários; Cadastro aceita vários e Macroprocesso/Processo só um; seletor lista só `GESTOR` ativos; relações inversas do `User`; excluir, desativar ou trocar o perfil de quem gerencia limpa o vínculo ("Sem gestor"); edição sem gestor é permitida; o gestor que deixou de ser GESTOR/ativo por fora do model sai da pivô ao salvar o cadastro.

**Pontos de atenção**
- Registros antigos (inclusive os 5 cadastros base) ficam sem gestor até serem editados; o gestor é obrigatório na **criação**; na edição o campo pode ficar vazio ("Sem gestor"). A listagem mostra "Sem gestor".
- Decisão da review: existe o estado "Sem gestor". Excluir (soft delete), desativar ou tirar o perfil GESTOR de um usuário limpa o que ele gerencia: `manager_id` vira nulo em macroprocesso e processo (coluna anulável) e ele sai da pivô dos cadastros. Isso é feito nos eventos `updated`/`deleted` do `User` (`clearManagement()`), porque `nullOnDelete`/`cascadeOnDelete` não disparam no soft delete. Sem gestor, só o admin (e quem tiver liberação na matriz, ver T2) vê valores.
- ⬜ Opcional, ficou de fora: filtro "sem gestor" nas listagens.
- ⬜ Pendente: validação em homologação (a pivô é criada pelo `migrate` do `/update-system`).

**Aceite:** não é possível criar Cadastro, Macroprocesso ou Processo sem gestor; o gestor aparece em tabelas; `$user->managedProcesses` funciona.

---

### T2 — Visibilidade de valores (Gustavo, ~1 dia, depende de T1) 🔄

> **Status:** implementado e com PR aberto (`feat/visibilidade-valores`, empilhado sobre o PR do T1); 275 testes passando; validado manualmente no ambiente local. Pendente: homologação.
> **Acréscimo ao escopo original:** campo de moeda no padrão brasileiro `1.000,00` (pedido durante a validação).

**Regra central**
- [x] `app/Services/MoneyFieldVisibility.php` com `canView(User $user, Model $owner): bool`:
  - `ADMIN` → `true`.
  - Quem não é `GESTOR` **ativo** → `false` (gestor que virou EXECUTOR ou foi desativado deixa de ver, mesmo se ainda constar como gestor).
  - `$owner` é `CustomEntity` → `$owner->manager_id === $user->id` **ou** o gestor do macroprocesso ao qual ele pertence (resposta do PO).
  - `$owner` é `CustomRecordType` → qualquer um dos gestores do cadastro. Para os cadastros base (`custom_data`), o tipo é resolvido por `model_class` (`CustomRecordType::where('model_class', …)`, como `HasCustomFields::getCustomRecordType()`).
  - Caso contrário (inclusive sem usuário e registro "Sem gestor") → `false`: sem gestor, só o admin (e quem tiver liberação na matriz) vê.
- [x] **Liberação por matriz (decisão do usuário):** se o admin liberou o campo na matriz de permissões de campos, o campo de valor fica liberado para aquela pessoa **naquela situação**, mesmo sem ser gestora. "Liberou" = existe linha **explícita** de `FieldPermission` (entidade, situação, campo), do papel da pessoa (`ProcessMember`) ou genérica, cuja permissão efetiva (a linha do papel vence a genérica, entre papéis vale a mais permissiva) é `OPTIONAL` ou `REQUIRED`. **Só Opcional e Obrigatório liberam**: `READONLY`, `HIDDEN` e a ausência de linha (fallback `READONLY`) mantêm o campo restrito (papel `READONLY` + genérica `OPTIONAL`: o papel vence e não libera). Nesse caso o resolver devolve a permissão da matriz (a pessoa vê e edita) e valem formulário, save, preenchimento, tramitação e gateway. Relatório, PDF e busca não dependem da situação e continuam só para admin e gestores.
- [x] A grade de permissões grava todas as células, inclusive o padrão `READONLY` (também para campo de valor). Como `READONLY` não libera, as linhas gravadas pela grade (ou herdadas de um campo que mudou de tipo para valor) não expõem o valor: ele só é liberado ao marcar `OPTIONAL`/`REQUIRED`.
- [x] `isHiddenFor()`, `viewableEntityIds()` (usado pela busca) e `preserveHiddenValues()` / `stripHiddenFromFormData()` (proteção de dados dos cadastros).

**Pontos de aplicação**

| Lugar | Mudança | Status |
|---|---|---|
| `FieldPermissionResolver::resolve()` | Campos `CURRENCY` viram `HIDDEN` para quem não pode ver e não foi liberado na matriz (cobre o formulário da demanda, o descarte no save e a obrigatoriedade na tramitação). | [x] |
| `DemandResource::buildDynamicFieldSchema()` | Sem situação resolvida (criação com `?entity_id=`), o campo de valor é omitido de quem não pode vê-lo. | [x] |
| `TransitionDecisionEvaluator` / `DemandTransitionService` | Gateway por valor com campo vazio que o ator não enxerga: mensagem "Aguardando o gestor do processo informar o campo X" em vez de citar o campo como a preencher. | [x] |
| Matriz de permissões e editor de regras | Aviso sobre campos de valor (quem libera, o que fica restrito). | [x] |
| `app:audit-currency-values` | Comando que lista valores de moeda no formato antigo `x.xxx` (lido como milhar), para conferir em homologação. Não altera dados. | [x] |
| `EditDemand` / `ViewDemand` | O valor deixa de ser preenchido no estado do formulário (não chega ao navegador). | [x] |
| `CustomRecordResource` (form e tabela) e `HasCustomFields::buildCustomFieldComponents` | Componente e coluna ocultos. | [x] |
| Páginas de edição/visualização dos cadastros (12) | Trait `HidesMoneyValuesOnFill` tira o valor oculto do estado do formulário. | [x] |
| `CustomRecord` e `HasCustomFields` (hook de saving) | Restauram no servidor o valor oculto, pois `data_json`/`custom_data` são regravados por inteiro. | [x] |
| `DemandReports` | Coluna do campo de valor removida. O CSV de demandas hoje não exporta campos customizados, então não havia o que remover. | [x] |
| `demand-report.blade.php` e `DemandPdfController` | Valor omitido no PDF. | [x] |
| `Demand::globalSearch` | A busca por valor só encontra demandas de processos que o usuário pode ver; nos campos adicionais de cliente/projeto, sem permissão a busca olha só as chaves não-moeda do `custom_data`. | [x] |
| `DemandReports` / PDF | Valor exibido com `MoneyInput::display()`, o formatador único. | [x] |

**Riscos do plano, o que se confirmou**
- [x] Campo oculto apagando valor ao salvar a demanda: **não ocorre**. `saveFieldValues` já descartava campos bloqueados e só apagava valores de campos que saíram do processo. Teste de regressão adicionado.
- [x] `TransitionDecisionEvaluator` lê o valor direto do banco e não passa pela camada de visibilidade.
- Riscos novos, não previstos no plano, ambos cobertos por teste:
  - o Filament envia o registro inteiro ao navegador, inclusive chaves sem componente;
  - o JSON dos cadastros era regravado sem o valor oculto.

**Padrão de moeda `1.000,00`**
- [x] `App\Support\MoneyInput`: máscara pt-BR, completa as casas decimais ao sair do campo e abre valores gravados já formatados. O armazenamento continua numérico (`1500.50`), porque regras de transição, relatório e PDF dependem disso.
- [x] Usado nos formulários da demanda, do registro de cadastro e dos cadastros base; a tabela de registros mostra `R$ 800,00`.
- ⬜ Fora do escopo: o editor de regras de transição segue numérico e sem máscara.
- ⬜ Para o R0 (Filipi): o caso `CURRENCY` da `CustomFieldComponentFactory` deve apontar para `MoneyInput::make()`.

**Pontos de atenção**
- A tramitação deixa de cobrar um campo de valor obrigatório de quem não o enxerga (sem liberação ele nem chega a ser `REQUIRED` para a pessoa).
- Gateway por valor que o ator não enxerga: a mensagem pede para aguardar "o gestor do processo ou um administrador"; se processo e macroprocesso estão sem gestor, avisa que só um administrador pode informar o campo.
- Criação com `?entity_id=`: a situação inicial ("Nova" ou a primeira do processo) é resolvida na tela, então a liberação da matriz vale já na criação; sem situação resolvível o campo de valor segue restrito.
- Valores antigos no formato `1.500` (3 casas após o ponto) passam a ser lidos como milhar. Não há migração de dados: rodar `php artisan app:audit-currency-values` em homologação e conferir a lista.
- O `preco` do processo AQUI é `NUMBER`, não `CURRENCY`, então não é afetado. Se o PO quiser escondê-lo, é preciso mudar o tipo do campo.
- ⬜ Não verificado: se o `ActivityLog` registra valores de `custom_data` e `demand_field_values`.
- ⬜ Pendente: validação em homologação.

**Aceite:** executor sem gestão não vê o campo no formulário, no relatório, no PDF nem na busca; gestor do processo e do macroprocesso veem; gestor de **outro** processo não vê; admin vê; salvar a demanda sem permissão não apaga o valor; o campo de moeda segue o padrão `1.000,00`.

---

### T3 — Cadeia de Valor no Painel (Gustavo, ~1 dia)

**Blocos** (conforme a imagem do PO)
- **Atividades de apoio** (faixas horizontais empilhadas): Infra-estrutura · Gestão de Recursos Humanos · Desenvolvimento Tecnológico · Aquisição/Compras.
- **Atividades primárias** (colunas lado a lado): Logística de Entrada · Operações · Logística de Saída · Marketing e Vendas · Serviços.
- **Margens**: seta à direita, só elemento visual (nenhum macroprocesso é associado).

**Banco e enum**
- Criar `App\Enums\ValueChainFunctionEnum` com os 9 blocos, `label()` e `group()` (apoio/primária).
- `MacroprocessResource`: o `Select value_chain_function` passa a usar o enum.
- **Migration de dados:** os valores antigos (`Suportar Processos`, `Definir Diretrizes e Estratégia`, `Entregar Produtos e Serviços`, `Manter Relacionamentos`, `Apoio`, `Finalístico`) não têm equivalente direto nos novos blocos. **Não adivinhar**: zerar para `null`; o widget mostra esses macroprocessos num bloco "Não classificado", e o admin reclassifica.
- Ajustar `AquiProcessSeeder` (contratação de serviços → `Aquisição/Compras`) e os testes que usam `'Apoio'`/`'Finalístico'`.

**Widget**
- `app/Filament/Widgets/ValueChainWidget.php` + view em `resources/views/filament/widgets/value-chain.blade.php`, desenhado em HTML/CSS (faixas, colunas, seta), com cada macroprocesso ativo dentro do seu bloco, linkando para `MacroprocessResource::getUrl('view', …)`.
- Tema escuro/claro e telas pequenas: usar tokens de cor do Filament e `grid` que quebre linha no mobile.

**Aceite:** o Painel mostra o desenho; macroprocesso aparece no bloco certo e abre sua tela; sem classificação aparece em "Não classificado"; o formulário só aceita os 9 blocos.

---

### T4 — Campo "Vínculo com cadastro" (Filipi, ~2 dias, depende de R0)

**Modelo de dados**
- `FieldTypeEnum::LOOKUP` ("Vínculo com cadastro").
- Origem em `options_json`, sem migration: `{ "source": "client|project|supplier|user|macroprocess|process|record_type", "record_type_id": "<uuid, só quando source = record_type>" }`.
- Valor guardado = UUID do registro em `demand_field_values.value`, `custom_records.data_json` ou `custom_data`.
- Atenção: hoje `options_json` é lista de strings para `SELECT`/`RADIO` e o código faz `array_combine`; o `LOOKUP` usa array associativo, então a fábrica não pode aplicar essa lógica a ele. Verificar também o que `FieldsRelationManager`/`CustomFieldResource` assumem sobre `options_json`.

**Telas**
- `CustomFieldResource`: ao escolher o tipo "Vínculo", mostrar o seletor de origem (e de cadastro, quando a origem é `record_type`).
- `CustomFieldComponentFactory`: `LOOKUP` → `Select` com `searchable()`, `getSearchResultsUsing` e `getOptionLabelUsing` (busca paginada, para listas grandes).
- Rótulo de cada origem: cliente/fornecedor/usuário → nome; projeto → `display_name`; macroprocesso/processo → `full_display_name`; registro de cadastro → código + valor do primeiro campo.

**Exibição**
- `app/Services/CustomFieldValueFormatter.php`: converte `(CustomField, valor)` em texto de exibição (UUID → rótulo; moeda → `R$ …`; registro removido → "(removido)").
- Usar em: `DemandReports` (coluna e CSV), PDF da demanda, infolists, tabelas de `CustomRecordResource` e busca.
- O `RuleOperatorEnum`/regras de transição **não** suportam `LOOKUP` nesta entrega (não oferecer o tipo no editor de regras).

**Testes:** criar campo Vínculo apontando para um cadastro; anexar a processo; selecionar na demanda; valor aparece com o nome nas telas e no CSV; registro apagado não quebra a tela.

**Fora de escopo:** seleção múltipla, filtro em cascata, vínculo em regras de transição.

---

### T5 — Relatório de Cadastros (Gustavo, ~1,5 dia, depende de T2 e do formatter de T4)

- Nova página `app/Filament/Pages/CadastroReports.php`, grupo "Relatórios", seguindo o padrão do `DemandReports` (botão "Consultar", CSV).
- Passos: escolher o cadastro (`CustomRecordType`) → o formulário gera um filtro por campo (texto contém, select igual, data por período, número por faixa) → tabela com os campos do tipo → exportar CSV.
- Para cadastros dinâmicos, filtrar por `data_json->campo`. **Confirmar o driver de banco** (MySQL em produção/SQLite nos testes?) porque a sintaxe de JSON path difere.
- Respeitar T2: campos de valor só aparecem para admin ou gestor do cadastro (tela e CSV).
- Usar o `CustomFieldValueFormatter` (T4) para campos de Vínculo e moeda.
- **Consultas salvas:** `saved_reports` hoje só guarda `filters_json` de demandas. Se couber, adicionar `report_type` e `record_type_id`; senão, fica para depois.
- **MVP:** cadastros dinâmicos. Clientes, Projetos, Fornecedores (que usam `custom_data`) entram depois como segunda fonte na mesma página.

**Aceite:** escolher "Equipamentos de TI", filtrar por tipo = impressora e cidade = X, ver só esses registros e exportar o CSV; usuário sem permissão não vê o campo de valor.

---

### T6 — Versionamento de processos (Filipi, ~3 dias)

**Abordagem escolhida: clonar o processo.** Cada versão é um `CustomEntity` próprio. Como todo o fluxo já é amarrado por `entity_id`, as demandas em andamento seguem na versão antiga **sem alterar** `Demand`, `DemandTransitionService`, `StatusTransition` nem `FieldPermissionResolver`. A alternativa (snapshot em JSON com `process_version_id` na demanda) exigiria reescrever mais de 10 consultas em `Demand.php` e os serviços, e foi descartada.

**Banco**
- Migration em `custom_entities`: `root_entity_id` (uuid, nullable; aponta para a versão 1 do processo), `version` (int, default 1), `is_current` (bool, default true), `previous_version_id` (uuid, nullable).
- Verificar se `code` tem índice único (migration de código sequencial). Se tiver, o clone recebe seu próprio `code` e a interface exibe o código da raiz + `v2`.

**Serviço** `app/Services/ProcessVersioningService.php::createNewVersion(CustomEntity $source): CustomEntity`, em transação, copiando:
- o registro do processo (nome, macroprocesso, descrição, finalidade, `sla_hours`, `manager_id`);
- campos (`custom_entity_custom_field`, preservando `field_order`);
- situações (`entity_process_status`, preservando `display_order`). **Atenção:** o hook `booted()::created` já anexa as situações de sistema ao novo processo; o clone deve limpar essas ligações antes de copiar, para não duplicar;
- transições (`status_transitions`) e regras (`transition_rules`), **remapeando os ids** das transições copiadas;
- permissões de campo (`field_permissions`) e membros (`process_members`).
- Marca a versão de origem com `is_current = false` (permanece ativa) e a nova com `is_current = true`.

**Telas**
- `CustomEntityResource`: ação "Criar nova versão" (admin), com confirmação.
- Select de **nova demanda** (e de subdemanda) lista apenas versões `is_current` e ativas. O select de filtro dos relatórios lista todas, com rótulo "v1 (anterior)".
- Rótulo `full_display_name` passa a incluir a versão quando `version > 1`.

**Trava de exclusão** (regra do PO: tudo da versão só pode ser apagado quando as demandas dependentes estiverem encerradas)
- `DeleteAction`/`DeleteBulkAction` de `CustomEntityResource` bloqueados enquanto houver demandas dessa versão em estado não encerrado. Considerar encerradas apenas `CLOSED`, `CANCELED` e `EVALUATED`; rascunhos (`DRAFT`) contam como pendentes.
- A mesma verificação deve valer para apagar/alterar de forma destrutiva itens da versão (campo, situação, transição, membro) enquanto houver demanda pendente nela. Se faltar tempo, é o primeiro item a ficar para depois.

**Testes:** nova versão copia tudo e remapeia regras; demanda antiga continua tramitando na v1 após a v2 existir; nova demanda não pode ser criada na v1; exclusão bloqueada com demanda pendente e liberada quando todas encerram; regressão do `ProcessoAquiE2ETest`.

---

## 5. Fluxo de trabalho e convenções

- **Branches** (a partir da `develop`, uma por task): `feat/gestor-obrigatorio`, `feat/visibilidade-valores`, `feat/cadeia-valor`, `refactor/custom-field-factory`, `feat/campo-vinculo`, `feat/relatorio-cadastros`, `feat/versionamento-processos`.
- **PRs** contra `develop`, em Conventional Commits em pt-BR, descrição com Resumo / O que muda / Fora deste PR / Testes (skill `pr-create`). Um PR por task, pequeno o bastante para review no mesmo dia.
- **Review cruzado:** Gustavo revisa os PRs do Filipi e vice-versa (skill `pr-review`).
- **Ordem de merge:** PR #39 → R0 → T1 → T4 → T2 → T3 → T5 → T6. Quem mergeia avisa o outro para fazer `git merge develop` na sua branch.
- **Migrations:** cada dev usa timestamps distintos e nunca edita migration já mergeada.
- **Pontos de colisão** (avisar no chat antes de mexer):

| Arquivo | Quem mexe |
|---|---|
| `CustomEntityResource` | T1 (campo gestor) e T6 (ação nova versão e trava) |
| `CustomRecordTypeResource` | PR #39, T1 |
| `DemandResource::buildDynamicFieldSchema` | R0, T2, T4 |
| `FieldPermissionResolver` | T2 |
| `DemandReports` | T2, T4 |
| `AquiProcessSeeder` e testes que criam macroprocesso/processo | T1, T3 |

- **Definição de pronto** por task: testes novos passando; suíte afetada passando (`php artisan test`); `pint` limpo; validado em homologação; PR aprovado.

---

## 6. Perguntas abertas (responder cedo; nenhuma bloqueia a segunda-feira)

1. **T1 — escopo do gestor do cadastro:** o gestor de um cadastro vale para todos os registros desse cadastro? (Assumido: sim.): SIM. **Complemento (17/11):** o cadastro pode ter **um ou mais** gestores; Macroprocesso e Processo têm um só. Todos precisam ter o perfil `GESTOR`.
2. **T2 — gestor de macroprocesso:** ele também enxerga os valores dos processos do seu macroprocesso? (Assumido: não; só o gestor do próprio processo.) : SIM
3. **T3 — rótulos:** os 9 nomes de faixas/colunas da imagem são definitivos ou só exemplo? (Define o enum.) : Só exemplo, vamos utilizar o enum do sistema pra montar a imagem
4. **T3 — dados antigos:** confirmar que zerar `value_chain_function` dos macroprocessos existentes e reclassificar manualmente é aceitável. : SIM
5. **T6 — edição estrutural:** enquanto uma versão tiver demandas pendentes, a estrutura dela fica travada para edição (obriga criar nova versão) ou só a exclusão é travada? (Assumido: só a exclusão nesta entrega.) : Só a exclusão

---

## 7. Feedback final sobre o plano

Gostei muito das seguintes partes:
- A clareza das tarefas, dividas em 7 pontos.

As seguintes partes poderiam ser melhoradas:
- O jeito que está dividido entre os desenvolvedores.
- O tempo estimado para cada tarefa.
- O plano de implementação está em inglês, mas eu prefiro em português.
- A divisão de tarefas não ficou boa, ficou com muita coisa para uma pessoa e pouca para outra.

---

## 7. Fora do escopo desta semana

- Vínculo de seleção múltipla ou em cascata; vínculo em regras de transição.
- Relatórios de Clientes, Projetos e Fornecedores pela página de Cadastros (entram depois, mesma página).
- Migração automática dos valores antigos de `value_chain_function`.
- Troca de gestor em lote e notificações ao gestor.

## 8. Riscos

| Risco | Impacto | Mitigação |
|---|---|---|
| T6 estourar o prazo (3 dias, sem folga) | Versionamento fecha na semana seguinte | Cortar a trava de itens internos; entregar primeiro clone + seleção + trava de exclusão |
| Campo oculto apagar valor ao salvar a demanda (T2) | Perda de dado financeiro | Verificar `EditDemand` antes; teste de regressão dedicado |
| Conflito entre R0, T2 e T4 em `DemandResource` | Retrabalho de merge | R0 mergeado na segunda de manhã; avisos no chat |
| Sintaxe de JSON diferente entre MySQL e SQLite (T5) | Filtro funciona em produção mas falha nos testes (ou vice-versa) | Confirmar driver; isolar a consulta em um método testado |
| `code` único bloqueando o clone de processo (T6) | Falha na criação da versão | Verificar o índice no início de T6 |
