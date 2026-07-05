# GASPAR — Plataforma Institucional de Gestão por Processos

> Sistema institucional de BPM (Business Process Management) com foco em automação, padronização, rastreabilidade e gerenciamento dinâmico de processos baseado em uma arquitetura orientada a metadados (*metadata-driven architecture*).

---

## 🎯 Fase Atual do Projeto: MVP Concluído

O MVP do Gaspar foi finalizado e validado com sucesso. A plataforma está totalmente funcional e conta com os seguintes módulos operacionais:

*   **Engine Dinâmica (Metadata-Driven)**: Criação de Entidades (Processos) e Campos Dinâmicos (texto, data, booleano, etc.) via painel administrativo, salvando as respostas dinamicamente em formato JSON no banco, sem a necessidade de alterar tabelas físicas do MySQL.
*   **Workflow Avançado**: Mapeamento de Papéis de Processo (`process_roles`), Situações (`process_statuses`) e transições dinâmicas autorizadas. O sistema move as demandas entre situações, alterando dinamicamente os usuários/papéis responsáveis a cada transição.
*   **Dashboard Inteligente**: Indicadores agregados na tela inicial (Minhas Demandas Pendentes, Vencidas, Concluídas, Ativas) visíveis para todos os colaboradores, além de uma listagem inteligente de pendências dinâmicas para o executor logado.
*   **Auditoria Completa (Logs de Atividades)**: Rastreabilidade total. Cada ação de criação, alteração ou exclusão nos principais modelos gera logs detalhados estruturados (guardando valores antigos e novos no banco) visíveis apenas para Administradores.


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
│   ├── Http/Controllers/       # Controladores da aplicação
│   └── Models/                 # Modelos do Eloquent mapeando o banco (User, Demand, StatusTransition, etc.)
├── database/
│   ├── migrations/             # Estrutura física fixa do banco de dados MySQL
│   └── seeders/                # Populadores de dados de teste (seeder de banco inicial)
├── resources/
│   ├── views/                  # Telas HTML/Blade do sistema
│   └── css/                    # Estilos CSS globais da aplicação
├── routes/
│   └── web.php                 # Definições de rotas web
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
